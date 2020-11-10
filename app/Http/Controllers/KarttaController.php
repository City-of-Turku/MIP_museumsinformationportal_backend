<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class KarttaController extends Controller {
	public function proxy($taso, Request $request) {
	    $client = new Client ();
		
		$username = config('app.mml_wmts_username');
		$pass = config('app.mml_wmts_password');
		
		/*
		 * Get the query from the request. Contains the parameters set automatically by OpenLayers.
		 */
		$params = array ();
		
		foreach ( $request->input () as $key => $value ) {
			$params [$key] = $value;
		}
		
		/*
		 * Get the correct url for the layer
		 */		
		switch ($taso) {
			case 'maastokartta':
			case 'taustakartta':
			case 'ortokuva':
			case 'korkeusmalli_vinovalo':
			case 'korkeusmalli_korkeusvyohyke':
				$url = config('app.mml_maastokartat_url');
				break;
			case 'kiinteistotunnukset':
			case 'kiinteistojaotus':
				$url = config('app.mml_kiinteistokartat_url');
				break;
			case 'kuntarajat':
			case 'korkeus':
			case 'rakennukset':
			case 'TM35_lehtijako':
			case 'osoitteet':
			case 'paikannimet':
				$url = config('app.mml_teemakartat_url');
				break;
		}		
		
		
		/*
		 * Make the request.
		 * Catch ClientExceptions to avoid 404 responses filling the logs.
		 */
		try {
    		$res = $client->request ( 'GET', $url, [ 
    				'query' => $params,
    				'auth' => [ 
    						$username,
    						$pass 
    				] 
    		] );
		} catch(ClientException $e) {
		    // A GuzzleHttp\Exception\ClientException is thrown for 400 level errors
		    return response('', 404);
		}
		
		return response ( $res->getBody () );
	}
	
	public function featureInfoProxy(Request $request) {
		$client = new Client ();
		
		$url = $request->input('featureinfourl');
				
		/*
		 * Make the request
		 */
		$res = $client->request ( 'GET', $url);
		
		$jsonData = ['data' => "'" . $res->getBody() . "'"];
		
		return response(json_encode($jsonData));
	}
}

