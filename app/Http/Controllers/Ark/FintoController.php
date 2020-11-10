<?php

namespace App\Http\Controllers\Ark;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;

class FintoController extends Controller
{
	/**
	 * Asiasanasto Finto API.
	 * http://api.finto.fi/
	 * $vocab: The vocabulary to use (e.g. 'maotao')
	 * $lang The language to use (e.g. 'fi')
	 * $query The search term (e.g. 'kiss*')
	 * 
	 * @param $vocab 
	 * @param $lang 
	 * @param $query 
	 */
	public function query($vocab, $lang, $query) {

		$client = new Client();
		$res = $client->request('GET', 'http://api.finto.fi/rest/v1/search', [
				'query' => ['vocab' => $vocab, 'lang' => $lang, 'query' => $query]
		]);

		return response($res->getBody());
	}
}
