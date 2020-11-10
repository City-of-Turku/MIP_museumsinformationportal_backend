<?php

namespace App\Http\Controllers\Rak;


use App\Kayttaja;
use App\Utils;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Rak\Suunnittelija;
use App\Rak\SuunnittelijaMuutoshistoria;
use App\Rak\SuunnittelijaTyyppi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * Class level doc block is here!
 *
 * @author
 * @version 1.0
 * @since
 */
class SuunnittelijaController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.suunnittelija.katselu')) {
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
    			'etunimi'			=> 'string',
    			'sukunimi'			=> 'string',
    			'ammatti_arvo'		=> 'string',
    			'id'				=> 'numeric',
    	]);

    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		foreach($validator->errors()->all() as $error) {
    			MipJson::addMessage($error);
    		}
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    		return MipJson::getJson();
    	}

   		try {

   			/*
   			 * Initialize the variables that needs to be "reformatted"
   			 */
   			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
   			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
   			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
   			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

   			/*
   			 * By default, initialize the query to get ALL entities
   			 */
   			$entities = Suunnittelija::getAll();

   			/*
   			 * If ANY search terms are given limit results by them
   			 */
   			if ($request->tarkka && $request->nimi) {
   				$nimi = explode(" ", $request->nimi);
   				$sukunimi = null;
   				$etunimi = null;

   				if(sizeof($nimi) == 2) {
   					$sukunimi = $nimi[0];
   					$etunimi = $nimi[1];
   				} else {
   					$sukunimi = $nimi[0];
   				}

   				if($etunimi) {
	   				//Hae samalla sukunimellä, (esim aalto)
   					$entities->where( 'suunnittelija.sukunimi', 'ILIKE', $sukunimi );

   					//Jos etunimi on annettu, etsi sukunimi ja etunimi yhdistelmää, (esim aalto viljo)
   					$entities->where('suunnittelija.etunimi', 'ILIKE', $etunimi);

   					//tai nimet väärinpäin, (esim viljo aalto)
   					$entities->orWhere('suunnittelija.sukunimi', 'ILIKE', $etunimi)->where('suunnittelija.etunimi', 'ILIKE', $sukunimi);
   				}

   				//etsi koko nimellä sukunimestä  (esim Hämeen Maatalousseura)
   				$entities->orWhere('suunnittelija.sukunimi', 'ILIKE', $request->nimi);

   			}

   			//Get the related info also
   			$entities = $entities->with(array('ammattiarvo', 'luoja'));

   			/*
   			 * If ANY search terms are given limit results by them
   			 */
   			if($request->etunimi) {
   				$entities->withFirstName($request->etunimi);
   			}
   			if($request->sukunimi) {
   				$entities->withLastName($request->sukunimi);
   			}
   			if($request->nimi) {
   				$entities->withName($request->nimi);
   			}
   			if($request->ammattiarvo) {
   				$entities->withProfessionId($request->ammattiarvo);
   			}
   			if($request->suunnittelija_laji) {
   			    $entities->withLajiId($request->suunnittelija_laji);
   			}
   			if($request->id) {
   				$entities->withID($request->id);
   			}
   			if($request->luoja) {
   				$entities->withLuoja($request->luoja);
   			}

   			// calculate the total rows of the search results
   			$total_rows = Utils::getCount($entities);

   			$entities->withOrderBy($jarjestys_kentta, $jarjestys_suunta);

   			// limit the results rows by given params
   			$entities->withLimit($rivi, $riveja);

   			// Execute the query
   			$entities = $entities->get()->toArray();

			MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
			foreach ($entities as $entity) {
    			MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
			}

			MipJson::addMessage(Lang::get('suunnittelija.found_count',["count" => count($entities)]));

    	} catch(Exception $e) {
    		MipJson::setGeoJsonFeature();
   			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
   			MipJson::addMessage(Lang::get('suunnittelija.search_failed'));
   		}

    	return MipJson::getJson();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.suunnittelija.luonti')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'etunimi'				=> 'string',
    			'sukunimi'				=> 'required|string',
    			'kuvaus'				=> 'string',
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

    			try {
    				DB::beginTransaction();
    				Utils::setDBUser();

	    			$entity = new Suunnittelija($request->all());

	    			$author_field = Suunnittelija::CREATED_BY;
	    			$entity->$author_field = Auth::user()->id;

	    			$entity->save();

	    			DB::commit();
    			} catch (Exception $e) {
    				DB::rollback();
    				throw $e;
    			}
    			MipJson::addMessage(Lang::get('suunnittelija.save_success'));
    			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

    		} catch(Exception $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('suunnittelija.save_failed'));
    		}
    	}
    	return MipJson::getJson();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.suunnittelija.katselu')) {
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
    			$entity = Suunnittelija::getSingle($id)->with(array('luoja','muokkaaja', 'ammattiarvo'))->first();

    			if(!isset($entity->id)) {
    				MipJson::setGeoJsonFeature();
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				MipJson::addMessage(Lang::get('suunnittelija.search_not_found'));
    			}
    			else {
    				MipJson::setGeoJsonFeature(null, $entity);
    				MipJson::addMessage(Lang::get('suunnittelija.search_success'));
    			}
    		}
    		catch(QueryException $e) {
    			MipJson::addMessage(Lang::get('suunnittelija.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.suunnittelija.muokkaus')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'etunimi'				=> 'string',
    			'sukunimi'				=> 'required|string',
    			'kuvaus'				=> 'string',
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
    			$entity = Suunnittelija::find($id);
    			if(!$entity){
    				//error, entity not found
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('suunnittelija.search_not_found'));
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			}
    			else {
    				try {
    					DB::beginTransaction();
    					Utils::setDBUser();

	    				$entity->fill($request->all());
	    				$author_field = Suunnittelija::UPDATED_BY;
	    				$entity->$author_field = Auth::user()->id;
	    				$entity->update();

	    				DB::commit();
    				} catch (Exception $e) {
    					DB::rollback();
    					throw $e;
    				}

    				MipJson::addMessage(Lang::get('suunnittelija.save_success'));
    				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
    			}
    		} catch(Exception $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('suunnittelija.save_failed'));
    		}
    	}
    	return MipJson::getJson();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.suunnittelija.poisto')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$entity = Suunnittelija::find($id);
    	if(!$entity) {
    		// return: not found
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('suunnittelija.search_not_found'));
    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    		return MipJson::getJson();
    	}

   		try {
   			DB::beginTransaction();
   			Utils::setDBUser();

    		$author_field = Suunnittelija::DELETED_BY;
    		$when_field = Suunnittelija::DELETED_AT;
    		$entity->$author_field = Auth::user()->id;
    		$entity->$when_field = \Carbon\Carbon::now();
    		$entity->save();

   			DB::commit();

   			MipJson::addMessage(Lang::get('suunnittelija.delete_success'));
   			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
   		} catch(Exception $e) {
   			DB::rollback();

   			MipJson::setGeoJsonFeature();
   			MipJson::addMessage(Lang::get('suunnittelija.delete_failed'));
   			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
   		}

    	return MipJson::getJson();
    }

    public function rakennukset($suunnittelija_id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.katselu')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	if(!is_numeric($suunnittelija_id)) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {
    		try {
    			$suunnittelija = Suunnittelija::find($suunnittelija_id);

    			if($suunnittelija) {

    				$buildings = $suunnittelija->rakennukset()->with('kiinteisto')->get();

    				// calculate the total rows of the search results
    				$total_rows = count($buildings);

    				if($total_rows > 0) {
    					MipJson::initGeoJsonFeatureCollection(count($buildings), $total_rows);
    					MipJson::addMessage(Lang::get('rakennus.search_success'));

    					foreach ($buildings as $building) {
    						/*
    						 * Asetetaan rakennustyypit manuaalisesti
    						 */
    						//Haetaan rakennustyypit jotka rakennukselle kuuluvat
    						$rakennus_rakennustyypit = DB::table('rakennus_rakennustyyppi')->where('rakennus_id', '=', $building->rakennus_id)->get();

    						//Rakennustyypit lista johon rakennukseen kuuluvat rakennustyypit lisätään
    						$rakennustyypit = [];

    						//Haetaan välitaulun rakennustyyppi_id:n mukainen rakennustyyppi rivi ja lisätään se rakennustyypit listaan
    						foreach($rakennus_rakennustyypit as $rrt) {
    							$rt = DB::table('rakennustyyppi')->where('id', '=', $rrt->rakennustyyppi_id)->get();
    							array_push($rakennustyypit, $rt[0]);
    						}
    						//Asetetaan rakennustyypit lista rakennuksen propertyksi
    						$building->rakennustyypit = $rakennustyypit;


    						$suunnittelijatyyppi = SuunnittelijaTyyppi::getSingle($building->suunnittelija_tyyppi_id)->first();
    						$building->suunnittelijatyyppi = $suunnittelijatyyppi;
    						/*
    						 * clone $kiinteisto so we can handle the "properties" separately
    						 * -> remove "sijainti" from props
    						 */
    						$properties = clone($building);
    						unset($properties['sijainti']);
    						MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($building->sijainti), $properties);
    					}
    				}
    				if(count($buildings) <= 0) {
    					MipJson::setGeoJsonFeature();
    					//MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    					MipJson::addMessage(Lang::get('rakennus.search_not_found'));
    				}
    			}
    			else {
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('rakennus.search_not_found'));
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			}
    		}
    		catch(QueryException $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::addMessage(Lang::get('rakennus.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
    }

    public function historia($id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.muutoshistoria.katselu')) {
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

    	return SuunnittelijaMuutoshistoria::getById($id);

    }

}
