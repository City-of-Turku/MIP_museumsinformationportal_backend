<?php

namespace App\Http\Controllers\Muistot;

use App\Http\Controllers\Controller;
use App\Kayttaja;
use App\Rak\Kuva;
use App\Muistot\Muistot_kuva;
use App\Library\String\MipJson;
use App\Utils;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;


class MuistotKuvaController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {

    	/*
    	 * Role check
    	 */
      if(!Kayttaja::hasPermission('muistot.muisto.katselu')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'muisto_id'				=> 'numeric|exists:muistot_muisto,prikka_id',
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
    			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
    			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
    			// $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "muistot_kuva.prikka_id";
          $jarjestys_kentta = "prikka_id";
    			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

    			$entities = Muistot_kuva::with('muisto')->orderBy($jarjestys_kentta, $jarjestys_suunta);
          
    			if($request->input("muisto_id")) {
    				$entities->withMemoryID($request->input("muisto_id"));
    			}

    			$total_rows = Utils::getCount($entities);
    			// $entities->withLimit($rivi, $riveja);
    			$entities = $entities->get();

          if(count($entities) <= 0) {
    				MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
    				MipJson::addMessage(Lang::get('kuva.search_not_found'));
    			}
    			else  {
    				MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
    				foreach ($entities as $entity) {
    					$images = Kuva::getImageUrls($entity->polku.$entity->nimi);
    					$entity->url = $images->original;
    					$entity->url_tiny = $images->tiny;
    					$entity->url_small = $images->small;
    					$entity->url_medium = $images->medium;
    					$entity->url_large = $images->large;
              // a few props needed for UI
              $entity->id = $entity->prikka_id; 
              $entity->otsikko = $entity->ottopaikka . " " . $entity->ottohetki; // modal title
              $entity->muisto_otsikko = $entity->muisto->kuvaus;
    					MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
    				}
    				MipJson::addMessage(Lang::get('kuva.found_count',["count" => count($entities)]));
    			}
    		}
    		catch(Exception $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('kuva.search_failed'));
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
    	if(!Kayttaja::hasPermission('muistot.muisto.katselu')) {
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

    			$entity = Muistot_kuva::getSingle($id)->with('muisto')->first();
    			if(!$entity) {
    				MipJson::setGeoJsonFeature();
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				MipJson::addMessage(Lang::get('kuva.search_not_found'));
    			}
    			else {
    				$entity->first();

            // huom. rakennuspuolen funkkari, mutta pitäisi toimia suoraan
    				$images = Kuva::getImageUrls($entity->polku.$entity->nimi); //
    				$entity->url = $images->original;
    				$entity->url_tiny = $images->tiny;
    				$entity->url_small = $images->small;
    				$entity->url_medium = $images->medium;
    				$entity->url_large = $images->large;
            // a few props needed for UI handling for images
            $entity->id = $entity->prikka_id; 
            $entity->otsikko = $entity->ottopaikka . " " . $entity->ottohetki; // modal title
            $entity->muisto_otsikko = $entity->muisto->kuvaus;

    				MipJson::setGeoJsonFeature(null, $entity);
    				MipJson::addMessage(Lang::get('kuva.search_success'));
    			}
    		}
    		catch(QueryException $e) {
    			MipJson::addMessage(Lang::get('kuva.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
    }

    /**
     * Used by report generation to get the image
     * @param $id Image ID
     */
    public function viewSmall($id) {

    	if(!is_numeric($id)) {
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    		return;
    	}

    	try {

    		$entity = Muistot_kuva::getSingle($id)->first();
    		if(!$entity) {
    			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			return;
    		}

    		$images = Kuva::getImageUrls($entity->polku.$entity->nimi);
    		$url = $images->medium;
            Log::channel('prikka')->info("viewSmallImage url: " . $url);

    		if ($url) {
    			return redirect( $url );
    		} else {
    			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			return;
    		}
    	} catch(QueryException $e) {
    		MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		return;
    	}
    }
}
