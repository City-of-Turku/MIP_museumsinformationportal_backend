<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;



/*
 * NOT IN USE!
 */





class MigrationControllerPhotoTable extends Controller {

	private $END_CHAR = "<br>";
	private $LIMIT = false; // how many rows to fetch and handle from lougis.mip_photo. false = no limit
	private $INSERT_FIELD = "ref_row_ids"; // field in "lougis.mip_photo" table where we insert the id value of access_97.rakennukset/.kiinteistöt table(s)
	private $error_ids = array(); // array to hold lougis.mip_photo.id fields with incorrect ref_row_ids field values
	private $error_updates = array(); // failed sql statements (while updating mip_photo)
	private $success_updates = array(); // success sql statements (while updatint mip_photo)


	/**
	 * Create a new controller instance.
	 *
	 * @return void
	*/
	public function __construct(){
		$this->middleware('guest');
	}

	/**
	 * Show the application welcome screen to the user.
	 *
	 * @return Response
	 */
	public function index(){

		

		$schemas = self::getRefSchemas();
		print "<b>Found ".count($schemas)." Schemas:</b>".$this->END_CHAR;
		self::printArray($schemas);
		print "---".$this->END_CHAR.$this->END_CHAR;

		foreach($schemas as $schema) {
			$tables = self::getRefTables($schema);
			print "<b>Found ".count($tables)." Tables for schmema: ".$schema."</b>".$this->END_CHAR;
				
			foreach($tables as $table) {

				$keys = self::getRefKeys($schema, $table);
				print "TABLE: '<b>".$table."</b>' keys (".count($keys)."):".$this->END_CHAR;

				foreach($keys as $key) {
					print $key.$this->END_CHAR;
				}
				print $this->END_CHAR;
			}
		}
		print "==========================".$this->END_CHAR.$this->END_CHAR;

		self::listOldRefs();


	}

