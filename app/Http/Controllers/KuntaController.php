<?php

namespace App\Http\Controllers;


use \Illuminate\Support\Facades\Validator;
use App\Kayttaja;
use App\Kunta;
use App\Rak\Alue;
use App\Rak\Arvoalue;
use App\Rak\Rakennus;
use App\Library\String\MipJson;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Utils;
use App\KuntaMuutoshistoria;

class KuntaController extends Controller {

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
		if(!Kayttaja::hasPermission('rakennusinventointi.kunta.katselu')) {
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
			'kuntanumero'		=> 'numeric',
			'nimi'				=> 'string',
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
				$entities = Kunta::getAll($jarjestys_kentta, $jarjestys_suunta);

				//Get the related info also
				$entities = $entities->with(array('luoja'));

				/*
				 * If ANY search terms are given limit results by them
				*/
				if($request->kuntanumero) {
					$entities->withMunicipalityNumber($request->kuntanumero);
				}
				if($request->nimi) {
					$entities->withMunicipalityName($request->nimi);
				}
				if($request->luoja) {
					$entities->withLuoja($request->luoja);
				}
				if($request->kuntaId) {
				    $entities->withId($request->kuntaId);
				}
				// calculate the total rows of the search results
				$total_rows = Utils::getCount($entities);

				// limit the results rows by given params
				$entities->withLimit($rivi, $riveja);

				// Execute the query
				$entities = $entities->get()->toArray();

				/*
				 * Set the results into Json object
				 */
				MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
				MipJson::addMessage(Lang::get('kunta.found_count',["count" => count($entities)]));
				foreach ($entities as $entity)  {
					MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
				}

				MipJson::addMessage(Lang::get('kunta.search_success'));

			} catch(Exception $e) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('kunta.search_failed'));
			}
		}
		return MipJson::getJson();
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return MipJson
	 */
	public function show($id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kunta.katselu')) {
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
				$entity = Kunta::getSingle($id)->with('luoja')->with('muokkaaja')->first();

				if(!isset($entity->id)) {
					MipJson::setGeoJsonFeature();
		    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
		    		MipJson::addMessage(Lang::get('kunta.search_not_found'));
		    	}
		    	else {
		    		MipJson::setGeoJsonFeature(null, $entity);
		    		MipJson::addMessage(Lang::get('kunta.search_success'));
		    	}
			}
			catch(QueryException $e) {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('kunta.search_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
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
		if(!Kayttaja::hasPermission('rakennusinventointi.kunta.luonti')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$validator = Validator::make($request->all(), [
			'kuntanumero'		=> 'required|numeric|digits:3',
			'nimi'				=> 'required|string|min:2',
			'nimi_se'			=> 'string|min:2',
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

					$entity = new Kunta($request->all());

					$author_field = Kunta::CREATED_BY;
					$entity->$author_field = Auth::user()->id;

					$entity->save();

					DB::commit();
				} catch(Exception $e) {
					DB::rollback();
					throw $e;
				}

				MipJson::addMessage(Lang::get('kunta.save_success'));
				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

			} catch(Exception $e) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('kunta.save_failed'));
			}
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
		if(!Kayttaja::hasPermission('rakennusinventointi.kunta.muokkaus')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$validator = Validator::make($request->all(), [
			'kuntanumero'		=> 'required|numeric|digits:3',
			'nimi'				=> 'required|string|min:2',
			'nimi_se'			=> 'string|min:2',
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
				$entity = Kunta::find($id);

				if(!$entity){
					//error, entity not found
					MipJson::setGeoJsonFeature();
					MipJson::addMessage(Lang::get('kunta.search_not_found'));
					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				}
				else {
					try {
						DB::beginTransaction();
						Utils::setDBUser();

						$entity->fill($request->all());

						$author_field = Kunta::UPDATED_BY;
						$entity->$author_field = Auth::user()->id;

						$entity->save();

						DB::commit();
					} catch(Exception $e) {
						DB::rollback();
						throw $e;
					}

					MipJson::addMessage(Lang::get('kunta.save_success'));
					MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
					MipJson::setResponseStatus(Response::HTTP_OK);
				}

			} catch(Exception $e) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('kunta.save_failed'));
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
		if(!Kayttaja::hasPermission('rakennusinventointi.kunta.poisto')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$entity = Kunta::find($id);
		if(!$entity) {
			// return: not found
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('kunta.search_not_found'));
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
			return MipJson::getJson();
		}

		try {
			DB::beginTransaction();
			Utils::setDBUser();

			$author_field = Kunta::DELETED_BY;
			$when_field = Kunta::DELETED_AT;
			$entity->$author_field = Auth::user()->id;
			$entity->$when_field = \Carbon\Carbon::now();
			$entity->save();

			DB::commit();

			MipJson::addMessage(Lang::get('kunta.delete_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

		} catch (Exception $e) {
			DB::rollback();

			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('kunta.delete_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		return MipJson::getJson();
	}

	/**
	 * get the list of areas that this municipality belongs to
	 *
	 * @param Request $request
	 * @param int $id
	 * @return <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function areas(Request $request, $id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kunta.katselu')) {
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


					$municipality = Kunta::find($id);

					if($municipality) {
						$entities = Alue::getAll();
						$entities->with(array('kylat'));
						$entities->withMunicipalityId($municipality->id)->orderBy($jarjestys_kentta, $jarjestys_suunta);
						$entities->withOrderBy(null, $jarjestys_kentta, $jarjestys_suunta);
						$total_rows = Utils::getCount($entities);
						$entities = $entities->skip($rivi)->take($riveja)->get();

						if(count($entities) <= 0) {
							MipJson::setResponseStatus(Response::HTTP_OK);
							MipJson::addMessage(Lang::get('alue.search_not_found'));
						}
						else  {
							MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
							MipJson::addMessage(Lang::get('alue.found_count',["count" => count($entities)]));
							foreach ($entities as $entity)
								MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
						}
					}
					else {
						MipJson::setGeoJsonFeature();
						MipJson::addMessage(Lang::get('kunta.search_not_found'));
						MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					}
				}
			}
			catch(QueryException $e) {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('kyla.search_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
		return MipJson::getJson();
	}

	/**
	 * get the list of valueareas that this municipality belongs to
	 *
	 * @param Request $request
	 * @param int $id
	 * @return <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function valueareas(Request $request, $id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kunta.katselu')) {
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


					$municipality = Kunta::find($id);

					if($municipality) {
						$entities = Arvoalue::getAll();
						$entities->with(array('kylat'));
						$entities->withMunicipalityId($municipality->id)->orderBy($jarjestys_kentta, $jarjestys_suunta);
						$entities->withOrderBy(null, $jarjestys_kentta, $jarjestys_suunta);

						$total_rows = Utils::getCount($entities);
						$entities = $entities->skip($rivi)->take($riveja)->get();

						if(count($entities) <= 0) {
							MipJson::setResponseStatus(Response::HTTP_OK);
							MipJson::addMessage(Lang::get('arvoalue.search_not_found'));
						}
						else  {
							MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
							MipJson::addMessage(Lang::get('arvoalue.found_count',["count" => count($entities)]));
							foreach ($entities as $entity)
								MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
						}
					}
					else {
						MipJson::setGeoJsonFeature();
						MipJson::addMessage(Lang::get('kunta.search_not_found'));
						MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					}
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

	/**
	 * Get the list of buildings of this municipality
	 *
	 * @param Request $request
	 * @param int $id
	 * @return <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function buildings(Request $request, $id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kunta.katselu')) {
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

					$municipality = Kunta::find($id);

					if($municipality) {

						//$entities = Kunta::buildings($municipality->id)->orderBy($jarjestys_kentta, $jarjestys_suunta);
						$rakennukset = Rakennus::getAll($jarjestys_kentta, $jarjestys_suunta);
						$rakennukset->with(array('kiinteisto', 'rakennustyypit'));
						$rakennukset->withKuntaId($municipality->id)->orderBy($jarjestys_kentta, $jarjestys_suunta);

						$total_rows = Utils::getCount($rakennukset);
						$rakennukset = $rakennukset->skip($rivi)->take($riveja)->get();

						if(count($rakennukset) <= 0) {
							MipJson::setResponseStatus(Response::HTTP_OK);
							MipJson::addMessage(Lang::get('rakennus.search_not_found'));
						}
						else  {
							MipJson::initGeoJsonFeatureCollection(count($rakennukset), $total_rows);
							MipJson::addMessage(Lang::get('rakennus.found_count',["count" => count($rakennukset)]));
							foreach ($rakennukset as $entity)
								MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
						}
					}
					else {
						MipJson::setGeoJsonFeature();
						MipJson::addMessage(Lang::get('kunta.search_not_found'));
						MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					}
				}
			}
			catch(QueryException $e) {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('kyla.search_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
		return MipJson::getJson();
	}

	/**
	 * Get the list of estates of this municipality
	 *
	 * @param Request $request
	 * @param int $id
	 * @return <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function estates(Request $request, $id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kunta.katselu')) {
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

					$municipality = Kunta::find($id);

					if($municipality) {

						$entities = Kunta::estates($municipality->id)->orderBy($jarjestys_kentta, $jarjestys_suunta);
						$total_rows = Utils::getCount($entities);
						$entities = $entities->skip($rivi)->take($riveja)->get();

						if(count($entities) <= 0) {
							MipJson::setResponseStatus(Response::HTTP_OK);
							MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
						}
						else  {
							MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
							MipJson::addMessage(Lang::get('kiinteisto.found_count',["count" => count($entities)]));
							foreach ($entities as $entity)
								MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
						}
					}
					else {
						MipJson::setGeoJsonFeature();
						MipJson::addMessage(Lang::get('kunta.search_not_found'));
						MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					}
				}
			}
			catch(QueryException $e) {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('kyla.search_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
		return MipJson::getJson();
	}

	/**
	 * Get the list of villages that this municipality belongs to
	 *
	 * @param Request $request
	 * @param int $id
	 * @return <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function villages(Request $request, $id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kunta.katselu')) {
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
					$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
					$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";


					$municipality = Kunta::find($id);

					if($municipality) {
						$villages = $municipality->villages()->orderBy($jarjestys_kentta, $jarjestys_suunta);

						if($request->nimi) {
						    $villages->withVillageName($request->nimi);
						}
						if($request->kylanumero) {
						    $villages->withVillageNumber($request->kylanumero);
						}

						$total_rows = Utils::getCount($villages);
						$villages = $villages->skip($rivi)->take($riveja)->get();

						if(count($villages) <= 0) {
							MipJson::setResponseStatus(Response::HTTP_OK);
							MipJson::addMessage(Lang::get('kyla.search_not_found'));
						}
						else  {
							MipJson::initGeoJsonFeatureCollection(count($villages), $total_rows);
							MipJson::addMessage(Lang::get('kyla.found_count',["count" => count($villages)]));
							foreach ($villages as $village)
								MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $village);
						}
					}
					else {
						MipJson::setGeoJsonFeature();
						MipJson::addMessage(Lang::get('kunta.search_not_found'));
						MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					}
				}
			}
			catch(QueryException $e) {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('kyla.search_failed'));
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

		return KuntaMuutoshistoria::getById($id);

	}
}
