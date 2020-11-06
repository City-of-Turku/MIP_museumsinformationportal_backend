<?php

namespace App\Http\Controllers\Ark;

use App\Ark\Nayttely;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Response;
use Exception;

class NayttelyController extends Controller {
	public function index() {
		try {
		
			$entities = Nayttely::orderBy("nimi_fi", "ASC")->get();
			$total_rows = count($entities);
			MipJson::initGeoJsonFeatureCollection($total_rows, $total_rows);
		
			foreach ($entities as $entity) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
			}
		
		} catch(Exception $e) {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('nayttely.get_failed'));
		}
			
		return MipJson::getJson();
	}
}
