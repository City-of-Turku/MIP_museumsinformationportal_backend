<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;

class SolrController extends Controller {
	/**
	 * A simple proxy to the SOLR API.
	 */
	public function query(Request $request) {
		// params & default values
		$kentta = isset ( $request->kentta ) ? $request->kentta : "combinedSearch";
		$hakusana = isset ( $request->hakusana ) ? $request->hakusana : "*";
		$rivit = isset ( $request->rivit ) ? $request->rivit : 10;
		$rivi = isset ( $request->rivi ) ? $request->rivi : 0;
		$tyyppi = isset ( $request->tyyppi ) ? $request->tyyppi : "";
		
		// this will hold the q parameter sent to Solr
		$q = "";
		
		// split $hakusana into an array
		$pieces = explode(" ", $hakusana);
		
		// construct $q from pieces
		for ($i = 0; $i < count($pieces); $i ++) {
			if ($i > 0) {
				$q .= " OR ";
			}
			
			$q .= "$kentta:$pieces[$i]";
		}
		
		// make certain $q ends with "*"
		if (substr($q, -1) !== "*") {
			$q .= "*";
		}
		
		// if $tyyppi is not empty, handle it
		if (strlen($tyyppi) > 0) {
			// temporary variable for appending type to $q
			$type = "";
			
			// split $tyyppi into an array
			$pieces = explode(",", $tyyppi);
			
			for ($i = 0; $i < count($pieces); $i ++) {
				if ($i > 0) {
					$type .= " OR ";
				}
				
				$type .= "doc_type:$pieces[$i]";
			}
			
			// if a type is specified, add it to $q
			if (strlen($type) > 0) {
				$q = "($type) AND ($q)";
			}
		}
		
		// invoke Solr
		$client = new Client ();
		$res = $client->request ( "GET", Config::get ( "app.solr_addr" ) . "query", [ 
				"query" => [ 
						"q" => $q,
						"rows" => $rivit,
						"start" => $rivi,
						"indent" => "on",
						"wt" => "json" 
				] 
		] );
		
		return response ( $res->getBody () );
	}
}
