<?php
namespace App\Library\String;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;

/**
 * MipJson - a class to help formatting the system wide json object easily
 *
 * @author
 * @version 1.0
 * @since 1.0 - 01.09.2015
 */
class MipJson {

	/**
	 * Class member to hold the messages of requested operation
	 *
	 * @since 1.0
	 * @author
	 * @var array $errors
	 */
	private static $messages = array();

	/**
	 * Class member to hold the data of requested operation
	 *
	 * @since 1.0
	 * @author
	 * @var array $data
	 */
	private static $data = array();

	/**
	 * Class member to hold tht data_format type of MipJson object
	 *
	 * @since 1.0
	 * @author
	 * @var String $data_format
	 */
	private static $data_format = "json"; // possible values: json/geojson

	/**
	 * Class member to hold data_size value
	 *
	 * @since 1.0
	 * @author
	 * @var int $data_size
	 */
	private static $data_size = null;

	/**
	 * Class member to hold the RESPONSE status code
	 *
	 * @since 1.0
	 * @author
	 * @var String $status_code
	 */
	private static $status_code = 200;

	/**
	 * Constructor of the class
	 *
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function __construct() {
	}

	/**
	 * Method to return formatted json from class members
	 *
	 * @return array
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getJson() {

		$json = array(
			'data'				=> self::$data,
			'response'			=> array(
				'format' 	=> self::$data_format,
				'message' 	=> self::$messages
			),
		);

		//$json['data']['count'] = self::$data_size;

		/*
		 * If Api-Debug header is set, return the debug info
		 */
		if(Request::header('Api-Debug') == "true") {

			$json['debug']	= array(

				'header' => array(
					'Accept-Language'	=> Request::header("Accept-Language"),
					'Api-Version'		=> Request::header('Api-Version'),
					'Api-Debug'			=> Request::header('Api-Debug'),
					'Authorization'		=> Request::header('Authorization'),
					//getAllHeaders(),
				),

				//'header' => getAllHeaders(),
				'request'				=> array_merge(
					Request::all(),
					array('Request-Method'  => Request::method())
				),
				'using' => array(
					'Accept-Language' => App::getLocale(),
					'Api-Version'	  => Config::get('api_version'),
					'Authorization'	  => Request::bearerToken(),
				),
			);
		}

		return response($json, self::$status_code);
	}

	public static function getData() {
		return self::$data;
	}

	/**
	 * Method to set the http response "status code"
	 *
	 * @param int $status_code
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function setResponseStatus($status_code) {
		self::$status_code = $status_code;
	}

	/**
	 * Method to set the idlist to the response data->idlist
	 *
	 * @param int $status_code
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function setIdList($list) {
	    self::$data['idlist'] = $list;
	}

	/**
	 * Setter to set class member value
	 *
	 * @param array $data
	 * @param boolean $show_datalen
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function setData(array $data, $count=0, $total_count=0) {

		// old version (until 12.04.2016)
		//self::$data = $data;

		// new version... format is equal to geojson
		self::$data = array("count" => $count, "total_count" => $total_count, "content" => $data);
	}

	/**
	 * Setter to set class member value
	 *
	 * @param int $data_size
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function setDataSize($data_size) {
		self::$data_size = $data_size;
	}

	/**
	 * Setter to set class member value
	 *
	 * @param String $data_format
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function setDataFormat($data_format) {
		self::$data_format = $data_format;
	}

	/**
	 * Setter to set class member value
	 *
	 * @param array $messages
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function setMessages(array $messages) {
		self::$messages = $messages;
	}

	/**
	 * Method to build geojson response data
	 *
	 * @param array $geometry
	 * @param $properties
	 * @param string $crs_name
	 * @author
	 * @version 1.0
	 * @since
	 */
	public static function setGeoJsonFeature($geometry=null, $properties=null, $crs_name=null) {
		self::setDataFormat("geojson");
		self::$data = array(
			"type" 			=> "Feature",
			"geometry" 		=> $geometry,
			"properties" 	=> $properties,
			"crs"			=> array("type" => "name","properties" => array("name" => (is_null($crs_name)) ? getenv("DEFAULT_CRS_NAME") : $crs_name))
		);
	}

	/**
	 * Method to build geojson containing GeometryCollection response data
	 *
	 * @param array $geometry
	 * @param $properties
	 * @param string $crs_name
	 * @author
	 * @version 1.0
	 * @since
	 */
	public static function setGeoJsonFeaturewithGeometryCollection($geometries=null, $properties=null, $crs_name=null) {
		self::setDataFormat("geojson");

		$geom = [];
		foreach($geometries as $g) {
			array_push($geom, $g->sijainti);
		}

		self::$data = array(
				"type" 			=> "Feature",
				"geometry" 		=> array('geometries' => $geom, 'type' => 'GeometryCollection'),
				"properties" 	=> $properties,
				"crs"			=> array("type" => "name","properties" => array("name" => (is_null($crs_name)) ? getenv("DEFAULT_CRS_NAME") : $crs_name))
		);
	}

	/**
	 * Method to initialize the $data array as geojson featurecollection
	 *
	 * @author
	 * @version 1.0
	 * @since 1.0
	 * @param int $count The total num in dataset
	 * @param int $total_count The total num of rows (including the ones not shown)
	 */
	public static function initGeoJsonFeatureCollection($count=0, $total_count=0) {
		self::setDataFormat("geojson");

		self::$data = array(
			"type" => "FeatureCollection",
			"count" => ($count) ? $count : 0,
			"total_count" => ($total_count) ? $total_count : 0,
			"features" => array()
		);
	}

	public static function addMessage($message) {
		array_push(self::$messages, $message);
	}

	/**
	 * Method to add new geojson "feature" into features array of FeatureCollection
	 *
	 * @param String $feature_type (Point, LineString, Polygon)
	 * @param array $coordinates
	 * @param array $properties
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function addGeoJsonFeatureCollectionFeature($feature_type, $coordinates, $properties, $crs_name=null) {
		array_push(self::$data['features'],
			array(
				"type" => "Feature",
				"geometry" => array(
					"type" => $feature_type,
					"coordinates" => $coordinates
				),
				"properties" => $properties,
				"crs"		=> array("type" => "name","properties" => array("name" => (is_null($crs_name)) ? getenv("DEFAULT_CRS_NAME") : $crs_name))
			)
		);
	}

	/**
	 * Method to add new geojson "feature" (Point) into features array of FeatureCollection
	 *
	 * @param String $geometry
	 * @param array $properties
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function addGeoJsonFeatureCollectionFeaturePoint($geometry=null, $properties, $crs_name=null) {
		array_push(self::$data['features'],
			array(
				"type" => "Feature",
				"geometry" => $geometry,
				"properties" => $properties,
				"crs"		=> array("type" => "name","properties" => array("name" => (is_null($crs_name)) ? getenv("DEFAULT_CRS_NAME") : $crs_name))
			)
		);
	}

	/**
	 * Method to add a geojson GeometryCollection to feature
	 */
	public static function addGeometryCollectionToFeature($feature_type, $geometries, $properties, $crs_name=null) {
		$geom = [];
		foreach($geometries as $g) {
			array_push($geom, $g->sijainti);
		}

		array_push(self::$data['features'],
				array(
					"type" => "Feature",
					"geometry" => array(
							"type" => "GeometryCollection",
							"geometries" => $geom
					),
					"properties" => $properties,
					"crs"		=> array(
							"type" => "name",
							"properties" => array(
									"name" => (is_null($crs_name)) ? getenv("DEFAULT_CRS_NAME") : $crs_name)
					)
				)
			);
	}

}

?>