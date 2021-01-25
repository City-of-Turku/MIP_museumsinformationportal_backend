<?php

namespace App\Library\Gis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Library\String\MipJson;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

class MipGis {


	/**
	 * Method to transform the given lat/lon pair to geometry value
	 *
	 * @param String $latlon String containing lat lon in format: "20.12345678 60.12345678"
	 * @author
	 * @version 1.0
	 * @since 1.0
	 * @return String returns given lat/lon pair to geometry value
	 *
	 * @note: ST_transform seems to return "unmatching" geometry value (few centimeters) while transforming from 3067 -> 4326
	 * The difference is so small that we will ignore it
	 */
	public static function getPointGeometryValue($latlon) {
		$result = DB::selectOne("SELECT ST_transform(ST_PointFromText('POINT(".$latlon.")', ".Config::get('app.json_srid')."), ".Config::get('app.db_srid').") AS value ");

		// TODO: remove transform (EPSG:3067 -> EPSG:4326)
		// TODO: test if it works...
		//$result = DB::selectOne("SELECT ST_PointFromText('POINT(".$latlon.")', ".Config::get('app.db_srid').") AS value ");

		return $result->value;
	}

	/*
	 * Get the geometry as postgis-valid format
	 * Parameter $text has to be valid 'text' format of a geometry, see e.g. http://postgis.net/workshops/postgis-intro/geometries.html
	 * Example input: GEOMETRYCOLLECTION(POINT(239734.77360804 6710970.1761898),POINT(239858.75148871 6711042.8300602))
	 */
	public static function getGeometryFromText($text) {
	    $result = DB::selectOne("SELECT ST_GeomFromText('$text', 3067) as value;");
	    return $result->value;
	}

	/**
	 * Palauttaa 3067 projektion mukaisen geometry sijainnin longitude ja latitude arvoilla
	 * @param $lon
	 * @param $lat
	 */
	public static function haeEpsg3067Sijainti($lon, $lat) {

	    $geom = DB::selectOne('SELECT ST_SetSRID(ST_Point(' .$lon .',' .$lat .'), 3067) as sijainti');

	    return $geom->sijainti;
	}

	/**
	 * Palauttaa 3067 projektion mukaisen geometry sijainnin polygon arvon pisteille.
	 * @param $points = string muodossa "250098.66 6742259.89,250092.41 6742255.98,.."
	 */
	public static function haeEpsg3067PolygonSijainti($points) {

	    //$result = DB::selectOne('SELECT ST_PolygonFromText(' .POLYGON((' .$points .')).', 3067) as sijaintiAlue');
	    //$result = DB::selectOne("SELECT ST_PolygonFromText('POLYGON((".$points."))', ".Config::get('app.db_srid').") as sijaintiAlue ");
	    $result = DB::selectOne("SELECT ST_PolygonFromText('POLYGON((".$points."))', ".Config::get('app.db_srid').") as json ");
	    return $result->json;
	}

	/**
	 * Method to transform the given geometry value to JSON string
	 *
	 * @param String $geometryValue The value of geometry in db to be converted to json for example: "0101000020FB0B0000633937DD9A1F3440B29C9B6ECD0F4E40"
	 * @author
	 * @version 1.0
	 * @since1.0
	 * @return String
	 */
	public static function getPointJsonValue($geometryValue) {
		$result = DB::selectOne("SELECT ST_AsGeoJson(ST_transform('".$geometryValue."'::geometry, ".Config::get('app.json_srid').")) as json ");
		// TODO: remove transform (EPSG:3067 -> EPSG:4326)
		// TODO: test if it works...
		//$result = DB::selectOne("SELECT ST_AsGeoJson('".$geometryValue."'::geometry) as json ");
		return $result->json;
	}

