<?php

namespace App\Http\Controllers;

use App\Kayttaja;
use App\Kyla;
use App\KylaMuutoshistoria;
use App\Utils;
use App\Library\String\MipJson;
use App\Rak\Alue;
use App\Rak\Arvoalue;
use App\Rak\Kiinteisto;
use App\Rak\Rakennus;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class KylaController extends Controller {

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
		if(!Kayttaja::hasPermission('rakennusinventointi.kyla.katselu')) {
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
			'kunta_id'			=> 'numeric|exists:kunta,id',
			'kylanumero'		=> 'numeric',
			'nimi'				=> 'string',
			'kuntanumero'		=> 'numeric',
		    'kunta'				=> 'string'
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
				$entities = Kyla::getAll($jarjestys_kentta, $jarjestys_suunta)->with('kunta');

				//Get the related info also
				$entities = $entities->with(array('luoja'));

				/*
				 * If ANY search terms are given limit results by them
				 */
				if($request->kunta_id) {
					$entities->withMunicipalityID($request->kunta_id);
				}
				if($request->kuntaId) {
				    $entities->withMunicipalityID($request->kuntaId);
				}
				if($request->kuntanumero) {
					$entities->withKuntanumero($request->kuntanumero);
				}
				if($request->kunta) {
					$entities->withKuntanimi($request->kunta);
				}
				if($request->kylanumero) {
					$entities->withVillageNumber($request->kylanumero);
				}
				if($request->nimi) {
					$entities->withVillageName($request->nimi);
				}
				if($request->kylaId) {
				    $entities->withId($request->kylaId);
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
				MipJson::addMessage(Lang::get('kyla.search_success'));

			} catch(Exception $e) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('kyla.search_failed'));
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
		if(!Kayttaja::hasPermission('rakennusinventointi.kyla.katselu')) {
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
				$entity = Kyla::getSingle($id)->with('luoja')->with('muokkaaja')->first();

				if(!isset($entity->id)) {
					MipJson::setGeoJsonFeature();
		    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
		    		MipJson::addMessage(Lang::get('kyla.search_not_found'));
		    	}
		    	else {
		    		MipJson::setGeoJsonFeature(null, $entity);
		    		MipJson::addMessage(Lang::get('kyla.search_success'));
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
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kyla.luonti')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$validator = Validator::make($request->all(), [
			'kunta_id'			=> 'required|numeric|exists:kunta,id',
			'kylanumero'		=> 'required|numeric',
			'nimi'				=> 'required|string',
			'nimi_se'			=> 'nullable|string'
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

					$entity = new Kyla($request->all());

					$author_field = Kyla::CREATED_BY;
					$entity->$author_field = Auth::user()->id;

					$entity->save();

					DB::commit();
				} catch (Exception $e) {
					DB::rollback();
					throw $e;
				}

				MipJson::addMessage(Lang::get('kyla.save_success'));
				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			} catch(Exception $e) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('kyla.save_failed'));
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
		if(!Kayttaja::hasPermission('rakennusinventointi.kyla.muokkaus')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$validator = Validator::make($request->all(), [
			'kunta_id'			=> 'required|numeric|exists:kunta,id',
			'kylanumero'		=> 'required|numeric',
			'nimi'				=> 'required|string',
			'nimi_se'			=> 'nullable|string'
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
				$entity = Kyla::find($id);

				if(!$entity){
					//error, entity not found
					MipJson::setGeoJsonFeature();
					MipJson::addMessage(Lang::get('kyla.search_not_found'));
					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				}
				else {

					try {
						DB::beginTransaction();
						Utils::setDBUser();

						$entity->fill($request->all());
						$author_field = Alue::UPDATED_BY;
						$entity->$author_field = Auth::user()->id;
						$entity->save();

						DB::commit();
					} catch (Exception $e) {
						DB::rollback();
						throw $e;
					}
					MipJson::addMessage(Lang::get('kyla.save_success'));
					MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
				}

			} catch(Exception $e) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('kyla.save_failed'));
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
		if(!Kayttaja::hasPermission('rakennusinventointi.kyla.poisto')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$entity = kyla::find($id);
		if(!$entity) {
			// return: not found
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('kyla.search_not_found'));
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
			return MipJson::getJson();
		}

		try {
			DB::beginTransaction();
			Utils::setDBUser();

			$author_field = Kyla::DELETED_BY;
			$when_field = Kyla::DELETED_AT;
			$entity->$author_field = Auth::user()->id;
			$entity->$when_field = \Carbon\Carbon::now();
			$entity->save();

			DB::commit();

			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			MipJson::addMessage(Lang::get('kyla.delete_success'));

		}catch (Exception $e) {
			DB::rollback();

			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('kyla.delete_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		return MipJson::getJson();
	}

	/**
	 * Get the kiinteistot of a kyla
	 *
	 * @param $id
	 */
	public function kiinteistot(Request $request, $id) {
		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
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
				foreach($validator->errors()->all() as $error) {
					MipJson::addMessage($error);
				}
				MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
				return MipJson::getJson();
			}


			/*
			 * Initialize the variables that needs to be "reformatted"
			 */
			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : null;
			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : null;
			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

			$kyla = Kyla::find($id);

			if(!$kyla) {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('kyla.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}

			$kiinteistot = Kiinteisto::getAll();
			$kiinteistot->withOrderBy(null, $jarjestys_kentta, $jarjestys_suunta);
			$kiinteistot->withVillageId($kyla->id)->orderBy("kiinteistotunnus", "desc");

			$total_rows = Utils::getCount($kiinteistot);
			if (!is_null($rivi) && !is_null($riveja)) {
				$kiinteistot = $kiinteistot->skip($rivi)->take($riveja)->get();
			}

			MipJson::initGeoJsonFeatureCollection(count($kiinteistot), $total_rows);
			MipJson::addMessage(Lang::get('kiinteisto.found_count',["count" => count($kiinteistot)]));
			foreach ($kiinteistot as $kiinteisto) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $kiinteisto);
			}



		}
		catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('kiinteisto.search_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		return MipJson::getJson();
	}


	/**
	 * Get the rakennukset of a kyla
	 *
	 * @param $id
	 */
	public function rakennukset(Request $request, $id) {
		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.katselu')) {
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
				foreach($validator->errors()->all() as $error) {
					MipJson::addMessage($error);
				}
				MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
				return MipJson::getJson();
			}

			/*
			 * Initialize the variables that needs to be "reformatted"
			 */
			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : null;
			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : null;
			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

			$kyla = Kyla::find($id);

			if(!$kyla) {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('kyla.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}

			$rakennukset = Rakennus::getAll($jarjestys_kentta, $jarjestys_suunta);
			$rakennukset->with(array('kiinteisto','rakennustyypit'));
			$rakennukset->withVillageId($kyla->id)->orderBy($jarjestys_kentta, $jarjestys_suunta);

			$total_rows = Utils::getCount($rakennukset);
			if (!is_null($rivi) && !is_null($riveja)) {
				$rakennukset = $rakennukset->skip($rivi)->take($riveja)->get();
			}

			MipJson::initGeoJsonFeatureCollection(count($rakennukset), $total_rows);
			MipJson::addMessage(Lang::get('rakennus.found_count',["count" => count($rakennukset)]));

			foreach ($rakennukset as $rakennus) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $rakennus);
			}

		}
		catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('rakennus.search_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		return MipJson::getJson();
	}

	/**
	 * Get the alueet of a kyla
	 *
	 * @param $id
	 */
	public function alueet(Request $request, $id) {
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
				foreach($validator->errors()->all() as $error) {
					MipJson::addMessage($error);
				}
				MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
				return MipJson::getJson();
			}

			/*
			 * Initialize the variables that needs to be "reformatted"
			 */
			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : null;
			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : null;
			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

			$kyla = Kyla::find($id);

			if(!$kyla) {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('kyla.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}

			$alueet = Alue::getAll();
			$alueet->with(array('kylat','kylat.kunta'));
			$alueet->withVillageId($kyla->id)->orderBy($jarjestys_kentta, $jarjestys_suunta);
			$alueet->withOrderBy(null, $jarjestys_kentta, $jarjestys_suunta);

			$total_rows = Utils::getCount($alueet);
			if (!is_null($rivi) && !is_null($riveja)) {
				$alueet = $alueet->skip($rivi)->take($riveja)->get();
			}

			MipJson::initGeoJsonFeatureCollection(count($alueet), $total_rows);
			MipJson::addMessage(Lang::get('alue.found_count',["count" => count($alueet)]));

			foreach ($alueet as $alue) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $alue);
			}

		}
		catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('alue.search_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		return MipJson::getJson();
	}

	/**
	 * Get the arvoalueet of a kyla
	 *
	 * @param $id
	 */
	public function arvoalueet(Request $request, $id) {
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
				foreach($validator->errors()->all() as $error) {
					MipJson::addMessage($error);
				}
				MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
				return MipJson::getJson();
			}

			/*
			 * Initialize the variables that needs to be "reformatted"
			 */
			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : null;
			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : null;
			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

			$kyla = Kyla::find($id);

			if(!$kyla) {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('kyla.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}

			$arvoalueet = Arvoalue::getAll();
			$arvoalueet->with(array('kylat','kylat.kunta'));
			$arvoalueet->withVillageId($kyla->id)->orderBy($jarjestys_kentta, $jarjestys_suunta);
			$arvoalueet->withOrderBy(null, $jarjestys_kentta, $jarjestys_suunta);

			$total_rows = Utils::getCount($arvoalueet);
			if (!is_null($rivi) && !is_null($riveja)) {
				$arvoalueet = $arvoalueet->skip($rivi)->take($riveja)->get();
			}

			MipJson::initGeoJsonFeatureCollection(count($arvoalueet), $total_rows);
			MipJson::addMessage(Lang::get('arvoalue.found_count',["count" => count($arvoalueet)]));

			foreach ($arvoalueet as $arvoalue) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $arvoalue);
			}

		}
		catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('arvoalue.search_failed'));
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

		return KylaMuutoshistoria::getById($id);

	}

}
