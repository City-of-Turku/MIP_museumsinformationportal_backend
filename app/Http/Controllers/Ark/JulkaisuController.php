<?php

namespace App\Http\Controllers\Ark;

use App\Ark\Julkaisu;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Response;
use Exception;

class JulkaisuController extends Controller {
	public function index() {
		try {
		
			$entities = Julkaisu::orderBy("nimi_fi", "ASC")->get();
			$total_rows = count($entities);
			MipJson::initGeoJsonFeatureCollection($total_rows, $total_rows);
		
			foreach ($entities as $entity) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
			}
		
		} catch(Exception $e) {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('julkaisu.get_failed'));
		}
			
		return MipJson::getJson();
	}
}
