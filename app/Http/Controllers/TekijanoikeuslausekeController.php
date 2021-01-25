<?php

namespace App\Http\Controllers;


use App\Tekijanoikeuslauseke;
use App\Utils;
use App\Library\String\MipJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class TekijanoikeuslausekeController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @version 1.0
	 * @since 1.0
	 * @param \Illuminate\Http\Request $request
	 * @return MipJson
	 */
	public function index(Request $request) {

		/*
		 * Role check
		 */

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
				$entities = Tekijanoikeuslauseke::getAll($jarjestys_kentta, $jarjestys_suunta);

				//Get the related info also
				$entities = $entities->with(array('luoja'));

				/*
				 * If ANY search terms are given limit results by them
				*/
				if($request->osio) {
					$entities->withOsio($request->osio);
				}

				// calculate the total rows of the search results
				$total_rows = Utils::getCount($entities);

				// Execute the query
				$entities = $entities->get()->toArray();

				/*
				 * Set the results into Json object
				 */
				MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
				MipJson::addMessage(Lang::get('valinta.found_count',["count" => count($entities)]));
				foreach ($entities as $entity)  {
					MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
				}

				MipJson::addMessage(Lang::get('valinta.search_success'));

			} catch(Exception $e) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('valinta.search_failed'));
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
	    if(Auth::user()->rooli != 'pääkäyttäjä' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
	        MipJson::setGeoJsonFeature();
	        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
	        MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
	        return MipJson::getJson();
	    }
		$validator = Validator::make($request->all(), [
			'lauseke'		=> 'required|string',
		    'otsikko'       => 'required|string'
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
			try {
				DB::beginTransaction();
				Utils::setDBUser();

				$entity = new Tekijanoikeuslauseke($request->all());

				$author_field = Tekijanoikeuslauseke::CREATED_BY;
				$entity->$author_field = Auth::user()->id;

				$entity->save();

				DB::commit();
			} catch(Exception $e) {
				DB::rollback();
				throw $e;
			}

			MipJson::addMessage(Lang::get('valinta.save_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

		} catch(Exception $e) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('tekijanoikeuslauseke.save_failed'));
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
	    if(Auth::user()->rooli != 'pääkäyttäjä' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
	        MipJson::setGeoJsonFeature();
	        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
	        MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
	        return MipJson::getJson();
	    }

		$validator = Validator::make($request->all(), [
			'lauseke'		=> 'required|string',
		    'otsikko'       => 'required|string'
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
			$entity = Tekijanoikeuslauseke::find($id);

			if(!$entity){
				//error, entity not found
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('valinta.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}

			try {
				DB::beginTransaction();
				Utils::setDBUser();

				$entity->fill($request->all());

				$author_field = Tekijanoikeuslauseke::UPDATED_BY;
				$entity->$author_field = Auth::user()->id;

				$entity->save();

				DB::commit();
			} catch(Exception $e) {
				DB::rollback();
				throw $e;
			}

			MipJson::addMessage(Lang::get('tekijanoikeuslauseke.save_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			MipJson::setResponseStatus(Response::HTTP_OK);

		} catch(Exception $e) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('kunta.save_failed'));
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
	    if(Auth::user()->rooli != 'pääkäyttäjä' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
	        MipJson::setGeoJsonFeature();
	        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
	        MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
	        return MipJson::getJson();
	    }


		$entity = Tekijanoikeuslauseke::find($id);

		if(!$entity) {
			// return: not found
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('valinta.search_not_found'));
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
			return MipJson::getJson();
		}

		try {
			DB::beginTransaction();
			Utils::setDBUser();
			$author_field = Tekijanoikeuslauseke::DELETED_BY;
			$when_field = Tekijanoikeuslauseke::DELETED_AT;
			$entity->$author_field = Auth::user()->id;
			$entity->$when_field = \Carbon\Carbon::now();
			$entity->save();

			DB::commit();

			MipJson::addMessage(Lang::get('valinta.delete_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

		} catch (Exception $e) {
			DB::rollback();

			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('valinta.delete_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		return MipJson::getJson();
	}
}
