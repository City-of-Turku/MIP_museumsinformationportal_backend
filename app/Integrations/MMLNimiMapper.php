<?php
namespace App\Integrations;

use GuzzleHttp\Client;
use Exception;

class MMLNimiMapper {

	/*
	 * Gets the xml containing the codings from MML.
	 */
	public static function getCodingsXml($url) {

		$client = new Client();

		$res = $client->request("GET", $url, [
				'http_errors' => false,
				'headers' => ['Content-Type' => 'text/xml']
		]);

		if ($res->getStatusCode()!="200") {
			throw new Exception("GetCodingsXML failed: ".$res->getStatusCode()." : ".$res->getReasonPhrase());
		}

		return $res->getBody();
	}


	/*
	 * Return the kunta nimi (e.g. Turku) that matches the given $value (e.g. 853) from the xmlstring.
	 */
	public static function mapKuntaValue($value, $rawXmlString) {

		$xml_string = str_replace(array("xs:", "xml:"), "", $rawXmlString);

		//app('log')->debug("MAPKUNTAVALUE: Value: " . $value . " XML: " . $xml_string);

		$data = new \SimpleXmlElement($xml_string);

		foreach($data->simpleType->restriction->enumeration as $kunta) {

			if((string)$kunta['value'] == (string)$value) {

				foreach($kunta->annotation->documentation as $doc) {
					//app('log')->debug($doc->asXML());

					if((string)$doc['lang'] == "fin") {
						//app('log')->debug("Translation found: " . $doc);

						return (string)$doc; //If we don't cast to string, we return object with attributes containing the language. Could be useful for the translations.
					}
				}
			}
		}

		return "";
	}

	/*
	 * Return the paikkatyyppi nimi (e.g. Autotie) that matches the given $value (e.g. 100) from the xmlstring.
	 */
	public static function mapPaikkatyyppiValue($value, $rawXmlString) {

		$xml_string = str_replace(array("xs:", "xml:"), "", $rawXmlString);

		//app('log')->debug("MAPPAIKKATYYPPI: Value: " . $value . " XML: " . $xml_string);

		$data = new \SimpleXmlElement($xml_string);

		foreach($data->simpleType->restriction->enumeration as $paikkatyyppi) {

			if((string)$paikkatyyppi['value'] == (string)$value) {

				foreach($paikkatyyppi->annotation->documentation as $doc) {
					//app('log')->debug($doc->asXML());

					if((string)$doc['lang'] == "fin") {
						//app('log')->debug("Translation found: " . $doc);

						return (string)$doc; //If we don't cast to string, we return object with attributes containing the language. Could be useful for the translations.
					}
				}
			}
		}

		return "";
	}

	/*
	 * Searches the county number for the given name.
	 */
	public static function mapKuntaNameToValue($value) {
		$value = ucfirst($value);

		$rawXmlString = self::getCodingsXml(config('app.mml_municipality_codes'));
		$xml_string = str_replace(array("xs:", "xml:"), "", $rawXmlString);
		$data = new \SimpleXmlElement($xml_string);

		foreach($data->simpleType->restriction->enumeration as $kunta) {
			foreach($kunta->annotation->documentation as $doc) {

				if((string)$doc == $value) {
					//app('log')->debug("Value found: " . $doc);
					//app('log')->debug("Value found: " . (string)$kunta['value']);

					return (string)$kunta['value']; //If we don't cast to string, we return object with attributes containing the language. Could be useful for the translations.
				}
			}
		}
		return "";

	}



}