	private function listOldRefs() {
		DB::enableQueryLog();
		$data = array();

		/*
		 * get ALL the images with "old" ref system
		 */
		$statement = "SELECT id,ref_schema,ref_table,ref_primary_keys as fields, ref_row_ids as vals ".
				"FROM lougis.mip_photo ".
				"WHERE ref_primary_keys != ? ".
				"ORDER BY ref_primary_keys DESC ";
		$statement .= ($this->LIMIT != false) ? "LIMIT ".$this->LIMIT : "";
		$results = self::resultToMultiArray(DB::select($statement, array('id')));
		print "<b>Found ".count($results)." rows with OLD REF</b>".$this->END_CHAR.$this->END_CHAR;


		/*
		 * go through the results and do the magic...
		 */
		foreach($results as $row) {
				
			$parts 				= explode(";", $row['fields']);
			$municipality_key 	= $parts[0];
			$property_key		= (!empty($parts[1])) ? $parts[1] : "";
			$building_key		= (!empty($parts[2])) ? $parts[2] : "";
				
			$parts 				= explode(";", $row['vals']);
			$municipality_val	= $parts[0];
			$property_val		= (!empty($parts[1])) ? $parts[1] : "";
			$building_val		= (!empty($parts[2])) ? $parts[2] : "";
				
			/*
			 * get data from given schema/table
			 * within given keys...
			 */
			if(!empty($municipality_val) && !empty($property_val) && !empty($building_val)) {
				$res = DB::table($row['ref_schema'].".".$row['ref_table'])
				->select(DB::raw('count(*)'))
				->select(DB::raw('*'))
				->where($municipality_key, '=', $municipality_val)
				->where($property_key, '=', $property_val)
				->where($building_key, '=', $building_val)
				->get();
			}
			else if(!empty($municipality_val) && !empty($property_val)) {
				$res = DB::table($row['ref_schema'].".".$row['ref_table'])
				->select(DB::raw('count(*)'))
				->select(DB::raw('*'))
				->where($municipality_key, '=', $municipality_val)
				->where($property_key, '=', $property_val)
				->get();
			}
			else
				array_push($this->error_ids, $row['id']."::".$row['fields']."::".$row['vals']);
				
			/*
			 * insert results and query to be executed
			*/
			if(count(explode(";",$row['fields'])) > 0)
				array_push($data, array(
						"lougis.mip_photo.ref_row_ids"=>$row['fields'],
						"lougis.mip_photo.ref_primary_keys"=>$row['vals'],
						$row['ref_schema'].".".$row['ref_table'].".id"=>$this->resultToMultiArray($res)[0]['id'],
						"sql"=>"UPDATE lougis.mip_photo SET ".$this->INSERT_FIELD." = '".$this->resultToMultiArray($res)[0]['id']."' ".
						"WHERE id = '".$row['id']."' ".
						"AND ref_row_ids = '".$row['vals']."' ".
						"AND ref_primary_keys = '".$row['fields']."' ",
						"sql2"=>"UPDATE lougis.mip_photo SET ref_primary_keys = 'id' WHERE id = '".$row['id']."' "
				));
					
				/********
				 $data2 = self::resultToMultiArray($res);
				 print "id: ".$row['id'].$this->END_CHAR;
				 print "schema: ".$row['ref_schema'].".".$row['ref_table'].$this->END_CHAR;
				 print "fields: ".$row['fields'].$this->END_CHAR;
				 print "values: ".$row['vals']." (".$municipality_val.",".$property_val.",".$building_val.")".$this->END_CHAR;
				 print "result rows (".count($res)."): ".$this->END_CHAR;
				 print "result id: ".$this->resultToMultiArray($res)[0]['id'].$this->END_CHAR;
				 print $this->END_CHAR;
				/******/
		}

		/*
		 * List ALL found items with OLD ref system
		 */
		print "<b>Found ".count($data)." items that MATCH items with OLD REF:</b> ".$this->END_CHAR;
		print $this->END_CHAR.$this->END_CHAR;

		//print "<pre>";
		//print_r($data);
		foreach($data as $array) {
				
				
			/*
			 * - update lougis.mip_photo.ref_row_ids = access_97.rakennukset/kiinteistöt.id  from ref table
			 * - update lougis.mip_photo.ref_primary_keys = 'id'
			 */
			if(DB::update($array['sql']) > 0 && DB::update($array['sql2']) > 0) {
				array_push($this->success_updates, $array['sql']);
				array_push($this->success_updates, $array['sql2']);
				//print "<font color='green'>OK</font><br>";
			}
			else {
				array_push($this->error_updates, $array['sql']);
				//print "<font color='red'>ERROR</font><br>";
			}
		}

		/*
		 * Show the failed / succeed UPDATE queries
		 */
		print "<font color='red'>Failed UPDATE queries: ".count($this->error_updates);
		print "<pre>";
		print_r($this->error_updates);
		print "</pre></font>";

		print "<font color='green'>Succeed UPDATE queries: ".count($this->success_updates);
		print "<pre>";
		print_r($this->success_updates);
		print "</pre></font>";

		/*
		 * Finally:
		 * print lougis.mip_photo.id values which has ref_row_ids with incorrect values
		 */
		if(count($this->error_ids) > 0) {
			print "<b>lougis.mip_photo</b> rows with incorrect 'ref_row_ids' value: ".$this->END_CHAR;
			foreach($this->error_ids as $key => $value) {
				$parts = explode("::", $value);
				$id = $parts[0];
				$keys = $parts[1];
				$vals = $parts[2];
				print $id.": ".$keys." = ".$vals.$this->END_CHAR;
			}
		}


		//return $data;
		//return view('welcome');
	}

	private function getRefSchemas() {
		$results = self::resultToMultiArray(DB::table('lougis.mip_photo')
				->distinct()
				->select(DB::raw('ref_schema'))
				->where('ref_primary_keys', '!=', 'id')
				->get());

		return self::resultToSingleArray($results);
	}

	private function getRefTables($schema) {
		$results = self::resultToMultiArray(DB::table('lougis.mip_photo')
				->distinct()
				->select(DB::raw('ref_table'))
				->where('ref_schema', '=', $schema)
				->where('ref_primary_keys', '!=', 'id')
				->get());

		return self::resultToSingleArray($results);
	}

	private function getRefKeys($schema, $table) {
		$results = self::resultToMultiArray(DB::table('lougis.mip_photo')
				->distinct()
				->select(DB::raw('ref_primary_keys'))
				->where('ref_schema', '=', $schema)
				->where('ref_table', '=', $table)
				->where('ref_primary_keys', '!=', 'id')
				->get());

		return self::resultToSingleArray($results);
	}

	private function printArray(array $results) {
		foreach($results as $key => $value) {
			print $key.": ".$value.$this->END_CHAR;
		}
	}

	private function resultToMultiArray(array $results) {
		/*
		 * convert sql results as an array
		 */
		$data = array();
		foreach($results as $rowNo => $row)
			foreach($row as $key => $value)
				$data[$rowNo][$key] = $value;
				
			return $data;
	}

	private function resultToSingleArray(array $results) {
		/*
		 * convert sql results as an array
		 */
		$data = array();
		foreach($results as $rowNo => $row)
			foreach($row as $key => $value)
				array_push($data, $value);

			return $data;
	}

}
?>
