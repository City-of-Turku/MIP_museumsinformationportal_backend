<?php

/*
 * 
 * 
 * DEPRECATED
 * KÄYTETÄÄN NYKYÄÄN TUTKIMUSALUEYKSIKKOCONTROLLERIA!
 * 
 * 
 * 
 */


namespace App\Http\Controllers\Ark;

use App\Kayttaja;
use App\Ark\Yksikko;
use App\Ark\YksikkoAsiasana;
use App\Ark\YksikkoSijainti;
use App\Ark\YksikkoTalteenottotapa;
use App\Http\Controllers\Controller;
use App\Library\Gis\MipGis;
use App\Library\String\MipJson;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class YksikkoController extends Controller
{
/**
	 * Search for units
	 * 
	 * @param Request $request
	 */
	public function index(Request $request) {
		$validator = Validator::make($request->all(), [
				'rivi'				=> 'numeric',
				'rivit'				=> 'numeric',
				'jarjestys'			=> 'string',
				'jarjestys_suunta'	=> 'string',
				'tunnus'			=> 'string',
				'projekti'			=> 'string',
				'yksikko_tyyppi'	=> 'string',
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
			
			// TODO: PERMISSION CHECKS 
			
			/*
			 * By default return ALL items from db (with LIMIT and ORDER options)
			 */
			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";
				
			$queryBuilder = Yksikko::withProjektiAndYksikkoTyyppiNimi($request->projekti, $request->yksikkoTyyppi);

			if ($request->tunnus) {
				$queryBuilder = $queryBuilder->where('yksikko.tunnus', 'ILIKE', "%$request->tunnus%");
			}
			
			$queryBuilder = $queryBuilder->orderBy('yksikko.id', 'asc');
			$queryBuilder = $queryBuilder->with ( array (
					'projekti' => function ($query) {
						$query->select ( 'id', 'tunnus', 'nimi' );
					},
					'tutkija' => function ($query) {
						$query->select ( 'id', 'etunimi', 'sukunimi' );
					},
					'luoja' => function ($query) {
						$query->select ( 'id', 'etunimi', 'sukunimi' );
					},
					'muokkaaja' => function ($query) {
						$query->select ( 'id', 'etunimi', 'sukunimi' );
					},
					'yksikkoTyyppi',
					'yksikonElinkaari',
					'yksikkoAsiasana',
					'yksikkoTalteenottotapa'
			) );
			
			// TODO:
			// ->where ( julkinen OR user has access to )

			// EXECUTE!
			$entities = $queryBuilder->get();
			
			$total_rows = count($entities);
			MipJson::initGeoJsonFeatureCollection($total_rows, $total_rows);
		
			foreach ($entities as $entity) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
			}
		
		} catch(Exception $e) {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('yksikko.get_failed'));
		}
			
		return MipJson::getJson();
	}
	
	/**
	 * Get all units of a project
	 *
	 * @param $projekti_id
	 */
	public function forProjekti(Request $request, $projekti_id) {
		if(!is_numeric($projekti_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}
	
		try {
			
			/*
			 * Permission check
			 */
			if(!Kayttaja::hasPermissionForEntity('arkeologia.projekti.katselu', $projekti_id)) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
				MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
				return MipJson::getJson();
			}
			
			/*
			 * By default return ALL items from db (with LIMIT and ORDER options)
			 */
			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";
			
			
			$queryBuilder = Yksikko::withProjektiId($projekti_id);

			if ($request->tarkka && $request->tunnus) {
				$queryBuilder = $queryBuilder->where('yksikko.tunnus', 'ILIKE', "$request->tunnus");
			} else if ($request->tunnus) {
				$queryBuilder = $queryBuilder->where('yksikko.tunnus', 'ILIKE', "%$request->tunnus%");
			}
			
			$queryBuilder = $queryBuilder->orderBy('yksikko.id', 'asc');
			$queryBuilder = $queryBuilder->with ( array (
					'tutkija' => function ($query) {
						$query->select ( 'id', 'etunimi', 'sukunimi' );
					},
					'yksikkoTyyppi',
					'yksikonElinkaari',
					'yksikkoTalteenottotapa'
			) );
			
			// EXECUTE!
			$entities = $queryBuilder->get();
			
			$total_rows = count($entities);
			MipJson::initGeoJsonFeatureCollection($total_rows, $total_rows);
		
			foreach ($entities as $entity) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
			}
		} catch(Exception $e) {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('projekti.get_failed'));
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
		$validator = Validator::make($request->all(), [
				'tunnus'				=> 'required|string|max:256',
				'yksikko_tyyppi_id'		=> 'required|numeric|exists:yksikko_tyyppi,id',
				'projekti_id'			=> 'numeric|exists:projekti,id'
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
			
			$entity = new Yksikko($request->all());
			
			/*
			 * Permission check
			 */
			if(!Kayttaja::hasPermissionForEntity('arkeologia.projekti.muokkaus', $entity->projekti_id)) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
				MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
				return MipJson::getJson();
			}
			
			$entity->luoja = Auth::user()->id;
			$entity->save();
				
			MipJson::addMessage(Lang::get('yksikko.save_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			MipJson::setResponseStatus(Response::HTTP_OK);
	
		} catch(AuthorizationException $e) {
			// just throw it on..
			throw $e;
		} catch(Exception $e) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('yksikko.save_failed'));
		}
		return MipJson::getJson();
	}
	
	/**
	 * Store a resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id) {
		$validator = Validator::make($request->all(), [
				'tunnus'				=> 'required|string|max:256',
				'yksikko_tyyppi_id'		=> 'required|numeric|exists:yksikko_tyyppi,id',
				'projekti_id'			=> 'numeric|exists:projekti,id',
				'talteenottotapa'		=> 'numeric|exists:talteenottotapa,id'
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
			 * Permission check
			 */
			if(!Kayttaja::hasPermissionForEntity('arkeologia.yksikko.muokkaus', $id)) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
				MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
				return MipJson::getJson();
			}
			
			$entity = Yksikko::find($id);
	
			if(!$entity){
				//error, entity not found
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('yksikko.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}
	
			// convert geometry from DB to lat/lon String like: "20.123456789 60.123456789"
			// $geom = MipGis::getPointGeometryValue($request->sijainti);
	
			$entity->fill($request->all());
			$entity->muokkaaja = Auth::user()->id;
			$entity->muokattu = new \DateTime();
			$entity->save();
	
			MipJson::addMessage(Lang::get('yksikko.save_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			MipJson::setResponseStatus(Response::HTTP_OK);
	
		} catch(AuthorizationException $e) {
			// just throw it on..
			throw $e;
		} catch(Exception $e) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('yksikko.save_failed'));
		}
	
		return MipJson::getJson();
	}
	
	/**
	 * Get the data of a given unit.
	 *
	 * @param  int  $id
	 * @return MipJson
	 */
	public function show($id) {
		if(!is_numeric($id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}
	
		try {
			
			/*
			 * Permission check
			 */
			if(!Kayttaja::hasPermissionForEntity('arkeologia.yksikko.katselu', $id)) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
				MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
				return MipJson::getJson();
			}
			
			$p = new Yksikko();
			$p->id = $id;
				
			$entity = Yksikko::getSingle ( $id )->with ( array (
					'projekti' => function ($query) {
						$query->select ( 'id', 'tunnus', 'nimi' );
					},
					'tutkija' => function ($query) {
						$query->select ( 'id', 'etunimi', 'sukunimi' );
					},
					'luoja' => function ($query) {
						$query->select ( 'id', 'etunimi', 'sukunimi' );
					},
					'muokkaaja' => function ($query) {
						$query->select ( 'id', 'etunimi', 'sukunimi' );
					},
					'yksikkoTyyppi',
					'yksikonElinkaari',
					'yksikkoAsiasana',
					'yksikkoTalteenottotapa'
			) )->first ();
			
			if($entity) {
				MipJson::setGeoJsonFeature(null, $entity);
				MipJson::addMessage(Lang::get('yksikko.get_success'));
			}
			else {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				MipJson::addMessage(Lang::get('yksikko.get_not_found'));
			}
		} catch(AuthorizationException $e) {
			// just throw it on..
			throw $e;
		} catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('yksikko.get_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
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
		 * Permission check
		 */
		if(!Kayttaja::hasPermissionForEntity('arkeologia.yksikko.poisto', $id)) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
		
		$entity = Yksikko::find($id);
		//TODO: Cascade the deletion to the referenced tables!!!
		if(!$entity) {
			// return: not found
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('yksikko.search_not_found'));
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
		}
		else  {
			$deleted_rows = $entity->delete();
			if($deleted_rows > 0) {
				MipJson::addMessage(Lang::get('yksikko.delete_success'));
				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			}
			else {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('yksikko.delete_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
		return MipJson::getJson();
	}
	
	/**
	 * Get the data of all geometries of a unit.
	 *
	 * @param  $yksikko_id
	 * @throws AuthorizationException
	 */
	public function showGeom($yksikko_id) {
		if(!is_numeric($yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}
	
		try {
			/*
			 * Permission check
			 */
			if(!Kayttaja::hasPermissionForEntity('arkeologia.yksikko.katselu', $yksikko_id)) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
				MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
				return MipJson::getJson();
			}
				
			$entities = YksikkoSijainti::byYksikkoId($yksikko_id)->get();
				
			$total_rows = count($entities);
			MipJson::initGeoJsonFeatureCollection($total_rows, $total_rows);
	
			foreach ($entities as $entity) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($entity->sijainti), $entity);
				unset($entity->sijainti);
			}

		} catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('yksikko_sijainti.get_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	
		return MipJson::getJson();
	}
	
	/**
	 * Remove a geometry from a unit
	 *
	 * @param $yksikko_id
	 * @param $id
	 * @throws AuthorizationException
	 */
	public function destroyGeom($yksikko_id, $id) {
		if(!is_numeric($id) || !is_numeric($yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}

		/*
		 * Permission check
		 */
		if(!Kayttaja::hasPermissionForEntity('arkeologia.yksikko.muokkaus', $yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
		
		try {
			$y = Yksikko::find($yksikko_id);
	
			if(!$y){
				//error, entity not found
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('yksikko.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}
					
			$entity = YksikkoSijainti::find($id);
				
			$deleted_rows = $entity->delete();
	
			if($deleted_rows == 1) {
				MipJson::addMessage(Lang::get('yksikkosijainti.delete_success'));
				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			} else {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('yksikkosijainti.delete_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}

		} catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('yksikkosijainti.delete_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	
		return MipJson::getJson();
	}
	
	
	/**
	 * Add (store) a new geometry for the given unit
	 *
	 * @param Request $request
	 * @param $yksikko_id
	 * @throws AuthorizationException
	 */
	public function storeGeom(Request $request, $yksikko_id) {
	
		if(!is_numeric($yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}
	
		$validator = Validator::make($request->all(), [
				'type'					=> 'required|in:Feature',
				'geometry'				=> 'required',
				'geometry.type'			=> 'required|in:Point,Polygon',
				'geometry.coordinates'	=> 'required'
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
		 * Permission check
		 */
		if(!Kayttaja::hasPermissionForEntity('arkeologia.yksikko.muokkaus', $yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
		
		try {
			$y = Yksikko::find($yksikko_id);
	
			if(!$y){
				//error, entity not found
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('yksikko.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}
				
			// parse the request, incoming data is geojson
			$entity = $request->all();
			// check the type of the geometry and parse the data according to the type
			if (strcasecmp($entity['geometry']['type'], 'point')==0) {
	
				$points = $this->extractPointPoints($entity['geometry']);
				$geom = MipGis::getPointGeometryValue("$points");
	
			} else if (strcasecmp($entity['geometry']['type'], 'polygon')==0) {
	
				$points = $this->extractPolygonPoints($entity['geometry']);
				$geom = MipGis::getAreaGeometryValue("$points");
	
			} else {
				// error
				// no need to throw error, the validation rules alredy block this
			}
				
			$entity = new YksikkoSijainti();
			$entity->yksikko_id = $yksikko_id;
			$entity->sijainti = $geom;
			$entity->luoja = Auth::user()->id;
			$entity->save();
	
			MipJson::addMessage(Lang::get('yksikkosijainti.save_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			MipJson::setResponseStatus(Response::HTTP_OK);

		} catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('yksikkosijainti.get_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	
		return MipJson::getJson();
	}
	
	private function extractPointPoints($geometry) {
	
		$lat = $geometry['coordinates'][0];
		$lon = $geometry['coordinates'][1];
	
		return "$lat $lon";
	}
	
	private function extractPolygonPoints($geometry) {
	
		$pointcount = count($geometry['coordinates'][0]);
		$geom = "";
	
		for($i = 0; $i < $pointcount; $i++) {
			$lat = $geometry['coordinates'][0][$i][0];
			$lon = $geometry['coordinates'][0][$i][1];
			$geom = "$geom $lat $lon";
			if ($i < $pointcount-1) {
				$geom = "$geom, ";
			}
		}
		return $geom;
	}
		
	/**
	 * Add (store) a new asiasana for the given unit
	 *
	 * @param Request $request
	 * @param $yksikko_id
	 * @throws AuthorizationException
	 */
	public function storeAsiasana(Request $request, $yksikko_id) {
	
		if(!is_numeric($yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}		
		
		$validator = Validator::make($request->all(), [
				'asiasana'			=> 'required',
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
		 * Permission check
		 */
		if(!Kayttaja::hasPermissionForEntity('arkeologia.yksikko.muokkaus', $yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
		
		try {
			// auth check
			$y = Yksikko::find($yksikko_id);
	
			if(!$y){
				//error, entity not found
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('yksikko.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}
				
			// parse the request, incoming data is geojson
			$entity = $request->all();
			$entity = new YksikkoAsiasana();
			$entity->yksikko_id = $yksikko_id;
			$entity->asiasana = $request->input('asiasana');
			$entity->luoja = Auth::user()->id;
			$entity->save();
			
			MipJson::addMessage(Lang::get('yksikkoAsiasana.save_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			MipJson::setResponseStatus(Response::HTTP_OK);
	
		} catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('yksikkoAsiasana.get_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	
		return MipJson::getJson();
	}
	

	/**
	 * Remove a asiasana from a unit
	 *
	 * @param $yksikko_id
	 * @param $id
	 * @throws AuthorizationException
	 */
	public function destroyAsiasana($yksikko_id, $asiasana) {
		if(!$asiasana || !is_numeric($yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}

		/*
		 * Permission check
		 */
		if(!Kayttaja::hasPermissionForEntity('arkeologia.yksikko.muokkaus', $yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
		
		try {
			$y = Yksikko::find($yksikko_id);
	
			if(!$y){
				//error, entity not found
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('yksikko.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}
				
			$entity = YksikkoAsiasana::where('yksikko_id', '=', $yksikko_id)->where('asiasana', '=', $asiasana)->first();

			$deleted_rows = $entity->delete();			
	
			if($deleted_rows == 1) {
				MipJson::addMessage(Lang::get('yksikkoAsiasana.delete_success'));
				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			} else {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('yksikkoAsiasana.delete_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}

		} catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('yksikkoAsiasana.delete_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	
		return MipJson::getJson();
	}
	/**
	 * Add (store) a new talteenottotapa for the given unit
	 *
	 * @param Request $request
	 * @param $talteenottotapa_id
	 * @throws AuthorizationException
	 */
	public function storeTalteenottotapa(Request $request, $yksikko_id) {
	
		if(!is_numeric($yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}
	
		$validator = Validator::make($request->all(), [
				'talteenottotapa_id'			=> 'required|numeric|exists:talteenottotapa,id',
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
		 * Permission check
		 */
		if(!Kayttaja::hasPermissionForEntity('arkeologia.yksikko.muokkaus', $yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
		
		try {
			$y = Yksikko::find($yksikko_id);
	
			if(!$y){
				//error, entity not found
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('yksikko.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}

			// parse the request, incoming data is geojson
			$entity = $request->all();
				
			$entity = new YksikkoTalteenottotapa();
			$entity->yksikko_id = $yksikko_id;
			$entity->talteenottotapa_id = $request->input('talteenottotapa_id');
			$entity->luoja = Auth::user()->id;
			$entity->save();
	
			MipJson::addMessage(Lang::get('yksikkoTalteenottotapa.save_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			MipJson::setResponseStatus(Response::HTTP_OK);

		} catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('yksikkoTalteenottotapa.get_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	
		return MipJson::getJson();
	}
	
	
	/**
	 * Remove a talteenottotapa from a unit
	 *
	 * @param $yksikko_id
	 * @param $id
	 * @throws AuthorizationException
	 */
	public function destroyTalteenottotapa($yksikko_id, $talteenottotapa_id) {
		if(!is_numeric($talteenottotapa_id) || !is_numeric($yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}

		/*
		 * Permission check
		 */
		if(!Kayttaja::hasPermissionForEntity('arkeologia.yksikko.muokkaus', $yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
		
		try {
			$y = Yksikko::find($yksikko_id);
	
			if(!$y){
				//error, entity not found
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('yksikko.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}

			$entity = YksikkoTalteenottotapa::where('yksikko_id', '=', $yksikko_id)->where('talteenottotapa_id', '=', $talteenottotapa_id)->first();
			$deleted_rows = $entity->delete();
				
	
			if($deleted_rows == 1) {
				MipJson::addMessage(Lang::get('yksikkoTalteenottotapa.delete_success'));
				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			} else {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('yksikkoTalteenottotapa.delete_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}

		} catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('yksikkoTalteenottotapa.delete_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	
		return MipJson::getJson();
	}
	
}
