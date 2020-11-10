<?php
namespace App\Library\History;
use App\Library\Gis\MipGis;

class HistoryPopulatorBase {
	
	public function getGeometryAsText($geometry) {
		if (is_null($geometry) || $geometry=="") {
			return "";
		}
		// TODO: if geometry is invalid (can it be?) then this will throw exception.
		// should it be catched and returned as "invalid geometry" or something?
		$geom = MipGis::getGeometryAsText($geometry);
		
		if ($geom) {
			// TODO: should we make it more human-readable?
			
			return $geom;
		}
		return "";
	}
}