	/**
	 * Method to transform given area coordinate pairs to geometry value
	 *
	 * @param $points
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getAreaGeometryValue($points) {
		$result = DB::selectOne("SELECT ST_transform(ST_PolygonFromText('POLYGON((".$points."))', ".Config::get('app.json_srid')."), ".Config::get('app.db_srid').") as json ");

		// TODO: remove transform (EPSG:3067 -> EPSG:4326)
		// TODO: test if it works...
		//$result = DB::selectOne("SELECT ST_PolygonFromText('POLYGON((".$points."))', ".Config::get('app.db_srid').") as json ");

		return $result->json;
	}

	/**
	 * Method to transform the given geometry value (of area) to JSON string
	 *
	 * @param String $geometryValue The value of geometry in db to be converted to json for example: "0101000020FB0B0000633937DD9A1F3440B29C9B6ECD0F4E40"
	 * @author
	 * @version 1.0
	 * @since1.0
	 * @return String returns json string
	 */
	public static function getAreaJsonValue($geometryValue) {
		$result = DB::selectOne("SELECT ST_AsGeoJson(ST_transform('".$geometryValue."'::geometry, ".Config::get('app.json_srid').")) as json ");

		// TODO: remove transform (EPSG:3067 -> EPSG:4326)
		// TODO: test if it works...
		//$result = DB::selectOne("SELECT ST_AsGeoJson('".$geometryValue."'::geometry) as json ");

		return $result->json;
	}

	/**
	 * Method to build the query string to fetch the position of given field from DB
	 *
	 * @param String $fieldname fieldname to fetch position from
	 * @param String $as fieldname to put result into
	 * @return string
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getGeometryFieldQueryString($fieldname, $as) {
		return "ST_AsGeoJson(ST_transform(".$fieldname.", ".Config::get('app.json_srid').")) as ".$as." ";

		// TODO: remove transform (EPSG:3067 -> EPSG:4326)
		// TODO: test if it works...
		//return "ST_AsGeoJson(".$fieldname.") as ".$as." ";

	}

	/**
	 * Method to build the query string to LIMIT results of data by given bounding box
	 *
	 * @param string $fieldName the name of the database field to use
	 * @param string $box ($minLon $minLat,$maxLon $maxLat)
	 * @return string returns the "WHERE" SQL statement
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getGeometryFieldBoundingBoxQueryWhereString($fieldName, $bbox) {
		/*
		 * RE-Format the given bounding box format.
		 * We read the "bounding box" from user in format: lon lat,lon lat
		 * This Postgis function however wants the bounding box in format: "lon,lat,lon,lat"
		 * --> So we will make it valid here.
		 */
		$bbox = str_replace(" ", ",", $bbox);

		return $fieldName." && ST_Transform(ST_MakeEnvelope(".$bbox.", ".Config::get('app.json_srid')."), ".Config::get('app.db_srid').")";


