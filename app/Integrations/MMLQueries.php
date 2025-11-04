<?php
namespace App\Integrations;

use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Log;

class MMLQueries {

	private static function parseKiinteistoTunnus($rawJSONString) {
		$data_array = array();
		$data = json_decode($rawJSONString);
		if($data->features) {
			foreach($data->features as $kiinteisto) {
				$ktunnus = (string)$kiinteisto->properties->kiinteistotunnus;

				if ($ktunnus!="") {
					$result['kiinteistotunnus'] = substr($ktunnus, 0, 3). "-". substr($ktunnus, 3, 3). "-".  substr($ktunnus, 6, 4). "-".  substr($ktunnus, 10, 4);
				} else {
					$result['kiinteistotunnus'] = "";
				}
				array_push($data_array, $result);
			}
		}
		return $data_array;
	}

	private static function elementsAsString($elementArray) {
		$ret = "";
		foreach ($elementArray as $elem) {
			$ret .= (string)$elem;
		}
		return $ret;
	}

	private static function parseKiinteistoTiedot($rawXmlString) {

		$kiinteisto = array(); // the returned kiinteisto

		$xml = new \SimpleXmlElement($rawXmlString);
		$xml->registerXPathNamespace('kylh', "http://xml.nls.fi/ktjkir/kysely/lainhuutotiedot/2017/02/01");
		$xml->registerXPathNamespace('trlh', "http://xml.nls.fi/ktjkir/lainhuutotiedot/2017/02/01");
		$xml->registerXPathNamespace('y', 'http://xml.nls.fi/ktjkir/yhteinen/2014/10/01');

		// kiinteiston nimi, kuntanumero, kuntnan nimi
		$expression  = '/kylh:Lainhuutotiedot/kylh:Rekisteriyksikko/trpt:rekisteriyksikonPerustiedot';
		$nodes = $xml->xpath( $expression );
		foreach ($nodes as $node) {
			// should be only one, but loop anyways..

			$kiinteisto['kiinteistotunnus'] = "";
			$ktunnus = self::elementsAsString($node->xpath('y:kiinteistotunnus'));

			$kiinteisto['kiinteistotunnus'] = substr($ktunnus, 0, 3). "-". substr($ktunnus, 3, 3). "-".  substr($ktunnus, 6, 4). "-".  substr($ktunnus, 10, 4);

			$kiinteisto['nimi'] = self::elementsAsString($node->xpath('trpt:nimi'));
			$kiinteisto['kuntanumero'] = self::elementsAsString($node->xpath('trpt:kuntaviittaus/trpt:kunta/trpt:kuntatunnus'));
			$kiinteisto['kuntanimi_fi'] = self::elementsAsString($node->xpath('trpt:kuntaviittaus/trpt:kunta/trpt:nimi[@kieli="fi"]'));
			$kiinteisto['kuntanimi_se'] = self::elementsAsString($node->xpath('trpt:kuntaviittaus/trpt:kunta/trpt:nimi[@kieli="sv"]'));
		}

		// omistajat
		$expression  = '/kylh:Lainhuutotiedot/kylh:Rekisteriyksikko/trlh:lainhuudot/trlh:Lainhuutoasia';
		$expression .= '/trlh:osuudetAsianKohteesta/trlh:OsuusAsianKohteesta/y:osuudenHenkilot/y:Henkilo/y:henkilonTiedot';
		// TODO: xpathiin tarvitsee filttereitä?

		$nodes = $xml->xpath( $expression );

		$omistajat = array();
		$omistajatkokonimi = array(); // for duplicate checking
		$ytunnukset = array();
		$nimet = array();

		/*
		* Omistajatiedot parsitaan henkilölajin perusteella
		* Kaikki tiedot asetataan etunimet ja sukunimet kenttään, jotta selvitään ilman UI-muutoksia
		* React-versiota varten tiedot on myös järkevämmissä kentissä
		* Kuolinpesän toimintaa ei ole saatu varmistettua
		* Tuntematon henkilö TU on vielä epävarma, siksi lokitus
		*/

		$omistajaLoydetty = false;
		foreach ($nodes as $node) {
			$henkilolaji = self::elementsAsString($node->xpath('y:henkilolaji'));
			$omistajaLoydetty = true;
			switch ($henkilolaji){
				case "LU": //Luonnollinen henkilö
					$sukunimi = self::elementsAsString($node->xpath('y:sukunimi'));
					$etunimet = self::elementsAsString($node->xpath('y:etunimet'));
					$kokonimi = $sukunimi." ".$etunimet;

					if (trim($kokonimi)!= '' && !in_array($kokonimi, $omistajatkokonimi)) {

						$omistaja = array();
						$omistaja['etunimet'] = $etunimet;
						$omistaja['sukunimi'] = $sukunimi;
						array_push($omistajat, $omistaja);

						array_push($omistajatkokonimi, $kokonimi);
					}
					break;
				case "JU": //Juridinen henkilö
					$ytunnus = self::elementsAsString($node->xpath('y:ytunnus'));
					$nimi = self::elementsAsString($node->xpath('y:nimi'));
					if ((trim($ytunnus)!= '' && trim($nimi)!= '') || (!in_array($ytunnus, $ytunnukset) || !in_array($nimi, $nimet))) {
						$omistaja = array();
						$omistaja['etunimet'] = $ytunnus;
						$omistaja['sukunimi'] = $nimi;
						$omistaja['ytunnus'] = $ytunnus;
						$omistaja['nimi'] = $nimi;
						array_push($omistajat, $omistaja);

						array_push($ytunnukset, $ytunnus);
						array_push($nimet, $nimi);
					}

				break;
				case "VA": //Valtio
					$nimi = self::elementsAsString($node->xpath('y:nimi'));
					if (trim($nimi)!= '' && !in_array($nimi, $nimet)) {
						$omistaja = array();
						$omistaja['sukunimi'] = $nimi;
						$omistaja['nimi'] = $nimi;
						array_push($omistajat, $omistaja);
					}
				break;
				case "KP": //Kuolinpesä (ei varmuutta toiminnasta) lokitetaan varmuuden vuoksi
					$sukunimi = self::elementsAsString($node->xpath('y:sukunimi'));
					$etunimet = self::elementsAsString($node->xpath('y:etunimet'));
					$kokonimi = $sukunimi." ".$etunimet;

					if (trim($kokonimi)!= '' && !in_array($kokonimi, $omistajatkokonimi)) {

						$omistaja = array();
						$omistaja['etunimet'] = $etunimet;
						$omistaja['sukunimi'] = $sukunimi;
						array_push($omistajat, $omistaja);

						array_push($omistajatkokonimi, $kokonimi);
					}
					Log::channel('mml')->error("Omistajatiedot failed: Henkilölaji: " . $henkilolaji ." Kiinteistötunnus: " .$kiinteisto['kiinteistotunnus']);
					break;
				case "TU": //Tuntematon henkilö (ei varmuutta toiminnasta)
					$nimi = self::elementsAsString($node->xpath('y:nimi'));
					if (trim($nimi)!= '' && !in_array($nimi, $nimet)) {
						$omistaja = array();
						$omistaja['sukunimi'] = $nimi;
						$omistaja['nimi'] = $nimi;
						array_push($omistajat, $omistaja);
					}
					Log::channel('mml')->error("Omistajatiedot failed: Henkilölaji: " . $henkilolaji ." Kiinteistötunnus: " .$kiinteisto['kiinteistotunnus']);
				break;
			}


		}
		if ($omistajaLoydetty == false){
			$omistaja = array();
			$omistaja['sukunimi'] = "Omistajatietoa ei löydy";
			array_push($omistajat, $omistaja);
			$kiinteisto['omistajat'] = $omistajat;
		}
		$kiinteisto['omistajat'] = $omistajat;

		return $kiinteisto;
	}

