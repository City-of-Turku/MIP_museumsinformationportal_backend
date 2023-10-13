<?php

namespace App\Http\Controllers\Julkinen;

use App\Utils;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Rak\Alue;
use App\Rak\Arvoalue;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class PublicAlueController extends Controller
{
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request)
	{

		$validator = Validator::make($request->all(), [
			'rivi' => 'numeric',
			'rivit' => 'numeric',
			'jarjestys' => 'string',
			'jarjestys_suunta' => 'string',
			'nimi' => 'string',
			"kunta" => "string",
			"kuntanumero" => "string",
			"kyla" => "string",
			"kylanumero" => "string",
			//"aluerajaus"		=> "string|regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/",
			'id' => "numeric|exists:alue,id",
		]);

		if ($validator->fails()) {
			MipJson::setGeoJsonFeature(null, null);
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			foreach ($validator->errors()->all() as $error)
				MipJson::addMessage($error);
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
		} else {
			try {

				/*
				 * Initialize the variables that needs to be "reformatted"
				 */
				$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
				$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
				$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
				$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

				$entities = Alue::getAllPublicInformation();

				$entities = $entities->with(
					array(
						'kylat' => function ($query) use ($jarjestys_kentta, $jarjestys_suunta) {
							if ($jarjestys_kentta == "kyla") {
								$query->orderBy("nimi", $jarjestys_suunta);
							}
						},
						'kylat.kunta' => function ($query) use ($jarjestys_kentta, $jarjestys_suunta) {
							if ($jarjestys_kentta == "kunta") {
								$query->orderBy("nimi", $jarjestys_suunta);
							}
						},
					)
				);

				/*
				 * If ANY search terms are given limit results by them
				 */
				if ($request->id) {
					$entities = Alue::getSingle($request->id);
				}
				if ($request->nimi) {
					$entities->withAreaName($request->nimi);
				}
				if ($request->kunta) {
					$entities->withMunicipalityName($request->kunta);
				}
				if ($request->kuntaId) {
					$entities->withMunicipalityId($request->kuntaId);
				}
				if ($request->kuntanumero) {
					$entities->withMunicipalityNumber($request->kuntanumero);
				}
				if ($request->kyla) {
					$entities->withVillageName($request->kyla);
				}
				if ($request->kylaId) {
					$entities->withVillageId($request->kylaId);
				}
				if ($request->paikkakunta) {
					$entities->withPaikkakunta($request->paikkakunta);
				}
				if ($request->kylanumero) {
					$entities->withVillageNumber($request->kylanumero);
				}
				//Aluerajaus tarkoittaa bounding boxia
				if ($request->aluerajaus) {
					$entities->withBoundingBox($request->aluerajaus);
				}
				//Polygonrajaus tarkoittaa vapaamuotoista piirrettyä geometriaa jonka mukaan rajataan
				if ($request->polygonrajaus) {
					$entities->withPolygon($request->polygonrajaus);
				}

				// order the results by given parameters
				$entities->withOrderBy($request->aluerajaus, $jarjestys_kentta, $jarjestys_suunta);

				// calculate the total rows of the search results
				$total_rows = Utils::getCount($entities);

				// limit the results rows by given params
				$entities->withLimit($rivi, $riveja);

				// Execute the query
				$entities = $entities->get();

				MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
				foreach ($entities as $entity) {
					//Hide fields from kylat array
					$entity->kylat->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
					//Hide fields from kylat.kunta array
					foreach ($entity->kylat as $kyla) {
						$kyla->kunta->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
					}

					/*
					 * clone $entity so we can handle the "properties" separately
					 * -> remove "sijainti" from props
					 */
					$properties = clone ($entity);
					unset($properties['sijainti']);
					unset($properties['alue']);

					if (!is_null($entity->sijainti)) {
						MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($entity->sijainti), $properties);
					} else {
						MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($entity->alue), $properties);
					}
				}

				MipJson::addMessage(Lang::get('alue.search_success'));

			} catch (Exception $e) {
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('alue.search_failed'));
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
	public function show($id)
	{

		if (!is_numeric($id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
		} else {
			try {
				$entity = Alue::getSinglePublicInformation($id)->with(
					array(
						'kylat',
						'kylat.kunta',
					)
				)->first();

				if ($entity) {

					//Hide fields from kylat array
					$entity->kylat->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);

					//Hide fields from kylat.kunta array
					foreach ($entity->kylat as $kyla) {
						$kyla->kunta->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
					}

					$properties = clone ($entity);
					unset($properties['sijainti']);
					unset($properties['alue']);

					if (!is_null($entity->sijainti))
						MipJson::setGeoJsonFeature(json_decode($entity->sijainti), $properties);
					else if (!is_null($entity->alue))
						MipJson::setGeoJsonFeature(json_decode($entity->alue), $properties);
					else {
						MipJson::setGeoJsonFeature(null, $properties);
					}

					MipJson::addMessage(Lang::get('alue.search_success'));
				} else {
					MipJson::setGeoJsonFeature();
					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					MipJson::addMessage(Lang::get('alue.search_not_found'));
				}
			} catch (QueryException $e) {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('alue.search_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
		return MipJson::getJson();
	}

	public function listValueAreas(Request $request, $id)
	{

		if (!is_numeric($id)) {
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
		} else {
			try {

				$validator = Validator::make($request->all(), [
					'rivi' => 'numeric',
					'rivit' => 'numeric',
					'jarjestys' => 'string',
					'jarjestys_suunta' => 'string',
				]);

				if ($validator->fails()) {
					MipJson::setGeoJsonFeature();
					MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
					foreach ($validator->errors()->all() as $error)
						MipJson::addMessage($error);
					MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
				} else {

					/*
					 * Initialize the variables that needs to be "reformatted"
					 */
					$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
					$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
					$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
					$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

					$area = Alue::find($id);

					if ($area) {
						$entities = Arvoalue::getByAreaId($id)->orderBy('inventointinumero');
						$total_rows = Utils::getCount($entities);
						$entities = $entities->skip($rivi)->take($riveja)->get();

						MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
						MipJson::addMessage(Lang::get('arvoalue.search_success'));

						foreach ($entities as $entity) {

							//Hide fields from properties
							$entity->makeHidden([
								'poistettu',
								'poistaja',
								'luotu',
								'luoja',
								'muokattu',
								'muokkaaja',
								'tarkistettu',
								'arkeologinen_kohde'
							]);

							/*
							 * -> remove "sijainti" from props
							 */
							$properties = clone ($entity);
							unset($properties['sijainti']);
							unset($properties['alue']);

							if (!is_null($entity->sijainti)) {
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
			} catch (QueryException $e) {
				MipJson::addMessage(Lang::get('alue.search_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
		return MipJson::getJson();
	}
}