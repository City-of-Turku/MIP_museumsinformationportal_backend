<?php

namespace App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App;

class Utils {
	
	public static function setDBUser() {
		DB::statement("SET LOCAL application.userid='".Auth::user()->id."'");
	}
	
	public static function setDBUserAsSystem() {
		DB::statement("SET LOCAL application.userid='-1'");
	}
	
	public static function getSystemUserId() {
	    return -1;
	}
	
	/**
	 * Laskee rivien määrän ennen rivien varsinaista hakua
	 */
	public static function getCount($e) {
		$rowCount = DB::select('select count(*) from ('.$e->toSql().') as x', $e->getBindings());
		return $rowCount[0]->count;
	}
	
	/**
	 * Palauttaa sql:n mukaiset id:t listana
	 */
	public static function getIdLista($e) {
	    $idResult = DB::select('select id from ('.$e->toSql().') as x', $e->getBindings());
	    $idLista = [];
	    
	    // Tulosjoukko on stdClass array häkkyröitä, jotka käydään läpi
	    if($idResult){
	        foreach ($idResult as $row => $innerArray) {
	            foreach ($innerArray as $innerRow => $value) {
	                $idLista[] = $value;
	            }
	        }
	    }
	    return $idLista;
	}
	
	/*
	 * Write selected fields from a select statement to a file, for example datamigration logging.
	 * Output file is written to Laravel storage/app folder. App folder will be created if doesn't exist.
	 * Use unique filename or else existing file will be overwritten.
	 * Example: 
	 * 		$data = DB::select('select k.id, k.kiinteistotunnus from kiinteisto k;'); 
	 * 		Utils::logToFile('example.txt', $data);
	 * Parameters:
	 * $filename: The name that the file will be assigned.
	 * $data: Result of a DB::select() statement;
	 * $fieldDelimiter: Char to delimit the selected fields. (optional).
	 * $rowDelimiter: Char to delimit each of the separater records. (optional).
	 */
	public static function logToFile($filename, $data, $fieldDelimiter = "|", $rowDelimiter = "\n") {
		$dataToLog = "";
		foreach($data as $row) {
			foreach($row as $field => $value) {
				$dataToLog .= $value . $fieldDelimiter;
			}
			$dataToLog .= $rowDelimiter;
		}
		Storage::put($filename, $dataToLog);
	}
	
	/**
	 * 
	 * @param string $field_name - Kentän nimi joka halutaan
	 * @return string - Lisätään kentän nimeen _kieli localen mukaan
	 */
	public static function getLocalizedfieldname($field_name) {
		
		if (App::getLocale()=="fi") {
			return $field_name."_fi";
		}
		if (App::getLocale()=="en") {
			return $field_name."_en";
		}
		if (App::getLocale()=="se") {
			return $field_name."_se";
		}
		return $field_name."_fi";
	}
	
	public static function array_flatten($array = null) {
		$result = array();
		
		if (!is_array($array)) {
			$array = func_get_args();
		}
		
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$result = array_merge($result, array_flatten($value));
			} else {
				$result = array_merge($result, array($key => $value));
			}
		}
		
		return $result;
	}
	
}