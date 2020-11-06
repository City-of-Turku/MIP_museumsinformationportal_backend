<?php

namespace App\Http\Controllers\Rak;

use App\Kayttaja;
use App\Utils;
use App\Http\Controllers\Controller;
use App\Library\Gis\MipGis;
use App\Library\String\MipJson;
use App\Rak\Alue;
use App\Rak\AlueKyla;
use App\Rak\AlueMuutoshistoria;
use App\Rak\Arvoalue;
use App\Rak\InventointiprojektiAlue;
use App\Rak\Kuva;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class AlueController extends Controller {


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.alue.katselu')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    		'rivi'				=> 'numeric',
    		'rivit'				=> 'numeric',
    		'jarjestys'			=> 'string',
    		'jarjestys_suunta'	=> 'string',
    		'nimi'				=> 'string',
    		"kunta"				=> "string",
    		"kuntanumero"		=> "string",
    		"kyla"				=> "string",
    		"kylanumero"		=> "string",
    		//"aluerajaus"		=> "string|regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/",
    		'id'				=> "numeric|exists:alue,id",
    	]);

    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature(null, null);
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		foreach($validator->errors()->all() as $error)
    			MipJson::addMessage($error);
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {
    		try {

    			/*
    			 * Initialize the variables that needs to be "reformatted"
    			 */
    			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
    			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
    			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
    			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

    			$entities = Alue::getAll();

    			$entities = $entities->with(array(
    				'kylat' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
						if ($jarjestys_kentta == "kyla") {
							$query->orderBy("nimi", $jarjestys_suunta);
						}
    				},
    				'kylat.kunta' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
						if ($jarjestys_kentta == "kunta") {
							$query->orderBy("nimi", $jarjestys_suunta);
						}
    				},
    				'inventointiprojektit' => function($query) { $query->withExcludeTechProjects()->select('inventointiprojekti.nimi'); },
    				'inventoijat_str',
    				'luoja'
    			));
    			/*
    			 * If ANY search terms are given limit results by them
    			 */
    			if($request->id) {
    				$entities = Alue::getSingle($request->id);
    			}
    			if($request->nimi) {
    				$entities->withAreaName($request->nimi);
    			}
    			if($request->kunta) {
    				$entities->withMunicipalityName($request->kunta);
    			}
    			if($request->kuntaId) {
    			    $entities->withMunicipalityId($request->kuntaId);
    			}
    			if($request->kuntanumero) {
    				$entities->withMunicipalityNumber($request->kuntanumero);
    			}
    			if($request->kyla) {
    				$entities->withVillageName($request->kyla);
    			}
    			if($request->kylaId) {
    			    $entities->withVillageId($request->kylaId);
    			}
    			if($request->paikkakunta) {
    			    $entities->withPaikkakunta($request->paikkakunta);
    			}
    			if($request->kylanumero) {
    				$entities->withVillageNumber($request->kylanumero);
    			}
    			//Aluerajaus tarkoittaa bounding boxia
    			if($request->aluerajaus) {
    				$entities->withBoundingBox($request->aluerajaus);
    			}
    			//Polygonrajaus tarkoittaa vapaamuotoista piirrettyä geometriaa jonka mukaan rajataan
    			if($request->polygonrajaus) {
    				$entities->withPolygon($request->polygonrajaus);
    			}
    			if($request->inventointiprojektiId || $request->inventoija) {
    				$entities->withInventointiprojektiOrInventoija($request->inventointiprojektiId, $request->inventoija);
    			}

    			if($request->luoja) {
    				$entities->withLuoja($request->luoja);
    			}

    			// order the results by given parameters
    			$entities->withOrderBy($request->aluerajaus, $jarjestys_kentta, $jarjestys_suunta);

    			// calculate the total rows of the search results
    			$total_rows = Utils::getCount($entities);

    			// ID:t listaan ennen rivien rajoitusta
    			$kori_id_lista = Utils::getIdLista($entities);

    			// limit the results rows by given params
    			$entities->withLimit($rivi, $riveja);

    			// Execute the query
    			$entities = $entities->get();

    			MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
		    	foreach ($entities as $entity) {

		    		/*
		    		 * clone $entity so we can handle the "properties" separately
		    		 * -> remove "sijainti" from props
		    		 */
		    		$properties = clone($entity);
		    		unset($properties['sijainti']);
		    		unset($properties['alue']);

		    		if(!is_null($entity->sijainti)) {
		    			MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($entity->sijainti), $properties);
		    		} else {
		    			MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($entity->alue), $properties);
		    		}
		    	}

		    	/*
		    	 * Koritoiminnallisuus. Palautetaan id:t listana.
		    	 */
		    	MipJson::setIdList($kori_id_lista);

		    	MipJson::addMessage(Lang::get('alue.search_success'));

    		} catch(Exception $e) {
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('alue.search_failed'));
    		}
    	}
    	return MipJson::getJson();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.alue.luonti')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    		'nimi'			=> 'required|string|min:2',
    		'sijainti'		=> "nullable|regex:/^\d+\.\d+\ \d+\.\d+$/", // 20.123456 60.123456 (lat, lon)
    		/*
    		 * Area type of geometry field
    		 * requires MINIMUM 4 pairs of lat/lon coords
    		 * For example: "20.123456 60.123456,20.123456 60.123456,20.123456 60.123456,20.123456 60.123456"
    		 * TODO-ed: now ONLY accepts exactly 4 pairs of coords, must be changed to allow 4 or more pairs...
    		 * --> Actually currently validator only checks that string STARTS with 4 valid pairs of coordinates...
    		 */
    		'alue'		=> 'nullable|min:80'//"regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/", // 20.123456 60.123456,20.123456 60.123456,20.123456 60.123456,20.123456 60.123456 (lat lon, lat lon, lat lon, lat lon = minimum of 4 pairs)

    	]);

    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature(null, null);
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		foreach($validator->errors()->all() as $error)
    			MipJson::addMessage($error);
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {
    		try {

    			/*
    			 * Validate the area coordinate input from user
    			 */
    			//Do not validate as we may not have alue, instead sijainti.
    			//$validation = MipGis::validateAreaCoordinates($request->alue);
    			//if( $validation !== true)
    			//	return $validation;
    			// wrap the operations into a transaction
    			DB::beginTransaction();
    			Utils::setDBUser();

    			try {

	    			$entity = new Alue($request->all());

	    			/*
	    			 * convert coordinate pairs to geometry value so we can insert it to db
	    			 */
	    			if(isset($request->sijainti)) {
	    				$keskipiste = MipGis::getPointGeometryValue($request->sijainti);
	    				$entity->keskipiste = $keskipiste;
	    			}
	    			//Alue is not mandatory as we can have sijainti
	    			if($request->alue)
	    			{
	    				$aluerajaus = MipGis::getAreaGeometryValue($request->alue);
	    				$entity->aluerajaus = $aluerajaus;
	    			}

	    			$author_field = Alue::CREATED_BY;
	    			$entity->$author_field = Auth::user()->id;
	    			$entity->save();

	    			AlueKyla::update_alue_kylat($entity->id, $request->input('kylat'));

	    			//Update the related kiinteisto inventointiprojekti
	    			if($request->inventointiprojekti) {
	    				InventointiprojektiAlue::insertInventointitieto($request->inventointiprojekti, $entity->id);
	    			}

    			} catch(Exception $e) {
    				// Exception, always rollback the transaction
    				DB::rollback();
    				throw $e;
    			}

    			// And commit the transaction as all went well
    			DB::commit();

    			MipJson::addMessage(Lang::get('alue.save_success'));
    			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

    		} catch(Exception $e) {
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('alue.save_failed'));
    		}
    	}
    	return MipJson::getJson();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.alue.katselu')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	if(!is_numeric($id)) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {
    		try {
    			$entity = Alue::getSingle($id)->with(array(
    						'luoja',
    						'muokkaaja',
    						'valueAreas',
    						'valueAreas.aluetyyppi',
    						'valueAreas.arvotustyyppi',
    						'kylat',
    						'kylat.kunta',
    						'inventointiprojektit'
    			))->first();

    			if($entity) {
    				/*
    				 * Haetaan jokaiselle inventointiprojektille VIIMEKSI LISÄTTY inventointitieto per inventoija.
    				 * Tehdään tässä, koska ei onnistunut näppärästi query builderilla muualla.
    				 */
    				foreach($entity->inventointiprojektit as $ip) {
    					$ip->inventoijat = Alue::inventoijat($entity->id, $ip->id);
    				}

    				$properties = clone($entity);
    				unset($properties['sijainti']);
    				unset($properties['alue']);

    				if(!is_null($entity->sijainti))
    					MipJson::setGeoJsonFeature(json_decode($entity->sijainti), $properties);
    				else if(!is_null($entity->alue))
    					MipJson::setGeoJsonFeature(json_decode($entity->alue), $properties);
    				else {
    					MipJson::setGeoJsonFeature(null, $properties);
    				}

    				MipJson::addMessage(Lang::get('alue.search_success'));
    			}
    			else {
    				MipJson::setGeoJsonFeature();
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				MipJson::addMessage(Lang::get('alue.search_not_found'));
    			}
    		}
    		catch(QueryException $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::addMessage(Lang::get('alue.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
    }

    /**
     * Alueiden haku koriin
     */
    public function kori(Request $request) {

        if(!$request->kori_id_lista){
            MipJson::addMessage(Lang::get('alue.search_failed'));
            return MipJson::getJson();
        }

        try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 25;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            // Käytetään annettua id listaa tai haetaan tapahtumilta id:t
            $idLista = array();

            $idLista = $request->kori_id_lista;

            for($i = 0; $i<sizeof($idLista); $i++) {
                $idLista[$i] = intval($idLista[$i]);
            }

            $alueet = Alue::getAll();

            $alueet = $alueet->with(array(
                'kylat' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
                if ($jarjestys_kentta == "kyla") {
                    $query->orderBy("nimi", $jarjestys_suunta);
                }
                },
                'kylat.kunta' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
                if ($jarjestys_kentta == "kunta") {
                    $query->orderBy("nimi", $jarjestys_suunta);
                }
                },
                'inventointiprojektit' => function($query) { $query->withExcludeTechProjects()->select('inventointiprojekti.nimi'); },
                'inventoijat_str',
                'luoja'
                    ));

            // Suodatus id listan mukaan
            $alueet->withAlueIdLista($idLista);

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($alueet);

            // ID:t listaan ennen rivien rajoitusta
            $kori_id_lista = Utils::getIdLista($alueet);

            // Rivimäärien rajoitus parametrien mukaan
            $alueet->withLimit($rivi, $riveja);

            // Sorttaus
            $alueet->withOrderBy($request->aluerajaus, $jarjestys_kentta, $jarjestys_suunta);

            // suorita query
            $alueet = $alueet->get();

            MipJson::initGeoJsonFeatureCollection(count($alueet), $total_rows);

            foreach ($alueet as $alue) {
                //Tarkastetaan jokaiselle alueelle kuuluvat oikeudet
                $alue->oikeudet = Kayttaja::getPermissionsByEntity(null, 'alue', $alue->id);
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $alue);
            }

            /*
             * Koritoiminnallisuus. Palautetaan id:t listana.
             */
            MipJson::setIdList($kori_id_lista);

            MipJson::addMessage(Lang::get('alue.search_success'));
        }
        catch(QueryException $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('alue.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.alue.muokkaus')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    		'nimi'				=> 'required|string|min:2',
    		'sijainti'			=> "nullable|regex:/^\d+\.\d+\ \d+\.\d+$/", // 20.123456 60.123456 (lat, lon)
    		//'alue'				=> "required|regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/", // 20.123456 60.123456,20.123456 60.123456,20.123456 60.123456,20.123456 60.123456 (lat lon, lat lon, lat lon, lat lon = minimum of 4 pairs)
    		'alue'				=> "nullable|min:80",//|regex:([-]?\d+.\d+ [-]?\d+.\d+)((,[-]?\d+.\d+ [-]?\d+.\d+)+)?",
    	]);

    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature(null, null);
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		foreach($validator->errors()->all() as $error) {
    			MipJson::addMessage($error);
    		}
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    		return MipJson::getJson();
    	}

    	/*
    	 * Validate the area coordinate input from user
    	 */
    	//Do not validate as we may have sijainti.
    	//$validation = MipGis::validateAreaCoordinates($request->alue);
    	//if( $validation !== true)
    	//	return $validation;

    	$entity = Alue::find($id);

    	if(!$entity){
    		//error, entity not found
    		MipJson::setGeoJsonFeature(null, null);
    		MipJson::addMessage(Lang::get('alue.search_not_found'));
    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    		return MipJson::getJson();
    	}

    	// wrap the operations into a transaction
    	DB::beginTransaction();
    	Utils::setDBUser();

    	try {

	    	$entity->fill($request->all());

	    	/*
	    	 * convert coordinate pairs to geometry value so we can insert it to db
	    	 */
	    	if(isset($request->sijainti)) {
	    		$keskipiste = MipGis::getPointGeometryValue($request->sijainti);
	    		$entity->keskipiste = $keskipiste;

	    		//Clear the aluerajaus
	    		$entity->aluerajaus = null;
	    	}
	    	if($request->alue) {
	    		$aluerajaus = MipGis::getAreaGeometryValue($request->alue);
	    		$entity->aluerajaus = $aluerajaus;

	    		//Clear the keskipiste
	    		$entity->keskipiste = null;
	    	}

	    	//No coordinates at all -> clear the existing
	    	if($request->alue == null && $request->sijainti == null) {
	    		$entity->aluerajaus = null;
	    		$entity->keskipiste = null;
	    	}

	    	$updated_field = Alue::UPDATED_BY;
	    	$when_field = Alue::UPDATED_AT;
	    	$entity->$updated_field = Auth::user()->id;
	    	$entity->$when_field = \Carbon\Carbon::now();

	    	$entity->save();

	    	AlueKyla::update_alue_kylat($entity->id, $request->input('kylat'));

	    	//Update the related alue inventointiprojekti
	    	if($request->inventointiprojekti) {
	    		InventointiprojektiAlue::insertInventointitieto($request->inventointiprojekti, $entity->id);
	    	}

	    	/*
	    	 * Jos pyynnössä tulee poistettavia InventointiprojektiKiinteisto-rivejä, merkitään rivit kannasta poistetuiksi
	    	 */
	    	if($request->inventointiprojektiDeleteList && count($request->inventointiprojektiDeleteList)>0){
	    	    foreach($request->inventointiprojektiDeleteList as $ip){
	    	        InventointiprojektiAlue::deleteInventointitieto($ip['inventointiprojektiId'], $entity->id, $ip['inventoijaId']);
	    	    }
	    	}

	    	// And commit the transaction as all went well
	    	DB::commit();

	    	MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
	    	MipJson::addMessage(Lang::get('alue.save_success'));
	    	MipJson::setResponseStatus(Response::HTTP_OK);


    	} catch(Exception $e) {
    		DB::rollback();
    		MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		MipJson::addMessage(Lang::get('alue.save_failed'));
    	}

    	return MipJson::getJson();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.alue.poisto')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$entity = Alue::find($id);
    	if(!$entity) {
    		// return: not found
    		MipJson::setGeoJsonFeature(null, null);
    		MipJson::addMessage(Lang::get('alue.search_not_found'));
    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    		return MipJson::getJson();
    	}

    	try {
    		DB::beginTransaction();
    		Utils::setDBUser();

	    	$author_field = Alue::DELETED_BY;
	    	$when_field = Alue::DELETED_AT;
	    	$entity->$author_field = Auth::user()->id;
	    	$entity->$when_field = \Carbon\Carbon::now();
	    	$entity->save();

	    	// NO DELETE NEEDED

    		DB::commit();
    		MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
    		MipJson::addMessage(Lang::get('alue.delete_success'));


   		} catch(Exception $e) {
   			DB::rollback();

   			MipJson::addMessage(Lang::get('alue.delete_failed'));
   			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
   		}

    	return MipJson::getJson();
    }

    public function listValueAreas(Request $request, $id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.alue.katselu') || !Kayttaja::hasPermission('rakennusinventointi.arvoalue.katselu')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	if(!is_numeric($id)) {
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {
    		try {

    			$validator = Validator::make($request->all(), [
    					'rivi'				=> 'numeric',
    					'rivit'				=> 'numeric',
    					'jarjestys'			=> 'string',
    					'jarjestys_suunta'	=> 'string',
    			]);

    			if ($validator->fails()) {
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    				foreach($validator->errors()->all() as $error)
    					MipJson::addMessage($error);
    				MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    			}
    			else {

    				/*
    				 * Initialize the variables that needs to be "reformatted"
    				 */
    				$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
    				$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
    				$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
    				$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

    				$area = Alue::find($id);

    				if($area) {
    					$entities = Arvoalue::getByAreaId($id)->orderBy('inventointinumero');
    					$total_rows = Utils::getCount($entities);
    					$entities = $entities->skip($rivi)->take($riveja)->get();

    					MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
    					MipJson::addMessage(Lang::get('arvoalue.search_success'));

    					foreach ($entities as $entity) {
    						/*
    						 * -> remove "sijainti" from props
    						 */
    						$properties = clone($entity);
    						unset($properties['sijainti']);
    						unset($properties['alue']);

    						if(!is_null($entity->sijainti)) {
    							MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($entity->sijainti), $properties);
    						} else {
    							MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($entity->alue), $properties);
    						}
    					}
    				} else {
    					MipJson::setGeoJsonFeature();
    					MipJson::addMessage(Lang::get('alue.search_not_found'));
    					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				}
    			}
    		} catch(QueryException $e) {
    			MipJson::addMessage(Lang::get('alue.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
    }

    public function listAreaImages(Request $request, $id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.alue.katselu')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	if(!is_numeric($id)) {
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {
    		try {

    			$validator = Validator::make($request->all(), [
    					'rivi'				=> 'numeric',
    					'rivit'				=> 'numeric',
    					'jarjestys'			=> 'string',
    					'jarjestys_suunta'	=> 'string',
    			]);

    			if ($validator->fails()) {
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    				foreach($validator->errors()->all() as $error)
    					MipJson::addMessage($error);
    				MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    			}
    			else {

    				/*
    				 * Initialize the variables that needs to be "reformatted"
    				 */
    				$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
    				$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
    				$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
    				$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

    				$area = Alue::find($id);

    				if($area) {

    					$entities = $area->images()->orderby('kuva.'.$jarjestys_kentta, $jarjestys_suunta)->get();
    					$total_rows = Utils::getCount($entities);

    					if($total_rows <= 0) {
    						MipJson::setGeoJsonFeature();
    						MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    						MipJson::addMessage(Lang::get('kuva.search_not_found'));
    					}
    					else {
    						MipJson::initGeoJsonFeatureCollection(intval($riveja), $total_rows);
    						MipJson::addMessage(Lang::get('kuva.search_success'));
    						$row_counter = 0;
    						foreach ($entities as $entity) {
								if($row_counter >= $rivi && $row_counter <= $row_counter+$riveja) {

									$images = Kuva::getImageUrls($entity->polku.$entity->nimi);
									$entity->url = $images->original;
									$entity->url_tiny = $images->tiny;
									$entity->url_small = $images->small;
									$entity->url_medium = $images->medium;
									$entity->url_large = $images->large;

    								MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
								}
								$row_counter++;
    						}
    					}
    				}
    				else {
    					MipJson::setGeoJsonFeature();
    					MipJson::addMessage(Lang::get('alue.search_not_found'));
    					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				}
    			}
    		} catch(QueryException $e) {
    			MipJson::addMessage(Lang::get('alue.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
    }

	public function updateAreaImages(Request $request, $id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.alue.muokkaus')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		if(!is_numeric($id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
		}
		else {
			try {
				MipJson::setGeoJsonFeature();

				/*
				 * Note:
				 * PATCH endpoint requires data in BODY of a request
				 * This endpoint expects data to be given in BODY of request in format:
				 * [{"op":"add","items":[1,2,3]},{"op":"remove","items":[6,7,8]}]
				 */
				$items = array("no data delivered!");
				if($request->getContent()) {
					$items = json_decode($request->getContent());

					foreach ($items as $item) {
						foreach ($item->items as $item_id) {
							if($item->op == "remove")
								Alue::find($id)->images()->detach($item_id);
							else if($item->op == "add") {
								if(Kuva::find($item_id)) {
									$exists = DB::select("SELECT * FROM kuva_alue WHERE kuva_id = ? AND alue_id = ?", [$item_id, $id]);
									if(count($exists) == 0) {
										Alue::find($id)->images()->attach($item_id);
									}
								}
							}
						}
					}

					MipJson::setGeoJsonFeature();
					MipJson::addMessage(Lang::get('kuva.attach_success'));
				}
				else {
					MipJson::setGeoJsonFeature();
					MipJson::addMessage(Lang::get('kuva.attach_failed'));
					MipJson::addMessage(Lang::get('kuva.patch_body_missing'));
					MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
				}

			} catch(QueryException $e) {
				MipJson::setGeoJsonFeature();
    			MipJson::addMessage(Lang::get('alue.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
	}

	public function historia($id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.alue.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		if(!is_numeric($id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}

		return AlueMuutoshistoria::getById($id);

	}
}
