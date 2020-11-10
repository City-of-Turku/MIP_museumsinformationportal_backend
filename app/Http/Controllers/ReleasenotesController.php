<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Exception;

class ReleasenotesController extends Controller {
	
	public static function getReleasenotes(Request $request) {
					
		$url = config('app.mip_releasenotes_url');
		
		$client = new Client();
		$res = $client->request("GET", $url);
		
		if ($res->getStatusCode()!="200") {
			throw new Exception("Getting report file failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
		}
		
		$rn =  $res->getBody();	
		
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename=releasenotes.html');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . strlen($rn));
		header('Content-Type: text/html');
		
		return $rn;
	}	
}