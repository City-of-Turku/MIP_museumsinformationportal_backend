<?php

namespace App\Http\Controllers;

use App\Kayttaja;
use App\Integrations\MMLQueries;
use App\Library\String\MipJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class KtjController extends Controller {
	
	/**
	 * Method to query KIINTEISTO data from MML web services with given point in map
	 *
	 * @param Request $request
	 * @return MipJson
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function queryKiinteisto(Request $request) {
			
		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$validator = Validator::make($request->all(), [
				"sijainti"			=> "required|regex:/^\d{1,10}\.{0,1}\d{0,10} \d{1,10}\.{0,1}\d{0,10}$/" // (lat, lon)
		]);

		if ($validator->fails()) {
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			foreach($validator->errors()->all() as $error) {
				MipJson::addMessage($error);
				MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			}
			return MipJson::getJson();
		}
		
		try {
			
			$kiinteistot = MMLQueries::getKiinteistoTunnusByPoint($request->sijainti);

			if (empty($kiinteistot)) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				MipJson::addMessage(Lang::get('ktj.search_not_found'));
				return MipJson::getJson();
			}

			MipJson::initGeoJsonFeatureCollection(count($kiinteistot), count($kiinteistot));

			foreach ($kiinteistot as $index => $kiinteisto) {
				
				$kiint_tiedot = MMLQueries::getKiinteistoTiedotREST($kiinteisto['kiinteistotunnus']);

				$kiinteisto['nimi'] = $kiint_tiedot['nimi'];
				$kiinteisto['kuntanumero'] = $kiint_tiedot['kuntanumero'];
				$kiinteisto['kuntanimi_fi'] = $kiint_tiedot['kuntanimi_fi'];
				$kiinteisto['kuntanimi_se'] = $kiint_tiedot['kuntanimi_se'];
				$kiinteisto['omistajat'] = $kiint_tiedot['omistajat'];
				
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $kiinteisto);
			} // foreach

			MipJson::addMessage(Lang::get('ktj.search_success'));

		}
		catch(Exception $e) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('ktj.search_failed'));
		}

		return MipJson::getJson();
	}
	
	/**
	 * Kiinteistöjen ja rakennusten haku polygonin mukaan
	 */
	public function queryKiinteistotWithinPolygonWithRakennukset(Request $request) {
		
		try {
			
			$kiinteistot = MMLQueries::getKiinteistoTunnusByPolygon($request->sijainti);
			
			MipJson::initGeoJsonFeatureCollection(count($kiinteistot), count($kiinteistot));
			
			foreach ($kiinteistot as $index => $kiinteisto) {
			
				$kiint_tiedot = MMLQueries::getKiinteistoTiedotREST($kiinteisto['kiinteistotunnus']);
			
				$kiinteisto['nimi'] = $kiint_tiedot['nimi'];
				$kiinteisto['kuntanumero'] = $kiint_tiedot['kuntanumero'];
				$kiinteisto['kuntanimi_fi'] = $kiint_tiedot['kuntanimi_fi'];
				$kiinteisto['kuntanimi_se'] = $kiint_tiedot['kuntanimi_se'];
				
				// haetaan myös rakennustiedot
				$rakennukset_mml = MMLQueries::getRakennusTiedot($kiinteisto['kiinteistotunnus'], null);
				$rakennukset = array();
				
				// laitetaan palautukseen vain tarvittavat tiedot, nyt tulee hirveästi kaikkea muutakin.
				foreach ($rakennukset_mml as $index => $rakennus) {
					$r = [];
					$r['rakennustunnus'] = $rakennus['rakennustunnus'];
					$r['postinumero'] = $rakennus['postinumero'];
					$r['kunta'] = $rakennus['kuntanimiFin'];
					
					if(array_key_exists('osoitteet', $rakennus)){
					    $r['osoitteet'] = $rakennus['osoitteet'];
					}

					array_push($rakennukset,  $r);
				}

				$kiinteisto['rakennukset'] = $rakennukset;
				
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $kiinteisto);
			} // foreach
			
			MipJson::addMessage(Lang::get('ktj.search_success'));
			
		}
		catch(Exception $e) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('ktj.search_failed'));
		}
		
		return MipJson::getJson();
	}
	
	
	/**
	 * Method to query RAKENNUS data from MML web services with given point in map
	 *
	 * @param Request $request
	 * @return MipJson
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function queryRakennus(Request $request) {
			
		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
	
		$validator = Validator::make($request->all(), [
				"kiinteistotunnus"			=> "string"
		]);
	
		if ($validator->fails()) {
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			foreach($validator->errors()->all() as $error) {
				MipJson::addMessage($error);
				MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			}
			return MipJson::getJson();
		}
	
		try {			
			$rakennukset = MMLQueries::getRakennusTiedot($request->kiinteistotunnus, $request->sijainti);					
			if (empty($rakennukset)) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_OK);
				MipJson::addMessage(Lang::get('ktj.search_not_found'));
				return MipJson::getJson();
			}
			
			MipJson::initGeoJsonFeatureCollection(count($rakennukset), count($rakennukset));
			
			foreach ($rakennukset as $index => $rakennus) {	
				// set the feature->geometry->type  to 'Point' and feature->geometry->coordinates to the sijainti				
				$point = [];
				$point["type"] = "Point";
				$point["coordinates"] = explode(" ", $rakennus["sijainti"]);
				//Convert string to float
				$point["coordinates"][0] = (float)$point['coordinates'][0];
				$point["coordinates"][1] = (float)$point['coordinates'][1];
				
				MipJson::addGeoJsonFeatureCollectionFeaturePoint($point, $rakennus);				
			}
			
			MipJson::addMessage(Lang::get('ktj.search_success'));
	
		}
		catch(Exception $e) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('ktj.search_failed'));
		}
	
		return MipJson::getJson();
	}
	
	/**
	 * Method to query location for address from MML web services with given point in map
	 *
	 * @param Request $request
	 * @return MipJson
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function queryOsoite(Request $request) {
		
		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
		/*
		$validator = Validator::make($request->all(), [
				"kiinteistotunnus"			=> "string"
		]);
		
		if ($validator->fails()) {
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			foreach($validator->errors()->all() as $error) {
				MipJson::addMessage($error);
				MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			}
			return MipJson::getJson();
		}
		*/
		try {	
			$osoitteet = MMLQueries::getOsoiteTiedot($request->katunimi, $request->kuntanimi, $request->kuntanumero);
			
			if (empty($osoitteet)) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_OK);
				MipJson::addMessage(Lang::get('ktj.search_not_found'));
				return MipJson::getJson();
			}
			
			MipJson::initGeoJsonFeatureCollection(count($osoitteet), count($osoitteet));
			
			foreach ($osoitteet as $index => $osoite) {				
				// set the feature->geometry->type  to 'Point' and feature->geometry->coordinates to the sijainti
				$point = [];
				$point["type"] = "Point";
				$point["coordinates"] = explode(" ", $osoite["sijainti"]);
				//Convert string to float
				$point["coordinates"][0] = (float)$point['coordinates'][0];
				$point["coordinates"][1] = (float)$point['coordinates'][1];
				
				MipJson::addGeoJsonFeatureCollectionFeaturePoint($point, $osoite);
				
			}
			
			MipJson::addMessage(Lang::get('ktj.search_success'));
			
		}
		catch(Exception $e) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('ktj.search_failed'));
		}
		
		return MipJson::getJson();
	}
	
	/**
	 * Method to query location for nimisto from MML web services with given place name
	 *
	 * @param Request $request
	 * @return MipJson
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function queryNimisto(Request $request) {
		
		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
		/*		 
		 $validator = Validator::make($request->all(), [
		 "paikannimi"			=> "string"
		 ]);
		 
		 if ($validator->fails()) {
		 MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
		 foreach($validator->errors()->all() as $error) {
		 MipJson::addMessage($error);
		 MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
		 }
		 return MipJson::getJson();
		 }
		 */
		 
		try {
			$paikat = MMLQueries::getNimistoTiedot($request->paikannimi, $request->kunta, $request->kuntahaku);
			
			if (empty($paikat)) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_OK);
				MipJson::addMessage(Lang::get('ktj.search_not_found'));
				return MipJson::getJson();
			}
			
			MipJson::initGeoJsonFeatureCollection(count($paikat), count($paikat));
			
			foreach ($paikat as $index => $paikka) {
				// set the feature->geometry->type  to 'Point' and feature->geometry->coordinates to the sijainti
				$point = [];
				$point["type"] = "Point";
				$point["coordinates"] = explode(" ", $paikka["sijainti"]);
				//Convert string to float
				$point["coordinates"][0] = (float)$point['coordinates'][0];
				$point["coordinates"][1] = (float)$point['coordinates'][1];
				
				MipJson::addGeoJsonFeatureCollectionFeaturePoint($point, $paikka);
				
			}
			
			MipJson::addMessage(Lang::get('ktj.search_success'));
			
		}
		catch(Exception $e) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('ktj.search_failed'));
		}
		
		return MipJson::getJson();
	}
	
}
