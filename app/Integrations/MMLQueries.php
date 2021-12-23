<?php
namespace App\Integrations;

use GuzzleHttp\Client;
use Exception;

class MMLQueries {

	private static function parseKiinteistoTunnus($rawXmlString) {

		$xml_string = str_replace(array("rhr:", "gml:","ktjkiiwfs:"), "", $rawXmlString);
		$data = new \SimpleXmlElement($xml_string);

		$data_array = array();

		if($data->featureMember) {
			foreach($data->featureMember as $estate) {
				$result = array();
				$ktunnus = (string)$estate->RekisteriyksikonSijaintitiedot->kiinteistotunnus;

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

		foreach ($nodes as $node) {

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
		}
		$kiinteisto['omistajat'] = $omistajat;

		return $kiinteisto;
	}

	private static function generateKiinteistoTiedotRequestBodyForPoint($point) {

		$margin = 5;

		$lat = explode(" ", $point)[0];
		$lon = explode(" ", $point)[1];
		$lowerCorner = ($lat+$margin)." ".($lon+$margin);
		$upperCorner = ($lat-$margin)." ".($lon-$margin);

		$body  = '<?xml version="1.0" encoding="UTF-8"?>';
		$body .= '<wfs:GetFeature version="1.1.0" ';
		$body .= 'xmlns:ktjkiiwfs="http://xml.nls.fi/ktjkiiwfs/2010/02" ';
		$body .= 'xmlns:wfs="http://www.opengis.net/wfs" ';
		$body .= 'xmlns:gml="http://www.opengis.net/gml" ';
		$body .= 'xmlns:ogc="http://www.opengis.net/ogc" ';
		$body .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
		$body .= 'xsi:schemaLocation="http://www.opengis.net/wfs ';
		$body .= 'http://schemas.opengis.net/wfs/1.1.0/wfs.xsd">';
		$body .= '<wfs:Query ';
		$body .= 'typeName="ktjkiiwfs:RekisteriyksikonSijaintitiedot" ';
		$body .= 'srsName="EPSG:3067">';
		$body .= '<ogc:Filter>';
		$body .= '<ogc:BBOX>';
		$body .= '<ogc:PropertyName>ktjkiiwfs:rekisteriyksikonPalstanTietoja/ktjkiiwfs:RekisteriyksikonPalstanTietoja/ktjkiiwfs:sijainti</ogc:PropertyName>';
		$body .= '<gml:Envelope srsName="EPSG:3067">';
		$body .= '<gml:lowerCorner>'.$lowerCorner.'</gml:lowerCorner>';
		$body .= '<gml:upperCorner>'.$upperCorner.'</gml:upperCorner>';
		$body .= '</gml:Envelope>';
		$body .= '</ogc:BBOX>';
		$body .= '</ogc:Filter>';
		$body .= '</wfs:Query>';
		$body .= '</wfs:GetFeature>';

		return $body;
	}

	private static function generateKiinteistoTiedotRequestBodyForPolygon($polygon) {

		$body  = '<?xml version="1.0" encoding="UTF-8"?>';
		$body .= '<wfs:GetFeature version="1.1.0" ';
		$body .= 'xmlns:ktjkiiwfs="http://xml.nls.fi/ktjkiiwfs/2010/02" ';
		$body .= 'xmlns:wfs="http://www.opengis.net/wfs" ';
		$body .= 'xmlns:gml="http://www.opengis.net/gml" ';
		$body .= 'xmlns:ogc="http://www.opengis.net/ogc" ';
		$body .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
		$body .= 'xsi:schemaLocation="http://www.opengis.net/wfs ';
		$body .= 'http://schemas.opengis.net/wfs/1.1.0/wfs.xsd">';
		$body .= '<wfs:Query ';
		$body .= 'typeName="ktjkiiwfs:RekisteriyksikonSijaintitiedot" ';
		$body .= 'srsName="EPSG:3067">';
		$body .= '<ogc:Filter>';
		$body .= '<ogc:Intersects>';
		$body .= '<ogc:PropertyName>ktjkiiwfs:rekisteriyksikonPalstanTietoja/ktjkiiwfs:RekisteriyksikonPalstanTietoja/ktjkiiwfs:sijainti</ogc:PropertyName>';
		$body .= '<gml:Polygon srsName="EPSG:3067">';
		$body .= '  <gml:outerBoundaryIs>';
		$body .= '    <gml:LinearRing>';
		$body .= '      <gml:coordinates>';
		$body .= $polygon;
		$body .= '      </gml:coordinates>';
		$body .= '    </gml:LinearRing>';
		$body .= '</gml:outerBoundaryIs>';
		$body .= '</gml:Polygon>';
		$body .= '</ogc:Intersects>';
		$body .= '</ogc:Filter>';
		$body .= '</wfs:Query>';
		$body .= '</wfs:GetFeature>';

		return $body;
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

		$url = config('app.mml_kiinteistotiedot_url');
		$username = config('app.mml_kiinteistotiedot_username');
		$password = config('app.mml_kiinteistotiedot_password');

		$client = new Client();

		$res = $client->request("POST", $url, [
				'http_errors' => false,
				'headers' => ['Content-Type' => 'text/xml'],
				"auth" => [$username, $password],
				"body" => self::generateKiinteistoTiedotRequestBodyForPoint($point)
		]);

		if ($res->getStatusCode()!="200") {
			throw new Exception("KiinteistoTiedot failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
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

		$url = config('app.mml_kiinteistotiedot_url');
		$username = config('app.mml_kiinteistotiedot_username');
		$password = config('app.mml_kiinteistotiedot_password');

		$client = new Client();

		$res = $client->request("POST", $url, [
				'http_errors' => false,
				'headers' => ['Content-Type' => 'text/xml'],
				"auth" => [$username, $password],
				"body" => self::generateKiinteistoTiedotRequestBodyForPolygon($polygon)
		]);

		if ($res->getStatusCode()!="200") {
			throw new Exception("KiinteistoTiedotForPolygon failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
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

	private static function parseRakennusTiedot($rawXmlString) {

		$xml_string = str_replace(array("rhr:", "gml:","wfs:"), "", $rawXmlString);

		$data = new \SimpleXmlElement($xml_string);

		$data_array = array();
		if($data->featureMember) {
			foreach($data->featureMember as $building) {
				$result = array(); //Rakennus
				$osoitteet = array();
				$omistajat = array();

				foreach($building->RakennuksenOmistajatiedot as $omistajatiedot) {
					foreach($omistajatiedot as $key => $value) {
						if($key == 'sijainti') {
							//Get the location from sijainti/Point/pos
							$result[(string)$key] = (string)$value->Point->pos;

						} else if($key == 'osoite') {
							//Get 1..n amount of addresses and add them to the result
							$osoite = array();

							foreach($value as $ok => $ov) {
								$osoite['jarjestysnumero'] = (string)$ov->jarjestysnumero;
								$osoite['kieli'] = (string)$ov->kieli;
								$osoite['katunimi'] = (string)$ov->katunimi;
								$osoite['katunumero'] = (string)$ov->katunumero;
								array_push($osoitteet, $osoite);
							}

							$result['osoitteet'] = $osoitteet;

						} else if($key =='omistaja') {
							//Get 1..n amount of owners and add them to the result
							$omistaja = array();

							foreach($value as $ok => $ov) {
								$omistaja['sukunimi'] = (string)$ov->sukunimi;
								$omistaja['etunimi'] = (string)$ov->etunimi;
								array_push($omistajat, $omistaja);
							}

							$result['omistajat'] = $omistajat;

						} else {
							//Normal case, XML doesn't contain children.
							//Do mapping for the value and return it.
							$val = MMLRakennustietoMapper::map((string)$key, (string)$value);

							$result[(string)$key] = $val;
						}
					}
				}

				array_push($data_array, $result);
			}
		}
		return $data_array;
	}

	public static function getRakennusTiedot($kiinteistotunnus, $sijainti) {

		$url = config('app.mml_rakennustiedot_url');
		$username = config('app.mml_rakennustiedot_username');
		$password = config('app.mml_rakennustiedot_password');

		$client = new Client();

		if($kiinteistotunnus != 'null') {
			$parsedKiinteistotunnus = str_replace('-', '', $kiinteistotunnus);
			$filter = "<Filter><PropertyIsEqualTo><PropertyName>rhr:kiinteistotunnus</PropertyName><Literal>" . $parsedKiinteistotunnus . "</Literal></PropertyIsEqualTo></Filter>";

			//Get the rakennus information with with owner information (rhr:RakennuksenOmistajatiedot)
			$res = $client->request("GET", $url, [
					"query" => [
							"SERVICE" 		=> "WFS",
							"REQUEST" 		=> "GetFeature",
							"VERSION" 		=> "1.1.0",
							"NAMESPACE" 	=> "xmlns(rhr:http://xml.nls.fi/Rakennustiedot/VTJRaHu/2009/2)",
							"TYPENAME" 		=> "rhr:RakennuksenOmistajatiedot",
							"SRSNAME" 		=> "EPSG:3067",
							"MAXFEATURES" 	=> "500",
							"RESULTTYPE" 	=> "results",
							"EPSG" 			=> "3067",
							"Filter" 		=> $filter
					]
					,
					"auth" => [
							$username, $password
					]
			]);

		} else {
			$lon = explode(" ", $sijainti)[0];
			$lat = explode(" ", $sijainti)[1];

			$minlon = $lon - 20;
			$minlat = $lat - 20;

			$maxlon = $lon + 20;
			$maxlat = $lat + 20;

			$bb = $minlon . "," . $minlat . "," . $maxlon . "," . $maxlat;
			//Get the rakennus information with with owner information (rhr:RakennuksenOmistajatiedot)
			$res = $client->request("GET", $url, [
					"query" => [
							"SERVICE" 		=> "WFS",
							"REQUEST" 		=> "GetFeature",
							"VERSION" 		=> "1.1.0",
							"NAMESPACE" 	=> "xmlns(rhr:http://xml.nls.fi/Rakennustiedot/VTJRaHu/2009/2)",
							"TYPENAME" 		=> "rhr:RakennuksenOmistajatiedot",
							"SRSNAME" 		=> "EPSG:3067",
							"MAXFEATURES" 	=> "30",
							"RESULTTYPE" 	=> "results",
							"EPSG" 			=> "3067",
							"BBOX" 			=> $bb
					]
					,
					"auth" => [
							$username, $password
					]
			]);
		}

		if ($res->getStatusCode()!="200") {
			throw new Exception("Rakennustiedot failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
		}

		return self::parseRakennusTiedot($res->getBody());
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

	private static function generateOsoiteTiedotRequestBody($katunimi, $kuntanimi, $kuntanumero) {

		//Extract the numbers and set them to own variable
		preg_match_all('!\d+!', $katunimi, $numerot);
		$katunumero = implode('', $numerot[0]);

		//Leave the $katunimi with only the alphabets
		$katunimi = preg_replace('/[0-9]+/', '', $katunimi);
		//trim the trailing spaces
		$katunimi = rtrim($katunimi);
		//trim leading and trailing spaces
		$kuntanimi = ltrim($kuntanimi);
		$kuntanimi = rtrim($kuntanimi);

		$body = '<?xml version="1.0" encoding="UTF-8"?>';
		$body .= '<wfs:GetFeature version="1.1.0" ';
		$body .= 'xmlns:oso="http://xml.nls.fi/Osoitteet/Osoitepiste/2011/02" ';
		$body .= 'xmlns:wfs="http://www.opengis.net/wfs" ';
		$body .= 'xmlns:gml="http://www.opengis.net/gml" ';
		$body .= 'xmlns:ogc="http://www.opengis.net/ogc" ';
		$body .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
		$body .= 'xsi:schemaLocation="http://www.opengis.net/wfs ';
		$body .= 'http://schemas.opengis.net/wfs/1.1.0/wfs.xsd" ';
		$body .= 'maxFeatures="20">';
		$body .= '<wfs:Query typeName="oso:Osoitenimi">';
		$body .= '<ogc:Filter>';
		$body .= '<ogc:And>';

		//If we only have a single search parameter, add "first" to params as the service requires at least 2 parameters to be used
		if(($kuntanimi && !$katunimi && !$katunumero && !$kuntanumero) || ($katunimi && !$katunumero && !$kuntanimi && !$kuntanumero)
			|| ($kuntanumero && !$katunimi && !$katunumero && !$kuntanimi))
		{
			$body .= '<ogc:PropertyIsEqualTo>';
			$body .= '<ogc:PropertyName>oso:jarjestysnumero</ogc:PropertyName>';
			$body .= '<ogc:Literal>1</ogc:Literal>';
			$body .= '</ogc:PropertyIsEqualTo>';
		}

		if($kuntanimi) {

			$body .= '<ogc:PropertyIsLike wildCard="*" singleChar="?" escape="!" matchCase="false">';
			$body .= '<ogc:PropertyName>oso:kuntanimiFin</ogc:PropertyName>';
			$body .= '<ogc:Literal>'.$kuntanimi.'*</ogc:Literal>';
			$body .= '</ogc:PropertyIsLike>';
		}

		if($katunimi) {

			$body .= '<ogc:PropertyIsLike wildCard="*" singleChar="?" escape="!" matchCase="false">';
			$body .= '<ogc:PropertyName>oso:katunimi</ogc:PropertyName>';
			$body .= '<ogc:Literal>'.$katunimi.'*</ogc:Literal>';
			$body .= '</ogc:PropertyIsLike>';
		}

		if($katunumero) {
			$body .= '<ogc:PropertyIsEqualTo>';
			$body .= '<ogc:PropertyName>oso:katunumero</ogc:PropertyName>';
			$body .= '<ogc:Literal>'.$katunumero.'</ogc:Literal>';
			$body .= '</ogc:PropertyIsEqualTo>';
		}

		if($kuntanumero) {
			$body .= '<ogc:PropertyIsEqualTo>';
			$body .= '<ogc:PropertyName>oso:kuntatunnus</ogc:PropertyName>';
			$body .= '<ogc:Literal>'.$kuntanumero.'</ogc:Literal>';
			$body .= '</ogc:PropertyIsEqualTo>';
		}

		$body .= '</ogc:And>';
		$body .= '</ogc:Filter>';
		$body .= '</wfs:Query>';
		$body .= '</wfs:GetFeature>';

		return $body;
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

	private static function generateNimistoTiedotRequestBody($paikannimi, $kunta) {

		/*
		 * If we have kunta (name or number), we must add AND filter and the correct value to the request body.
		 * First, start by checking if the user has given us kunta number or name. Name needs to be mapped to number as
		 * MML supports only searching with county numbers.
		 * If we do not have exact match (e.g. Turk instead of Turku), skip the county filter and use only the paikannimi.
		 */
		$kuntaNum = null;

		if($kunta) {
			$kunta = rtrim($kunta);
			$kunta = ltrim($kunta);

			if(strlen($kunta) == 3 && is_numeric($kunta)) {
				//Use the value directly
				$kuntaNum = $kunta;
			} else {
				$kuntaNum = MMLNimiMapper::mapKuntaNameToValue($kunta);
			}
		}

		$body = '<?xml version="1.0" encoding="UTF-8"?>';
	    $body .= '<wfs:GetFeature version="1.1.0" ';
	    $body .= 'xmlns:pnr="http://xml.nls.fi/Nimisto/Nimistorekisteri/2009/02" ';
	    $body .= 'xmlns:wfs="http://www.opengis.net/wfs" ';
	    $body .= 'xmlns:gml="http://www.opengis.net/gml" ';
	    $body .= 'xmlns:ogc="http://www.opengis.net/ogc" ';
	    $body .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
	    $body .= 'xsi:schemaLocation="http://www.opengis.net/wfs ';
	    $body .= 'http://schemas.opengis.net/wfs/1.1.0/wfs.xsd" ';
		$body .= 'maxFeatures="20">';
	    $body .= '<wfs:Query typeName="pnr:Paikka">';
	    $body .= '<ogc:Filter>';

	    if($kuntaNum) {
	    	$body .= '<ogc:And>';
	    }

	    $body .= '<ogc:PropertyIsLike wildCard="*" singleChar="?" escapeChar="!" matchCase="false">';
	    $body .= '<ogc:PropertyName>pnr:nimi/pnr:PaikanNimi/pnr:kirjoitusasu</ogc:PropertyName>';
	    $body .= '<ogc:Literal>'.$paikannimi.'*</ogc:Literal>';
	    $body .= '</ogc:PropertyIsLike>';

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