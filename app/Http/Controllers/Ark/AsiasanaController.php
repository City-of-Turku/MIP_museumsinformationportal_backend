<?php

namespace App\Http\Controllers\Ark;

use App\Ark\Asiasana;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Response;
use Exception;

class AsiasanaController extends Controller
{
	public function index() {
		try {
			//Get only the word itself, no need to return all data.
			//TODO: Return only the needed localized word?
			$entities = Asiasana::orderBy ( "asiasana_fi", "ASC" )->get (['id', 'asiasana_fi', 'asiasana_se', 'asiasana_en']);
			$total_rows = count ( $entities );
			MipJson::initGeoJsonFeatureCollection ( $total_rows, $total_rows );
				
			foreach ( $entities as $entity ) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint ( null, $entity );
			}
		} catch ( Exception $e ) {
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
			MipJson::addMessage ( Lang::get ( 'asiasana.get_failed' ) );
		}
	
		return MipJson::getJson ();
	}
}
