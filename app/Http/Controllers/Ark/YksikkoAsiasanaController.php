<?php

namespace App\Http\Controllers\Ark;

use App\Ark\YksikkoAsiasana;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Response;
use Exception;

class YksikkoAsiasanaController extends Controller
{	
	/**
	 * Get all asiasanat of a unit
	 *
	 * @param $unit_id
	 */
	public function forYksikko($yksikko_id) {
		if(!is_numeric($yksikko_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}
	
		try {
			$queryBuilder = YksikkoAsiasana::withYksikkoId($yksikko_id);
	
			$queryBuilder = $queryBuilder->orderBy('yksikko.id', 'asc');				
	
			// EXECUTE!
			$entities = $queryBuilder->get();
				
			$total_rows = count($entities);
			MipJson::initGeoJsonFeatureCollection($total_rows, $total_rows);
	
			foreach ($entities as $entity) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
			}
		} catch(Exception $e) {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('yksikkoAsiasana.get_failed'));
		}
	
		return MipJson::getJson();
	}
}
