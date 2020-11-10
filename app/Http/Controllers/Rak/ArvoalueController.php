<?php

namespace App\Http\Controllers\Rak;

use App\Kayttaja;
use App\Utils;
use App\Http\Controllers\Controller;
use App\Library\Gis\MipGis;
use App\Library\String\MipJson;
use App\Rak\Aluetyyppi;
use App\Rak\Arvoalue;
use App\Rak\ArvoalueKyla;
use App\Rak\ArvoalueMuutoshistoria;
use App\Rak\ArvoalueSuojelutyyppi;
use App\Rak\ArvoalueenKulttuurihistoriallinenArvo;
use App\Rak\Arvotustyyppi;
use App\Rak\InventointiprojektiArvoalue;
use App\Rak\Kiinteisto;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class ArvoalueController extends Controller {


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.arvoalue.katselu')) {
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
    		'kunta'				=> 'string',
    		'kuntanumero'		=> 'numeric',
    		'kyla'				=> 'string',
    		'kylanumero'		=> 'numeric',
    		'alue_nimi'			=> 'string',
    		'nimi'				=> 'string',
    		'aluetyyppi'		=> 'string',
    		'id'				=> "numeric|exists:arvoalue,id",
    		'inventointinumero' => 'numeric',
    		//"aluerajaus"		=> "string|regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/",
    	]);

    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature();
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

    			$entities = Arvoalue::getAll();

    			/*
    			 * If ANY search terms are given limit results by them
    			*/
    			if($request->input('id')) {
    				$entities->withID($request->input('id'));
    			}
    			if($request->input('paikkakunta')) {
    			    $entities->withPaikkakunta($request->input('paikkakunta'));
    			}
    			if($request->input('nimi')) {
    				$entities->withName($request->input('nimi'));
    			}
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
    			if($request->input('aluetyyppi')) {
    				$entities->withAreatypeId($request->input('aluetyyppi'));
    			}
    			if($request->input('arvotus')) {
    				$entities->withArvotustyyppiId($request->input('arvotus'));
    			}
    			if($request->input('alue_nimi')) {
    				$entities->withAreaName($request->input('alue_nimi'));
    			}
    			if($request->input('kyla')) {
    				$entities->withVillageName($request->input('kyla'));
    			}
    			if($request->input('kylaId')) {
    			    $entities->withVillageId($request->input('kylaId'));
    			}
    			if($request->input('kylanumero')) {
    				$entities->withVillageNumber($request->input('kylanumero'));
    			}
    			if($request->input('kunta')) {
    				$entities->withMunicipalityName($request->input('kunta'));
    			}
    			if($request->input('kuntaId')) {
    			    $entities->withMunicipalityId($request->input('kuntaId'));
    			}
    			if($request->input('kuntanumero')) {
    				$entities->withMunicipalityNumber($request->input('kuntanumero'));
    			}
    			if($request->input('alue_id')) {
    				$entities->withAreaId($request->input('alue_id'));
    			}

    			if($request->luoja) {
    				$entities->withLuoja($request->luoja);
    			}

    			$entities = $entities->with( array(
    				'aluetyyppi',
    				'arvotustyyppi',
    				'_alue',
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

	    		MipJson::addMessage(Lang::get('arvoalue.search_success'));

    		} catch(Exception $e) {
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('arvoalue.search_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.arvoalue.luonti')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'alue_id'			=> 'required|numeric|exists:alue,id',
    			'nimi'				=> 'nullable|string|min:1',
    			'arvotustyyppi.id'	=> 'numeric|exists:arvotustyyppi,id',
    			'kuvaus'			=> 'string',
    			'aluetyyppi.id'		=> 'required|numeric|exists:aluetyyppi,id',
    			'tarkistettu'		=> 'string',
    			'sijainti'			=> "nullable|regex:/^\d+\.\d+\ \d+\.\d+$/", // 20.123456 60.123456 (lat, lon)
    			'inventointinumero' => 'required|numeric',
    			//'kylat' => array...
		    	//'alue'			=> "required|regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/", // 20.123456 60.123456,20.123456 60.123456,20.123456 60.123456,20.123456 60.123456 (lat lon, lat lon, lat lon, lat lon = minimum of 4 pairs)
    	]);

    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature();
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
    			//$validation = MipGis::validateAreaCoordinates($request->alue);
    			//if( $validation !== true)
    			//	return $validation;

    			// wrap the operations into a transaction
    			DB::beginTransaction();
    			Utils::setDBUser();

    			try {
	    			$entity = new Arvoalue($request->all());

	    			if (isset($request->aluetyyppi)) {
		    			$aluetyyppi = new Aluetyyppi($request->input('aluetyyppi'));
		    			$entity->aluetyyppi_id = $aluetyyppi->id;
	    			} else {
	    				$entity->aluetyyppi_id = null;
	    			}

	    			if (isset($request->arvotustyyppi)) {
		    			$arvotustyyppi = new Arvotustyyppi($request->input('arvotustyyppi'));
		    			$entity->arvotustyyppi_id = $arvotustyyppi->id;
	    			} else {
	    				$entity->arvotustyyppi_id = null;
	    			}

		    		/*
		    		 * convert coordinate pairs to geometry value so we can insert it to db
		    		 */
	    			if(isset($request->sijainti)) {
		    			$keskipiste = MipGis::getPointGeometryValue($request->sijainti);
		    			$entity->keskipiste = $keskipiste;
	    			}
	    			if($request->alue) {
		    			$aluerajaus = MipGis::getAreaGeometryValue($request->alue);
		    			$entity->aluerajaus = $aluerajaus;
	    			}

	    			$author_field = Arvoalue::CREATED_BY;
	    			$entity->$author_field = Auth::user()->id;

		    		$entity->save();

		    		ArvoalueenKulttuurihistoriallinenArvo::update_arvoalue_kulttuurihistoriallisetarvot($entity->id, $request->input('kulttuurihistoriallisetarvot'));
		    		ArvoalueSuojelutyyppi::update_arvoalue_suojelutyypit($entity->id, $request->input('suojelutiedot'));
		    		ArvoalueKyla::update_arvoalue_kylat($entity->id, $request->input('kylat'));

		    		//Update the related arvoalue inventointiprojekti
		    		if($request->inventointiprojekti) {
		    			InventointiprojektiArvoalue::insertInventointitieto($request->inventointiprojekti, $entity->id);
		    		}
	    		} catch(Exception $e) {
	    			// Exception, always rollback the transaction
	    			DB::rollback();
	    			throw $e;
	    		}

	    		// And commit the transaction as all went well
	    		DB::commit();

	    		MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
	    		MipJson::addMessage(Lang::get('arvoalue.save_success'));

    		} catch(Exception $e) {
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('arvoalue.save_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.arvoalue.katselu')) {
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
    			$entity = Arvoalue::getSingle($id)->with(
    					array(  'arvotustyyppi',
    							'kulttuurihistoriallisetarvot',
    							'aluetyyppi',
    							'luoja',
    							'muokkaaja',
    							'_alue',
    							'kylat',
    							'kylat.kunta',
    							'suojelutiedot',
								'suojelutiedot.suojelutyyppi',
								'suojelutiedot.suojelutyyppi.suojelutyyppiryhma',
    							'inventointiprojektit'
    					)
    			)->first();

    			if($entity) {
    				/*
    				 * Haetaan jokaiselle inventointiprojektille VIIMEKSI LISÄTTY inventointitieto per inventoija.
    				 * Tehdään tässä, koska ei onnistunut näppärästi query builderilla muualla.
    				 */
    				foreach($entity->inventointiprojektit as $ip) {
    					$ip->inventoijat = Arvoalue::inventoijat($entity->id, $ip->id);
    				}

    				$properties = clone($entity);
    				unset($properties['sijainti']);
    				unset($properties['alue']);

    				if(!is_null($entity->sijainti)) {
    					MipJson::setGeoJsonFeature(json_decode($entity->sijainti), $properties);
    				} else if(!is_null($entity->alue)) {
    					MipJson::setGeoJsonFeature(json_decode($entity->alue), $properties);
    				} else {
    					MipJson::setGeoJsonFeature(null, $properties);
    				}

    				MipJson::addMessage(Lang::get('arvoalue.search_success'));
    			}
    			else {
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				MipJson::addMessage(Lang::get('arvoalue.search_not_found'));
    			}
    		}
    		catch(QueryException $e) {
    			MipJson::addMessage(Lang::get('arvoalue.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
    }

    /**
     * Arvoalueiden haku koriin
     */
    public function kori(Request $request) {

        if(!$request->kori_id_lista){
            MipJson::addMessage(Lang::get('arvoalue.search_failed'));
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

            $arvoalueet = Arvoalue::getAll();

            $arvoalueet = $arvoalueet->with( array(
                'aluetyyppi',
                'arvotustyyppi',
                '_alue',
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
            $arvoalueet->withArvoalueIdLista($idLista);

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($arvoalueet);

            // ID:t listaan ennen rivien rajoitusta
            $kori_id_lista = Utils::getIdLista($arvoalueet);

            // Rivimäärien rajoitus parametrien mukaan
            $arvoalueet->withLimit($rivi, $riveja);

            // Sorttaus
            $arvoalueet->withOrderBy($request->aluerajaus, $jarjestys_kentta, $jarjestys_suunta);

            // suorita query
            $arvoalueet = $arvoalueet->get();

            MipJson::initGeoJsonFeatureCollection(count($arvoalueet), $total_rows);

            foreach ($arvoalueet as $arvoalue) {
                //Tarkastetaan jokaiselle arvoalueelle kuuluvat oikeudet
                $arvoalue->oikeudet = Kayttaja::getPermissionsByEntity(null, 'arvoalue', $arvoalue->id);
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $arvoalue);
            }

            /*
             * Koritoiminnallisuus. Palautetaan id:t listana.
             */
            MipJson::setIdList($kori_id_lista);

            MipJson::addMessage(Lang::get('arvoalue.search_success'));
        }
        catch(QueryException $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('arvoalue.search_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.arvoalue.muokkaus')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'nimi'				=> 'nullable|string|min:1',
    			'maisema'			=> 'string',
    			'historia'			=> 'string',
    			'nykytila'			=> 'string',
    			'lisatiedot'		=> 'string',
    			'lahteet'			=> 'string',
    			'arvotustyyppi.id'	=> 'numeric|exists:arvotustyyppi,id',
    			'aluetyyppi.id'		=> 'required|numeric|exists:aluetyyppi,id',
    			'sijainti'			=> "nullable|regex:/^\d+\.\d+\ \d+\.\d+$/", // 20.123456 60.123456 (lat, lon)
    			'inventointinumero' => 'required|numeric',
    			//'alue'				=> "required|regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/", // 20.123456 60.123456,20.123456 60.123456,20.123456 60.123456,20.123456 60.123456 (lat lon, lat lon, lat lon, lat lon = minimum of 4 pairs)
    	]);

    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature();
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
    			//$validation = MipGis::validateAreaCoordinates($request->alue);
    			//if( $validation !== true)
    			//	return $validation;

    			$entity = Arvoalue::find($id);

    			if(!$entity){
    				//error, entity not found
    				MipJson::addMessage(Lang::get('arvoalue.search_not_found'));
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			}
    			else {

    				// wrap the operations into a transaction
    				DB::beginTransaction();
    				Utils::setDBUser();

    				try {

	    				$entity->fill($request->all());

	    				if (isset($request->aluetyyppi)) {
	    					$aluetyyppi = new Aluetyyppi($request->input('aluetyyppi'));
	    					$entity->aluetyyppi_id = $aluetyyppi->id;
	    				} else {
	    					$entity->aluetyyppi_id = null;
	    				}

	    				if (isset($request->arvotustyyppi)) {
	    					$arvotustyyppi = new Arvotustyyppi($request->input('arvotustyyppi'));
	    					$entity->arvotustyyppi_id = $arvotustyyppi->id;
	    				} else {
	    					$entity->arvotustyyppi_id = null;
	    				}

	    				/*
	    				 * convert coordinate pairs to geometry value so we can insert it to db
	    				*/
	    				if(isset($request->sijainti)) {
	    					$keskipiste = MipGis::getPointGeometryValue($request->sijainti);
	    					$entity->keskipiste = $keskipiste;
	    					$entity->aluerajaus = null;
	    				}
	    				if(isset($request->alue)) {
		    				$aluerajaus = MipGis::getAreaGeometryValue($request->alue);
		    				$entity->aluerajaus = $aluerajaus;
		    				$entity->keskipiste = null;
	    				}

	    				$author_field = Arvoalue::UPDATED_BY;
	    				$entity->$author_field = Auth::user()->id;

	    				$entity->save();

	    				ArvoalueenKulttuurihistoriallinenArvo::update_arvoalue_kulttuurihistoriallisetarvot($entity->id, $request->input('kulttuurihistoriallisetarvot'));
	    				ArvoalueSuojelutyyppi::update_arvoalue_suojelutyypit($entity->id, $request->input('suojelutiedot'));
	    				ArvoalueKyla::update_arvoalue_kylat($entity->id, $request->input('kylat'));

	    				//Update the related arvoalue inventointiprojekti
	    				if($request->inventointiprojekti) {
	    					InventointiprojektiArvoalue::insertInventointitieto($request->inventointiprojekti, $entity->id);
	    				}
	    				/*
	    				 * Jos pyynnössä tulee poistettavia InventointiprojektiKiinteisto-rivejä, merkitään rivit kannasta poistetuiksi
	    				 */
	    				if($request->inventointiprojektiDeleteList && count($request->inventointiprojektiDeleteList)>0){
	    				    foreach($request->inventointiprojektiDeleteList as $ip){
	    				        InventointiprojektiArvoalue::deleteInventointitieto($ip['inventointiprojektiId'], $entity->id, $ip['inventoijaId']);
	    				    }
	    				}

    				} catch(Exception $e) {
    					// Exception, always rollback the transaction
    					DB::rollback();
    					throw $e;
    				}

    				// And commit the transaction as all went well
    				DB::commit();

    				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
    				MipJson::addMessage(Lang::get('alue.save_success'));
    				//MipJson::setData(array("id" => $entity->id));
    				MipJson::setResponseStatus(Response::HTTP_OK);
    			}

    		} catch(Exception $e) {
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('arvoalue.save_failed'));
    		}
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.arvoalue.poisto')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$entity = Arvoalue::find($id);
    	if(!$entity) {
    		// return: not found
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('arvoalue.search_not_found'));
    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    		return MipJson::getJson();
    	}
    	try {
	    	DB::beginTransaction();
	    	Utils::setDBUser();

	    	$author_field = Arvoalue::DELETED_BY;
	    	$when_field = Arvoalue::DELETED_AT;
	    	$entity->$author_field = Auth::user()->id;
	    	$entity->$when_field = \Carbon\Carbon::now();

	    	$entity->save();

    		DB::commit();

    		MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
    		MipJson::addMessage(Lang::get('arvoalue.delete_success'));

   		} catch (Exception $e) {
   			DB::rollback();

   			MipJson::setGeoJsonFeature();
   			MipJson::addMessage(Lang::get('arvoalue.delete_failed'));
   			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
   		}

    	return MipJson::getJson();
    }


    /**
     * Method to get all estates within an valuearea
     *
     * @param int $id the id of the arvoalue
     * @return MipJson
     * @version 1.0
     * @since 1.0
     */
    public function showEstatesWithin($id) {
    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.arvoalue.katselu')) {
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
    			$arvoalue = Arvoalue::getSingle($id)->first();

    			if($arvoalue) {
    				$estates = Kiinteisto::getWithinArvoalue($id); // no ->get() here because raw select

    				MipJson::initGeoJsonFeatureCollection(count($estates), count($estates));
    				foreach($estates as $estate) {

			    		$properties = clone($estate);
			    		unset($properties['sijainti']);

			    		MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($estate->sijainti), $properties);
    				}
    				MipJson::addMessage(Lang::get('arvoalue.search_success'));
    			}
    			else {
    				MipJson::setGeoJsonFeature();
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				MipJson::addMessage(Lang::get('arvoalue.search_not_found'));
    			}
    		}
    		catch(QueryException $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::addMessage(Lang::get('arvoalue.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
    }

    public function historia($id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.arvoalue.katselu')) {
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

    	return ArvoalueMuutoshistoria::getById($id);

    }
}