	public static function getKiinteistoTiedot_byHttpGET($bbox) {
		// getting the kiinteistotiedot by http GET, this does not work for some reason..
		// no time to find out why, the POST works (funtion getKiinteistoTunnusByPoint)
		$url = config('app.mml_kiinteistotiedot_url');
		$username = config('app.mml_kiinteistotiedot_username');
		$password = config('app.mml_kiinteistotiedot_password');

		$client = new Client();
		$res = $client->request("GET", $url, [
				"query" => [
					"SERVICE" 		=> "WFS",
					"REQUEST" 		=> "GetFeature",
					"VERSION" 		=> "1.1.0",
					"NAMESPACE" 	=> "xmlns(ktjkiiwfs=http://xml.nls.fi/ktjkiiwfs/2010/02)",
					"TYPENAME" 		=> "ktjkiiwfs:RekisteriyksikonTietoja",
					"SRSNAME" 		=> "EPSG:3067",
					"MAXFEATURES" 	=> "3",
					"RESULTTYPE" 	=> "results",
					"EPSG" 			=> "3067",
					"BBOX" 			=> $bbox
					]
				,
				"auth" => [
					$username, $password
				]
		]);

		if ($res->getStatusCode()!="200") {
			throw new Exception("KiinteistoTiedot failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
		}

		return self::parseKiinteistoTiedot($res->getBody());
	}

	/**
	 * Get the kiinteistotunnus of kiinteistos located in a point
	 * Returns an array of kiinteistos where there is 'kiinteistotunnus' key
	 *
	 * @param $point
	 * @throws Exception
	 */
	public static function getKiinteistoTunnusByPoint($point) {
		if (!empty(config('app.mml_kiinteistotiedot_username')) && !empty(config('app.mml_kiinteistotiedot_password'))){
			$filter = "filter=S_INTERSECTS(geometry,POINT(" .$point ."))";
			$url = config('app.mml_kiinteistotiedot_url') . $filter;
			$username = config('app.mml_kiinteistotiedot_username');
			$password = config('app.mml_kiinteistotiedot_password');
		}
		else{
			$margin = 50;
			$lat = explode(" ", $point)[0];
			$lon = explode(" ", $point)[1];
			$bbox = implode(",", [$lat+$margin, $lon+$margin, $lat-$margin, $lon-$margin]);
			$url = config('app.mml_kiinteistotiedot_url') ."bbox=" .htmlentities($bbox);
			$username = config('app.mml_apikey_nimisto');
			$password = '';
		}

		$client = new Client();
		$res = $client->request('GET', $url, [
			'auth' => [$username, $password]
		]);

		if ($res->getStatusCode()!="200") {
			throw new Exception("KiinteistoTunnus failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
		}

		return self::parseKiinteistoTunnus($res->getBody());
	}

	/**
	 * Get the kiinteistotunnus of kiinteistos located inside a polygon
	 * Returns an array of kiinteistos where there is 'kiinteistotunnus' key
	 *
	 * @param $polygon
	 * @throws Exception
	 */
	public static function getKiinteistoTunnusByPolygon($polygon) {
		$poly = str_replace(",","+", $polygon);
		$poly = str_replace(" ",",", $poly);
		$filter = "filter=S_INTERSECTS(geometry,POLYGON((" .$poly .")))";
		if (!empty(config('app.mml_kiinteistotiedot_username')) && !empty(config('app.mml_kiinteistotiedot_password'))){
			$url = config('app.mml_kiinteistotiedot_url') . $filter;
			$username = config('app.mml_kiinteistotiedot_username');
			$password = config('app.mml_kiinteistotiedot_password');
		}
		else{
			$url = config('app.mml_kiinteistotiedot_url') . $filter;
			$username = config('app.mml_apikey_nimisto');
			$password = '';
		}

		$client = new Client();

		$res = $client->request('GET', $url, [
			'auth' => [$username, $password]
		]);

		if ($res->getStatusCode()!="200") {
			throw new Exception("KiinteistoTunnus failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
		}

		return self::parseKiinteistoTunnus($res->getBody());
	}

	/**
	 * Get information of kiinteisto by REST service
	 *
	 * @param $kiinteistotunnus
	 * @throws Exception
	 */
	public static function getKiinteistoTiedotREST($kiinteistotunnus) {

		$url = config('app.mml_lainhuutotiedot_rest_url');
		$username = config('app.mml_lainhuutotiedot_rest_username');
		$password = config('app.mml_lainhuutotiedot_rest_password');

		$client = new Client();
		$res = $client->request("GET", $url, [
				"query" => [
						"kiinteistotunnus" => $kiinteistotunnus
				],
				"auth" => [
				 		$username, $password
				]
		]);

		if ($res->getStatusCode()!="200") {
			throw new Exception("KiinteistoTiedot failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
		}

		return self::parseKiinteistoTiedot($res->getBody());
	}

public static function getOsoiteTiedotByBuildingKey($buildingKey) {
    $url = config('app.mml_rakennustiedot_url');
    $username = config('app.mml_rakennustiedot_username');
    $client = new Client(['verify' => false]);

    $queryParams = [
        "f" => "application/gml+xml;version=3.2",
        "limit" => "10",
        "sykeuserid" => $username,
        "filter" => "building_key = '" . $buildingKey . "'",
    ];
    $fullUrl = $url . "open_address/items?" . http_build_query($queryParams);
    Log::info('Rakennustiedot osoitehaku GET osoite', ['url' => $fullUrl]);

    $open_address_res = $client->request("GET", $url . "open_address/items", [
        "query" => $queryParams
    ]);
    if ($open_address_res->getStatusCode() != "200") {
        throw new Exception("OsoiteTiedot failed: ".$open_address_res->getStatusCode()." : ".$open_address_res->getReasonPhrase());
    }

    $osoiteXml = $open_address_res->getBody()->getContents();
    $osoiteXmlObj = simplexml_load_string($osoiteXml);
    $osoiteXmlObj->registerXPathNamespace('wfs', 'http://www.opengis.net/wfs/2.0');
    $osoiteXmlObj->registerXPathNamespace('ryhti_building', 'http://paikkatiedot.ymparisto.fi/ryhti_building');

    $osoitteet = [];
	$jarjestysnumero=1;
    foreach ($osoiteXmlObj->xpath('//wfs:member/ryhti_building:open_address') as $address) {
        $fields = $address->children('ryhti_building', true);

        // Osoite FI
        $osoite_fi = [
            'jarjestysnumero' => $jarjestysnumero,
            'kieli' => "fin",
            'katunimi' => (string)($fields->address_name_fin ?? ''),
            'katunumero' => (string)($fields->number_part_of_address_number ?? ''),
            'kuntatunnus' => (string)($fields->municipality_number ?? ''),
            'kuntanimi' => (string)($fields->postal_office_fin ?? ''),
            'postinumero' => (string)($fields->postal_code ?? ''),
        ];
        $osoitteet[] = $osoite_fi;

        // Jos löytyy ruotsinkielinen osoite, lisää se osoitteisiin
        if (!empty($fields->address_name_swe)) {
            $osoite_swe = [
                'jarjestysnumero' => $jarjestysnumero,
                'kieli' => "swe",
                'katunimi' => (string)$fields->address_name_swe,
                'katunumero' => (string)($fields->number_part_of_address_number ?? ''),
                'kuntatunnus' => (string)($fields->municipality_number ?? ''),
                'kuntanimi' => (string)($fields->postal_office_swe ?? ''),
                'postinumero' => (string)($fields->postal_code ?? ''),
            ];
            $osoitteet[] = $osoite_swe;
        }

        $jarjestysnumero++;
    }

    return $osoitteet;
}

private static function parseRakennusTiedot($rawXmlString) {
    // Jos data on muotoa {"response": "<?xml ..."}
    if (is_string($rawXmlString)) {
        $json = json_decode($rawXmlString);
        if (is_object($json) && isset($json->response)) {
            $rawXmlString = $json->response;
        }
    }

    $xml = simplexml_load_string($rawXmlString);
    $xml->registerXPathNamespace('wfs', 'http://www.opengis.net/wfs/2.0');
    $xml->registerXPathNamespace('ryhti_building', 'http://paikkatiedot.ymparisto.fi/ryhti_building');
    $xml->registerXPathNamespace('gml', 'http://www.opengis.net/gml/3.2');

    $data_array = [];
    $jarjestysnumero = 1;

    foreach ($xml->xpath('//wfs:member/ryhti_building:open_building') as $rakennus) {
        $fields = $rakennus->children('ryhti_building', true);

        // Käytä mapperia
        $rakennusData = \App\Integrations\MMLBuildingMapper::mapBuilding($fields);

		// Osoitteet
		$rakennusData['osoitteet'] = [];
		if (!empty($fields->building_key)) {
			try {
				$rakennusData['osoitteet'] = self::getOsoiteTiedotByBuildingKey((string)$fields->building_key);
				$rakennusData['postinumero'] = $rakennusData['osoitteet'][0]['postinumero'] ?? null;
			} catch (\Exception $e) {
				\Log::error('Rakennustiedot osoitehaku epäonnistui: ' . $e->getMessage());
			}
		}

        // Sijainti
        $location_geometry_data = $fields->point_location_geometry_data ?? null;
        $sijainti = null;
        if ($location_geometry_data) {
            $gmlPoint = $location_geometry_data->children('gml', true)->Point ?? null;
            if ($gmlPoint && isset($gmlPoint->pos)) {
                $sijainti = (string)$gmlPoint->pos;
            }
        }
        $rakennusData['sijainti'] = $sijainti;

        $rakennusData['MMLFeatureIndex'] = $jarjestysnumero;
        $rakennusData['showLabel'] = true;


        $data_array[] = $rakennusData;
        $jarjestysnumero++;
    }

    Log::info('parseRakennusTiedot: rakennukset', ['rakennukset' => $data_array]);
    return $data_array;
}

	public static function getRakennusTiedot($kiinteistotunnus, $sijainti) {

		$url = config('app.mml_rakennustiedot_url');
		$username = config('app.mml_rakennustiedot_username');
		$client = new Client(['verify' => false]);

		if($kiinteistotunnus != 'null') {
			$parsedKiinteistotunnus = str_replace('-', '', $kiinteistotunnus);	

			// Hae kaikki rakennukset kiinteistötunnuksella open_building-rajapinnasta (XML)
			$queryParams = [
				"f" => "application/gml+xml;version=3.2",
				"limit" => "10",
				"sykeuserid" => $username,
				"filter" => "property_identifier = '" . $parsedKiinteistotunnus . "'",
			];
			$fullUrl = $url . "open_building/items?" . http_build_query($queryParams);
			Log::info('Rakennustiedot GET osoite', ['url' => $fullUrl]);

			$open_building_res = $client->request("GET", $url . "open_building/items", [
				"query" => $queryParams
			]);
			if ($open_building_res->getStatusCode() != "200") {
				throw new Exception("Rakennustiedot failed: ".$open_building_res->getStatusCode()." : ".$open_building_res->getReasonPhrase());
			}

			return self::parseRakennusTiedot($open_building_res->getBody());

		} else {
			$lon = explode(" ", $sijainti)[0];
			$lat = explode(" ", $sijainti)[1];

			$minlon = $lon - 20;
			$minlat = $lat - 20;

			$maxlon = $lon + 20;
			$maxlat = $lat + 20;

			$bb = $minlon . "," . $minlat . "," . $maxlon . "," . $maxlat;

			Log::info('Rakennustiedot sijainti', ['bbox ' => $lon . " " . $lat]);
			Log::info('Rakennustiedot sijainti', ['bbox ' => $bb]);
			$open_building_res = $client->request("GET", $url ."open_building/items", [
					"query" => [
							"f"             => "application/gml+xml;version=3.2",
							"bbox-crs"      => "EPSG:3067",
							"limit"         => "10",
							"RESULTTYPE"    => "results",
							"filter-lang"   => "ecql-text",
							"sykeuserid"    => $username,
							"bbox"          => "$bb"
					]
			]);
			if ($open_building_res === null || $open_building_res->getStatusCode()!="200") {
				throw new Exception("Rakennustiedot failed: ".$open_building_res->getStatusCode()." : ".$open_building_res->getReasonPhrase());
			}

			return self::parseRakennusTiedot($open_building_res->getBody());
		}
	}

	private static function parseOsoiteTiedot($rawJSONString) {
		$json = json_decode($rawJSONString);
		$data_array = array();

		if ($json->features) {
			foreach($json->features as $osoite) {
				$result['katunimi'] = (string)$osoite->properties->katunimi;
				$result['katunumero'] = (string)$osoite->properties->katunumero;

				$result['kieli'] = (string)$osoite->properties->kieli;
				$result['jarjestysnumero'] = (string)$osoite->properties->jarjestysnumero;

				$result['kuntatunnus'] = (string)$osoite->properties->kuntatunnus;
				$result['kuntanimiFin'] = (string)$osoite->properties->kuntanimiFin;
				$result['kuntanimiSwe'] = (string)$osoite->properties->kuntanimiSwe;

				$result['sijainti'] = $osoite->geometry;

				//TODO: Separate to own method
				$result['osoite'] = (string)$result['katunimi'] . " " . $result['katunumero'] . ", " . $result['kuntanimiFin'];
				$result['katuosoite'] = (string)$result['katunimi'] . " " . $result['katunumero'];

				array_push($data_array, $result);
			}
		}
		return $data_array;
	}

	public static function getOsoiteTiedot($katunimi, $kuntanimi, $kuntanumero) {
		$kunta = "";
		if ($kuntanimi){
			$kunta = $kuntanimi;
		}
		else if ($kuntanumero){
			$kunta = $kuntanumero;
		}
		$url = config('app.mml_maasto_url')."&text=".$katunimi.",".$kunta;
		$client = new Client();
		$res = $client->request('GET', $url, [
			'auth' => [config('app.mml_apikey_nimisto'), '']
		]);

		if ($res->getStatusCode()!="200") {
			throw new Exception("OsoiteTiedot failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
		}

		return self::parseOsoiteTiedot($res->getBody());
	}

	private static function parseNimistoTiedot($rawXmlString) {
		$xml_string = str_replace(array("gml:","wfs:", "pnr:"), "", $rawXmlString);

		$data = new \SimpleXmlElement($xml_string);

		$data_array = array();
		if($data->featureMember) {

			$kunnatXml = MMLNimiMapper::getCodingsXml(config('app.mml_municipality_codes'));
			$paikkatyypitXml = MMLNimiMapper::getCodingsXml(config('app.mml_placetype_codes'));
			/*
			 * TODO:
			 * Lisää paikan tyyppi mukaan palautusarvoihin. Vaatii arvon muuttamisen stringiksi, esim:
			 * Paikkaryhmä: http://xml.nls.fi/Nimisto/Nimistorekisteri/paikkatyyppiryhma.xsd
			 * Paikkatyyppialaryhmä: http://xml.nls.fi/Nimisto/Nimistorekisteri/paikkatyyppialaryhma.xsd
			 * Paikkatyyppi: http://xml.nls.fi/Nimisto/Nimistorekisteri/paikkatyyppi.xsd
			 */

			foreach($data->featureMember as $paikka) {
				$result = array(); //Paikka

				$result['nimi'] = "";
				//Map kunta etc
				$result['kunta'] = MMLNimiMapper::mapKuntaValue($paikka->Place->municipality, $kunnatXml);
				$result['paikkatyyppi'] = MMLNimiMapper::mapPaikkatyyppiValue($paikka->Place->placeType, $paikkatyypitXml);

				foreach($paikka->Place->name as $paikannimi) {
					if($result['nimi'] != "") {
						$result['nimi'] = $result['nimi'] . ", " . (string)$paikannimi->Name->spelling; //Retuns all of the names separated with comma. E.g. Turku, Åbo
					} else {
						$result['nimi'] = (string)$paikannimi->Name->spelling;
					}
				}

				$result['formatted_data'] = (string)$result['nimi'] . " (" . $result['paikkatyyppi'] . "), " . $result['kunta'];

				$result['sijainti'] = (string)$paikka->Place->placeLocation->Point->pos;

				array_push($data_array, $result);
			}
		}
		return $data_array;
	}

	private static function generateKuntahakuRequestBody($kuntaNum) {

		/*
		 * Haetaan MML palvelusta kuntanumerolla ainoastaan
		 * kuntia (paikkatyyppikoodi 540 & 550 ( http://www.maanmittauslaitos.fi/kartat-ja-paikkatieto/asiantuntevalle-kayttajalle/kartta-ja-paikkatietojen-rajapintapalvelut-9 )
		 *
		 */

		$body = '<?xml version="1.0" encoding="UTF-8"?>';
		$body .= '<wfs:GetFeature version="1.1.0" ';
		$body .= 'xmlns:pnr="http://xml.nls.fi/Nimisto/Nimistorekisteri/2009/02" ';
		$body .= 'xmlns:wfs="http://www.opengis.net/wfs" ';
		$body .= 'xmlns:gml="http://www.opengis.net/gml" ';
		$body .= 'xmlns:ogc="http://www.opengis.net/ogc" ';
		$body .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
		$body .= 'xsi:schemaLocation="http://www.opengis.net/wfs ';
		$body .= 'http://schemas.opengis.net/wfs/1.1.0/wfs.xsd" ';
		$body .= 'maxFeatures="1">'; //Haetaan eksatia yhtä kuntaa, yksi tulos riittää
		$body .= '<wfs:Query typeName="pnr:Paikka">';
		$body .= '<ogc:Filter>';

		if($kuntaNum) {
			$body .= '<ogc:And>';
		}
		$body .= '<ogc:Or>';
		$body .= '<ogc:PropertyIsEqualTo>';
		$body .= '<ogc:PropertyName>pnr:paikkatyyppiKoodi</ogc:PropertyName>';
		$body .= '<ogc:Literal>540</ogc:Literal>';
		$body .= '</ogc:PropertyIsEqualTo>';
		$body .= '<ogc:PropertyIsEqualTo>';
		$body .= '<ogc:PropertyName>pnr:paikkatyyppiKoodi</ogc:PropertyName>';
		$body .= '<ogc:Literal>550</ogc:Literal>';
		$body .= '</ogc:PropertyIsEqualTo>';
		$body .= '</ogc:Or>';

		if($kuntaNum) {

			$body .= '<ogc:PropertyIsEqualTo>';
			$body .= '<ogc:PropertyName>pnr:kuntaKoodi</ogc:PropertyName>';
			$body.= '<ogc:Literal>'.$kuntaNum.'</ogc:Literal>';
			$body .= '</ogc:PropertyIsEqualTo>';
			$body .= '</ogc:And>';
		}


		$body .= '</ogc:Filter>';
		$body .= '</wfs:Query>';
		$body .= '</wfs:GetFeature>';

		return $body;
	}

	public static function getNimistoTiedot($paikannimi, $kunta, $kuntahaku) {
		if(strlen($kunta) == 3 && is_numeric($kunta)) {
			//Use the value directly
			$kuntaNum = $kunta;
		} else {
			$kuntaNum = MMLNimiMapper::mapKuntaNameToValue($kunta);
		}
		$url = config('app.mml_nimisto_url') ."&municipality=" . $kuntaNum ."&name.spelling_case_insensitive=" .$paikannimi . "*&limit=50";
		$client = new Client();

		if($kuntahaku) { //Haetaan ainoastaan kunta ja sen sijainti. Paikannimi kentässä on tällöin kuntanumero ja kuntahaku on true
			$url = config('app.mml_nimisto_url') ."&municipality=" . MMLNimiMapper::mapKuntaNameToValue($paikannimi) ."&placeType=4010125&limit=50"; //4010125=Kunta
			$res = $client->request('GET', $url, [
				'auth' => [config('app.mml_apikey_nimisto'), '']
			]);
		} else {
			$res = $client->request('GET', $url, [
				'auth' => [config('app.mml_apikey_nimisto'), '']
			]);
		}

		if ($res->getStatusCode()!="200") {
			throw new Exception("NimistoTiedot failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
		}

		return self::parseNimistoTiedot($res->getBody());
	}

}