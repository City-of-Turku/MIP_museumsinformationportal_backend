<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;

class KayttoohjeController extends Controller {
	
	public static function getKayttoohje(Request $request) {			
		
		$url = config('app.mip_kayttoohje_url');
		
		$client = new \GuzzleHttp\Client();
		$res = $client->request("GET", $url);
		
		if ($res->getStatusCode()!="200") {
			throw new Exception("Getting report file failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
		}
		
		$ohje =  $res->getBody();	
		
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename=ohje.pdf');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . strlen($ohje));
		header('Content-Type: application/pdf');
		
		return $ohje;
	}	
}