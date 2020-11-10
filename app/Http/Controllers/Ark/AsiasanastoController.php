<?php

namespace App\Http\Controllers\Ark;

use App\Ark\Asiasanasto;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Response;
use Exception;

class AsiasanastoController extends Controller {
	public function index() {
		try {
			//Get only the identifier, no need to return all of the data.
			//TODO: Return only the needed localized identifier?
			$entities = Asiasanasto::orderBy ( "tunnus_fi", "ASC" )->get (['id', 'tunnus_fi', 'tunnus_se', 'tunnus_en']);
			$total_rows = count ( $entities );
			MipJson::initGeoJsonFeatureCollection ( $total_rows, $total_rows );
			
			foreach ( $entities as $entity ) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint ( null, $entity );
			}
		} catch ( Exception $e ) {
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
			MipJson::addMessage ( Lang::get ( 'asiasanasto.get_failed' ) );
		}
		//return $entities->toJson();
		return MipJson::getJson ();
	}
}