		// TODO: remove transform (EPSG:3067 -> EPSG:4326)
		// TODO: test if it works...
		//return $fieldName." && ST_MakeEnvelope(".$bbox.", ".Config::get('app.db_srid').")";



	}
	/*
	 * Add query section that checks if point is inside the given polygon OR
	 * a polygon is AT LEAST PARTLY inside the given polygon
	 *
	 * $fieldName1 = point
	 * $fieldName2 = polygon
	 *
	 * 2. Fieldname is optional, because Kiinteisto & Rakennus only have single point column for geometry
	 * 	but Alue & Arvoalue have two columns (point & polygon geometry).	 *
	 */
	public static function getGeometryFieldPolygonQueryWhereString($polygon, $fieldName1, $fieldName2 = null) {
		if($fieldName2 != null)  {
			//Point OR polygon geometry present, check either one.
			//Contains and overlaps both need to be present as
			//the first returns true if the area is completely inside the given polygon
			//and the latter one returns true only if they are partly overlapping, but not completely
			$ret = "(ST_Contains(ST_Transform(ST_GeomFromText('POLYGON(($polygon))',  " . Config::get('app.json_srid') . "), " . Config::get('app.db_srid') . "), ". $fieldName1 . ")";
			$ret .= " OR (";
			$ret .= "ST_Overlaps(ST_Transform(ST_GeomFromText('POLYGON(($polygon))',  " . Config::get('app.json_srid') . "), " . Config::get('app.db_srid') . "), ". $fieldName2 . ")";
			$ret .= " OR ";
			$ret .= "ST_Contains(ST_Transform(ST_GeomFromText('POLYGON(($polygon))',  " . Config::get('app.json_srid') . "), " . Config::get('app.db_srid') . "), ". $fieldName2 . ")";
			$ret .= "))";

			return $ret;
		} else {
			//Point
			return "ST_Contains(ST_Transform(ST_GeomFromText('POLYGON(($polygon))',  " . Config::get('app.json_srid') . "), " . Config::get('app.db_srid') . "), ". $fieldName1 . ")";
		}

		// TODO: remove transform (EPSG:3067 -> EPSG:4326)
		// TODO: test if it works...
	}


	/**
	 * Method to build the query string to LIMIT results of data by given bounding box
	 *
	 * @param string $areaField the name of the AREA field in database
	 * @param String $pointField the name of the POINT field in database
	 * @param string $box ($minLon $minLat,$maxLon $maxLat)
	 * @return string returns the "WHERE" SQL statement
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getGeometryFieldBoundingBoxQueryWhereStringFromAreaAndPoint($areaField, $pointField, $bbox) {
		/*
		 * RE-Format the given bounding box format.
		 * We read the "bounding box" from user in format: lon lat,lon lat
		 * This Postgis function however wants the bounding box in format: "lon,lat,lon,lat"
		 * --> So we will make it valid here.
		 */
		$bbox = str_replace(" ", ",", $bbox);
		$ret = " ( ";
		$ret .= $areaField." && ST_Transform(ST_MakeEnvelope(".$bbox.", ".Config::get('app.json_srid')."), ".Config::get('app.db_srid').") ";
		$ret .= " OR ";
		$ret .= $pointField." && ST_Transform(ST_MakeEnvelope(".$bbox.", ".Config::get('app.json_srid')."), ".Config::get('app.db_srid').") ";
		$ret .= " ) ";
		return $ret;
		// TODO: remove transform (EPSG:3067 -> EPSG:4326)
		// TODO: test if it works...
		//return $areaField." && ST_MakeEnvelope(".$bbox.", ".Config::get('app.db_srid').") OR ".
		//		$pointField." && ST_MakeEnvelope(".$bbox.", ".Config::get('app.db_srid').") ";
	}

	/**
	 * Method to build sql query string to order results by the given bounding box center point.
	 * The closest entities are returned first
	 *
	 * @param string $fieldName
	 * @param string $boundingBox "0 0, 60 20"
	 * @return string
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getGeometryFieldOrderByBoundingBoxCenterString($fieldName, $bbox) {
		return "ST_Transform(".$fieldName.", ".Config::get('app.db_srid').") <-> ST_Transform(ST_GeomFromText('POINT(".self::calculateBboxCenter($bbox).")', ".Config::get('app.json_srid')."), ".Config::get('app.db_srid').") ASC ";
		// TODO: remove transform (EPSG:3067 -> EPSG:4326)
		// TODO: test if it works...
		//return $fieldName." <-> ST_GeomFromText('POINT(".self::calculateBboxCenter($bbox).")', ".Config::get('app.db_srid').") ASC ";
	}
	/**
	 * Method to build sql query string to order results by the given bounding box center point.
	 * The closest entities are returned first
	 *
	 * @param string $areaField the fieldname in database that contains AREA type geometry
	 * @param string $boundingBox "0 0, 60 20"
	 * @return string
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getGeometryFieldOrderByBoundingBoxAreaAndPointCenterString($areaField, $pointField, $bbox) {
		return "ST_Transform(".$areaField.", ".Config::get('app.db_srid').") <-> ST_Transform(ST_GeomFromText('POINT(".self::calculateBboxCenter($bbox).")', ".Config::get('app.json_srid')."), ".Config::get('app.db_srid').") ASC, ".
				"ST_Transform(".$pointField.", ".Config::get('app.db_srid').") <-> ST_Transform(ST_GeomFromText('POINT(".self::calculateBboxCenter($bbox).")', ".Config::get('app.json_srid')."), ".Config::get('app.db_srid').") ASC ";
		// TODO: remove transform (EPSG:3067 -> EPSG:4326)
		// TODO: test if it works...
		//return $areaField." <-> ST_GeomFromText('POINT(".self::calculateBboxCenter($bbox).")', ".Config::get('app.json_srid').") ASC, ".
		//		$pointField." <-> ST_GeomFromText('POINT(".self::calculateBboxCenter($bbox).")', ".Config::get('app.json_srid').") ASC ";

	}

	/**
	 * Method to validate the given AREA type coordinate string
	 *
	 * @param $coordinates
	 * @return <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>|boolean
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function validateAreaCoordinates($coordinates) {

		// TODO: this now validates coordinates in EPSG:4326, it MUST be changed to the EPSG:3067 format

		/*
		 * validate the alue coordinate format
		 */
		$pairs = explode(",", $coordinates);
		foreach ($pairs as $pair) {
			$coords = explode(" ", $pair);
			// if this coordinate pair is not valid
			if( count($coords) != 2 || count(explode(".", $coords[0])) != 2 || count(explode(".", $coords[1])) != 2 ) {
				MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
				MipJson::addMessage(Lang::get('alue.coordinate_validation_failed'));
				MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
				return MipJson::getJson();
			}
		}
		// atleast 4 pairs must be given, also there must be even (pair) amount of coordinates
		if(count($pairs)%2 != 0 || count($pairs) < 4) {
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::addMessage(Lang::get('alue.coordinate_validation_failed_4pairs_required'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}
		// first and last point of the area MUST be same.
		if($pairs[0] != $pairs[count($pairs)-1]) {
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::addMessage(Lang::get('alue.coordinate_validation_failed_first_and_last_must_match'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}
		return true;
	}

	/**
	 * Method to calculate the center point of given bounding box
	 *
	 * @param String $bbox (minLon minLat,maxLon maxLat)
	 * @return string Returns the center point of given bounding box (in format: lon lat)
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	private static function calculateBboxCenter($bbox) {

		// TODO: this now calculates the center point of given box coordinates in EPSG:4326, it MUST be changed to the EPSG:3067 format

		/*
		 * Calculate the center point of given bbox
		 *
		 * For example input would be: "21.900000 60.910000,22.000000 61.000000"
		 * And then center point is calculated:
		 * 21.900000+22.000000/2 = 21.95
		 * 60.910000+61.000000/2 = 60.955
		 */
		$points = explode(",", $bbox);
		$lon_center = (explode(" ", $points[0])[0]+explode(" ", $points[1])[0])/2;
		$lat_center =  (explode(" ", $points[0])[1]+explode(" ", $points[1])[1])/2;

		return $lon_center." ".$lat_center;
	}

	public static function getGeometryAsText($geometry) {
		if (is_null($geometry) || $geometry=="") {
			return "";
		}
		// TODO: if geometry is invalid (can it be?) then this will throw exception.
		$geom = DB::selectOne("SELECT ST_AsText(?) as value", array($geometry));
		if ($geom) {
			return $geom->value;
		}
		return "";
	}
	/**
	 * In: Point array, e.g. [$lat, $lon]
	 * Out: String, e.g. "$lat $lon"
	 */
	public static function extractPointPoints($geometry) {

		//$lat = $geometry['coordinates'][0];
		//$lon = $geometry['coordinates'][1];

		return implode(" ", $geometry);
	}

	public static function extractPolygonPoints($geometry) {

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
	public static function convertTextToGeom($geomType, $geomAsText) {
		if($geomType == 'Point') {
			$statement = "select ST_GeomFromText('".$geomType ."(".$geomAsText.")', 3067)";
			$geom = DB::select($statement);
			return $geom[0]->st_geomfromtext;
		} else if($geomType == 'Polygon') {
			$statement = "select ST_GeomFromText('".$geomType ."(".$geomAsText.")', 3067)";
			$geom = DB::select($statement);
			return $geom[0]->st_geomfromtext; //TODO Not tested yet.
		}


	}

	/**
	 *
	 * @param int $from
	 * @param int $to
	 * @param $coords , Esim: POLYGON((6707025.3649801 241193.1276022, 6707015.8532057 241204.8661964, 6707025.3649801 241193.1276022))
	 */
	public static function transformSSRID($from, $to, $coords) {
		$statement = "select ST_AsText(ST_Transform(ST_GeomFromText('".$coords."', ".$from."), ".$to.")) as coords";
		$result = DB::select($statement);
		return $result[0]->coords;
	}

	/**
	 *
	 * @param $coords , Esim: 6707025.3649801 241193.1276022
	 */
	public static function flipCoords($coords) {
		$statement = "select ST_AsText(ST_FlipCoordinates(ST_GeomFromText('".$coords."'))) as coords";
		$result = DB::select($statement);
		return $result[0]->coords;
	}

	/**
	 *
	 * @param $wkt , Esim: POLYGON((6707025.3649801 241193.1276022, 6707015.8532057 241204.8661964, 6707025.3649801 241193.1276022))
	 * @return
	 */
	public static function asGeoJson($wkt) {
		$statement = "select ST_AsGeoJSON('".$wkt."') as coords";
		$result = DB::select($statement);
		return $result[0]->coords;
	}
}