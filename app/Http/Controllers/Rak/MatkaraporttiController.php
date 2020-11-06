<?php

namespace App\Http\Controllers\Rak;


use App\Kayttaja;
use App\Utils;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Rak\MatkaraportinSyy;
use App\Rak\Matkaraportti;
use App\Rak\MatkaraporttiMuutoshistoria;
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
class MatkaraporttiController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.matkaraportti.katselu')) {
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
   			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
   			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

   			/*
   			 * By default, initialize the query to get ALL entities
   			 */
   			$entities = Matkaraportti::getAll($jarjestys_kentta, $jarjestys_suunta);


   			//Get the related info also
   			$entities = $entities->with(array('kiinteisto',
   				'luoja',
   				'syyt'
   			));

   			if($request->etunimi) {
   				$entities->withUserFirstname($request->etunimi);
   			}
   			if($request->sukunimi) {
   				$entities->withUserLastname($request->sukunimi);
   			}

   			if($request->kiinteistotunnus) {
   				$entities->withKiinteistotunnus($request->kiinteistotunnus);
   			}

   			if($request->kiinteisto_id) {
   				$entities->withKiinteistoId($request->kiinteisto_id);
   			}

   			if($request->palstanumero) {
   			    $entities->withPalstanumero($request->palstanumero);
   			}

   			if($request->kiinteistoNimi) {
   				$entities->withKiinteistonimi($request->kiinteistoNimi);
   			}

   			if($request->syy) {
   				$entities->withSyyId($request->syy);
   			}

   			if($request->matkapvm_aloitus&& $request->matkapvm_aloitus != 'null') {
   				$entities->withMatkapvmAloitus($request->matkapvm_aloitus);
   			}

   			if($request->matkapvm_lopetus && $request->matkapvm_lopetus != 'null') {
   				$entities->withMatkapvmLopetus($request->matkapvm_lopetus);
   			}

   			if($request->luoja) {
   				$entities->withLuoja($request->luoja);
   			}


   			// calculate the total rows of the search results
   			$total_rows = Utils::getCount($entities);

   			// limit the results rows by given params
   			$entities->withLimit($rivi, $riveja);

   			// Execute the query
   			$entities = $entities->get()->toArray();

			MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
			foreach ($entities as $entity) {
    			MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
			}

			MipJson::addMessage(Lang::get('matkaraportti.found_count',["count" => count($entities)]));

    	} catch(Exception $e) {
    		MipJson::setGeoJsonFeature();
   			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
   			MipJson::addMessage(Lang::get('matkaraportti.search_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.matkaraportti.luonti')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'tehtavan_kuvaus'			=> 'required|string',
    			'huomautukset'				=> 'required|string',
    			'matkapvm' 					=> 'required|date',
    			'kiinteisto_id'				=> 'required|exists:kiinteisto,id',
    			'syyt'						=> 'required'

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

	    			$entity = new Matkaraportti($request->all());

	    			$author_field = Matkaraportti::CREATED_BY;
	    			$entity->$author_field = Auth::user()->id;

	    			$entity->save();

	    			/*
	    			 * Store the new syy values
	    			 */
	    			$syyt = $request->get ( 'syyt' );

	    			foreach ( $syyt as $s ) {

	    				$syy= MatkaraportinSyy::find($s['id']);

	    				$entity->syyt()->attach($syy, ['luoja' => Auth::user()->id]);
	    			}

	    			DB::commit();
    			} catch (Exception $e) {
    				DB::rollback();
    				throw $e;
    			}
    			MipJson::addMessage(Lang::get('matkaraportti.save_success'));
    			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

    		} catch(Exception $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('matkaraportti.save_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.matkaraportti.katselu')) {
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
    			$entity = Matkaraportti::getSingle($id)->with(array('luoja','muokkaaja', 'syyt', 'kiinteisto'))->first();

    			if(!isset($entity->id)) {
    				MipJson::setGeoJsonFeature();
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				MipJson::addMessage(Lang::get('matkaraportti.search_not_found'));
    			}
    			else {
    				MipJson::setGeoJsonFeature(null, $entity);
    				MipJson::addMessage(Lang::get('matkaraportti.search_success'));
    			}
    		}
    		catch(QueryException $e) {
    			MipJson::addMessage(Lang::get('matkaraportti.search_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.matkaraportti.muokkaus')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'tehtavan_kuvaus'			=> 'required|string',
    			'huomautukset'				=> 'required|string',
    			'matkapvm' 					=> 'required|date',
    			'kiinteisto_id'				=> 'required|exists:kiinteisto,id',
    			'syyt'						=> 'required'
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
    			$entity = Matkaraportti::find($id);
    			if(!$entity){
    				//error, entity not found
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('matkaraportti.search_not_found'));
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			}
    			else {
    				try {
    					DB::beginTransaction();
    					Utils::setDBUser();


    					/*
    					 * Detach the existing syy relations
    					 */

    					$existingSyyt = DB::table('matkaraportti_syy')->where('matkaraportti_id', $entity->id)->get();

    					foreach($existingSyyt as $es) {
    						DB::table('matkaraportti_syy')->where('id', '=', $es->id)->delete();
    					}
    					/*
    					 * Store the new syy values
    					 */
    					$syyt = $request->get ( 'syyt' );

    					foreach ( $syyt as $s ) {

    						$syy= MatkaraportinSyy::find($s['id']);

    						$entity->syyt()->attach($syy, ['luoja' => Auth::user()->id]);
    					}


	    				$entity->fill($request->all());
	    				$author_field = Matkaraportti::UPDATED_BY;
	    				$entity->$author_field = Auth::user()->id;
	    				$entity->update();

	    				DB::commit();
    				} catch (Exception $e) {
    					DB::rollback();
    					throw $e;
    				}

    				MipJson::addMessage(Lang::get('matkaraportti.save_success'));
    				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
    			}
    		} catch(Exception $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('matkaraportti.save_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.matkaraportti.poisto')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$entity = Matkaraportti::find($id);
    	if(!$entity) {
    		// return: not found
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('matkaraportti.search_not_found'));
    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    		return MipJson::getJson();
    	}

   		try {
   			DB::beginTransaction();
   			Utils::setDBUser();

    		$author_field = Matkaraportti::DELETED_BY;
    		$when_field = Matkaraportti::DELETED_AT;
    		$entity->$author_field = Auth::user()->id;
    		$entity->$when_field = \Carbon\Carbon::now();
    		$entity->save();

   			DB::commit();

   			MipJson::addMessage(Lang::get('matkaraportti.delete_success'));
   			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
   		} catch(Exception $e) {
   			DB::rollback();

   			MipJson::setGeoJsonFeature();
   			MipJson::addMessage(Lang::get('matkaraportti.delete_failed'));
   			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
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

    	return MatkaraporttiMuutoshistoria::getById($id);

    }

}
