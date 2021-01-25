<?php

namespace App\Integrations\Finna;

use App\Ark\ArkFinnaLog;
use App\Utils;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Ark\Loyto;
use App\Ark\ArkKuva;
use App\Ark\Kohde;
use App\Ark\KohdeTutkimus;

/**
 * Integraatio Museoviraston muinaisjäännösrekisterin rajapintapalveluun.
 *
 * Lido spesifikaatio: http://www.lido-schema.org/schema/v1.0/lido-v1.0-specification.pdf
 * Finnan kenttien mäppäyksiä: https://www.kiwi.fi/display/Finna/Kenttien+mappaukset+eri+formaateista+Finnan+indeksiin
 * OAIPMH-spesifikaatio: http://www.openarchives.org/OAI/2.0/guidelines-repository.htm
 *
 */
class FinnaService
{
	private $IDENTIFIER = '';
	private $baseurl;
	private static $LOYTOIDENTIFIER = 17;
	private $HAKUMAARA = 1000;
	private $ORGANISATION = '';
	private $ARKEOLOGINENKOKOELMA = '';
	private $KOKOELMA = '';
	private $MUSEOURL = '';
	public $ADMINEMAIL = '';
	private $REPOSITORY_NAME = '';
	private $REPOSITORY_IDENTIFIER = '';

	var $completeListSize = null; // Haussa tulevien löytöjen kokonaismäärä
	var $cursor = 0; // Kursorin positio ("sivutus")

	function __construct()
	{
		$this->baseurl = url('/') . 'api/oaipmh';
		$this->ADMINEMAIL = config('app.finna_admin_email');
		$this->HAKUMAARA = config('app.finna_hakumaara');
		$this->IDENTIFIER = config('app.finna_identifier');
		$this->ORGANISATION = config('app.finna_organisation');
		$this->ARKEOLOGINENKOKOELMA = config('app.finna_arkeologinen_kokoelma');
		$this->KOKOELMA = config('app.finna_kokoelma');
		$this->MUSEOURL = config('app.finna_museo_url');
		$this->REPOSITORY_NAME = config('app.finna_repository_name');
		$this->REPOSITORY_IDENTIFIER = config('app.finna_repository_identifier');
	}

	public function setCursor(int $cursorPos)
	{
		$this->cursor = $cursorPos;
	}

	public function setCompleteListSize($size)
	{
		$this->completeListSize = $size;
	}

	public function makeLoytoIdentifier($loyto_id)
	{
		return $this->IDENTIFIER . self::$LOYTOIDENTIFIER . '.' . $loyto_id;
	}

	/*
     * http://www.openarchives.org/OAI/openarchivesprotocol.html#Identify
     */
	public function identify()
	{

		Log::channel('finna')->info("Identify");

		// Muuttujat XMLn täyttöä varten.
		$responseDate = FinnaUtils::dateTo8601Zulu(Carbon::now()); // Esim: 2001-07-08T22:00:00Z
		$protocolVersion = '2.0';
		$adminEmail = $this->ADMINEMAIL;
		$earliestDatestamp = '2000-01-01T00:00:00Z'; // ??
		$deleteRecord = 'persistent';
		$granularity = 'YYYY-MM-DDThh:mm:ssZ';
		$scheme = 'oai';
		$delimiter = ':';

		// Asetetaan arvot XML:ään
		$xml = simplexml_load_string(FinnaUtils::getIdentifyXml());
		$xml->responseDate = $responseDate;
		$xml->request = $this->baseurl;
		$xml->Identify->repositoryName = $this->REPOSITORY_NAME;
		$xml->Identify->baseURL = $this->baseurl;
		$xml->Identify->protocolVersion = $protocolVersion;
		$xml->Identify->adminEmail = $adminEmail;
		$xml->Identify->earliestDatestamp = $earliestDatestamp;
		$xml->Identify->deleteRecord = $deleteRecord;
		$xml->Identify->granularity = $granularity;
		$xml->Identify->description->{'oai-identifier'}->scheme = $scheme;
		$xml->Identify->description->{'oai-identifier'}->repositoryIdentifier = $this->REPOSITORY_IDENTIFIER;
		$xml->Identify->description->{'oai-identifier'}->delimiter = $delimiter;
		$xml->Identify->description->{'oai-identifier'}->sampleIdentifier = $this->makeLoytoIdentifier(123);

		return $xml->asXML();
	}

	/**
	 * http://www.openarchives.org/OAI/openarchivesprotocol.html#ListRecords
	 */
	public function listRecords($from = null, $until = null)
	{
		FinnaUtils::setFinnaUser();

		Log::channel('finna')->info("ListRecords, from - until: " . var_export($from, true) . " - " . var_export($until, true));
		$loytoIdList = "Returned loytoIDs: "; // Used for logging
		$loydot = $this->getLoydot($from, $until);

		// Haetaan 1 kpl enemmän kuin palautettava määrä on oikeasti.
		// Jos rivejä palautetaan hakumäärä+1, tiedetään, että tietueita on lisää
		// ja resumptionToken tulee palauttaa
		$isIncomplete = sizeof($loydot) === $this->HAKUMAARA + 1 ? true : false;
		if ($isIncomplete) {
			$loydot = array_slice($loydot, 0, $this->HAKUMAARA);
		}

		$responseDate = FinnaUtils::dateTo8601Zulu(Carbon::now()); // 2001-07-08T22:00:00Z

		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->startDocument('1.0', 'UTF-8');
		$writer->setIndent(1);

		$writer->startElement('OAI-PMH');
		$writer->writeAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/');
		$writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$writer->writeAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
		$writer->startElement('responseDate');
		$writer->text($responseDate);
		$writer->endElement();
		$writer->startElement('request');
		$writer->writeAttribute('verb', 'ListRecords');
		$writer->writeAttribute('metadataPrefix', 'lido');
		$writer->text($this->baseurl);
		$writer->endElement();
		$writer->startElement('ListRecords');
		foreach ($loydot as $loyto) {
			$loytoIdList .= $loyto['id'] . ",";
			$writer->startElement('record');
			$this->writeRecordElement($writer, $loyto, 'ListRecords');
			$writer->endElement();

			$finnaLog = ArkFinnaLog::updateOrCreate(['ark_loyto_id' => $loyto['id']], ['siirto_pvm' => Carbon::now()]);
		}
		if ($isIncomplete) {
			$this->setCursor($this->cursor + $this->HAKUMAARA);
			self::writeResumptionToken($writer, $this->cursor, $this->completeListSize);
		}
		$writer->endElement();
		$writer->endElement();
		$writer->endDocument();

		Log::channel('finna')->info($loytoIdList);
		return $writer->flush();
	}

	/**
	 * http://www.openarchives.org/OAI/openarchivesprotocol.html#ListIdentifiers
	 */
	public function listIdentifiers($from = null, $until = null)
	{
		FinnaUtils::setFinnaUser();

		Log::channel('finna')->info("ListIdentifiers, from - until: " . var_export($from, true) . " - " . var_export($until, true));
		$loydot = $this->getLoydot($from, $until);
		$loytoIdList = "Returned loytoIDs: "; // Used for logging
		// Haetaan 1 kpl enemmän kuin palautettava määrä on oikeasti.
		// Jos rivejä palautetaan hakumäärä+1, tiedetään, että tietueita on lisää
		// ja resumptionToken tulee palauttaa
		$isIncomplete = sizeof($loydot) === $this->HAKUMAARA + 1 ? true : false;
		if ($isIncomplete) {
			$loydot = array_slice($loydot, 0, $this->HAKUMAARA);
		}

		$responseDate = FinnaUtils::dateTo8601Zulu(Carbon::now()); // format: 2001-07-08T22:00:00Z

		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->startDocument('1.0', 'UTF-8');
		$writer->setIndent(1);

		$writer->startElement('OAI-PMH');
		$writer->writeAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/');
		$writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$writer->writeAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
		$writer->startElement('responseDate');
		$writer->text($responseDate);
		$writer->endElement();
		$writer->startElement('request');
		$writer->writeAttribute('verb', 'ListRecords');
		$writer->writeAttribute('metadataPrefix', 'lido');
		$writer->text($this->baseurl);
		$writer->endElement();
		$writer->startElement('ListRecords');
		foreach ($loydot as $loyto) {
			$loytoIdList .= $loyto['id'] . ","; //Log::debug("LOYTO ID: " . $loyto['id'] . ", luotu: ".$loyto['luotu'].", muokattu: ".$loyto['muokattu'].", poistettu: ".$loyto['poistettu']);
			$writer->startElement('record');
			$this->writeRecordElement($writer, $loyto, 'ListIdentifiers');
			$writer->endElement();
		}
		if ($isIncomplete) {
			$this->setCursor($this->cursor + $this->HAKUMAARA);
			self::writeResumptionToken($writer, $this->cursor, $this->completeListSize);
		}
		$writer->endElement();

		$writer->endElement();
		$writer->endDocument();

		Log::channel('finna')->info($loytoIdList);
		return $writer->flush();
	}


	public function getRecord($identifier)
	{
		FinnaUtils::setFinnaUser();

		// Identifier: oai:mip.turku.fi:17.123 <<-- 123 on löytöid
		$identifierArr = explode('.', $identifier);
		$id = array_pop($identifierArr);
		Log::channel('finna')->info("GetRecord, ID: " . $id);

		$loyto = $this->getLoyto($id);

		$responseDate = FinnaUtils::dateTo8601Zulu(Carbon::now()); // 2001-07-08T22:00:00Z

		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->startDocument('1.0', 'UTF-8');
		$writer->setIndent(1);

		$writer->startElement('OAI-PMH');
		$writer->writeAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/');
		$writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$writer->writeAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
		$writer->startElement('responseDate');
		$writer->text($responseDate);
		$writer->endElement();
		$writer->startElement('request');
		$writer->writeAttribute('verb', 'GetRecord');
		$writer->writeAttribute('identifier', $identifier);
		$writer->writeAttribute('metadataPrefix', 'lido');
		$writer->text($this->baseurl);
		$writer->endElement();
		$writer->startElement('GetRecord');
		$writer->startElement('record');
		$this->writeRecordElement($writer, $loyto, 'GetRecord');
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		$writer->endDocument();

		return $writer->flush();
	}

	private function writeRecordElement($writer, $loyto, $verb = null)
	{
		$isPoistettu = self::loytoIsPoistettu($loyto);
		// Header elementti
		$writer->startElement('header');
		// Status = deleted attribuutti kirjoitetaan ainoastaan poistetuille löydöille
		if ($isPoistettu == true) {
			$writer->writeAttribute('status', 'deleted');
		}
		$writer->startElement('identifier');
		$writer->text($this->makeLoytoIdentifier($loyto['id']));
		$writer->endElement();
		$writer->startElement('datestamp');
		if ($loyto['poistettu']) {
			$writer->text(FinnaUtils::dateTo8601Zulu(new \DateTime($loyto['poistettu'])));
		} else if ($loyto['muokattu']) {
			$writer->text(FinnaUtils::dateTo8601Zulu(new \DateTime($loyto['muokattu'])));
		} else {
			$writer->text(FinnaUtils::dateTo8601Zulu(new \DateTime($loyto['luotu'])));
		}
		$writer->endElement();
		$writer->endElement();

		// Lido muotoinen metadata-elementti
		// Ainoastaan jos loyto ei ole poistettu tai pyyntö on ListRecords tai GetRecord
		if ($verb == 'ListRecords' || $verb == 'GetRecord') {
			if ($isPoistettu == false) {
				$writer->startElement('metadata');
				$this->writeLidoElement($writer, $loyto);
				$writer->endElement();
			}
		}
	}

	/*
	 * Poistetut löydöt käsitellään eri tavalla. Finna-integraation osalta 'Poistettu' on seuraavanlainen
	 * Jos löytö on poistettu tai
	 * jos löydön tila on "Poistettu löytöluettelosta" tai
	 * jos löydön tila on "Poistettu kokoelmasta"
	 */
	private static function loytoIsPoistettu($loyto)
	{
		// Löydöllä on poistettu aikaleima
		if ($loyto['poistettu'] != null) {
			return true;
		}
		// 5: Poistettu löytöluettelosta
		if ($loyto['loydon_tila_id'] && $loyto['loydon_tila_id'] == 5) {
			return true;
		}
		// 9: Poistettu kokoelmasta
		if ($loyto['loydon_tila_id'] && $loyto['loydon_tila_id']  == 9) {
			return true;
		}

		// Löydon tutkimus ei ole julkinen ja valmis
		if ($loyto['tutkimus']['julkinen'] != true || $loyto['tutkimus']['valmis'] != true) {
			return true;
		}

		// Löydön siirtyy_finnaa on false
		if ($loyto['siirtyy_finnaan'] == false) {
			return true;
		}

		// TODO Vaatiiko jotain logiikkaa, jotta aina ei poistettu siirry uudelleen???

		return false;
	}

	private function writeLidoElement($writer, $loyto)
	{
		$writer->startElementNs('lido', 'lidoWrap', 'http://www.lido-schema.org');
		$writer->writeAttribute('xmlns:gml', 'http://www.opengis.net/gml');
		$writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$writer->writeAttribute('xsi:schemaLocation', 'http://www.lido-schema.org http://www.lido-schema.org/schema/v1.0/lido-v1.0.xsd http://www.opengis.net/gml http://schemas.opengis.net/gml/3.1.1/base/feature.xsd');
		$writer->startElement('lido:lido');
		$writer->startElement('lido:lidoRecID');
		$writer->writeAttribute('lido:type', 'ITEM');
		$writer->text($loyto['id']);
		$writer->endElement();
		$this->writeDescriptiveMetadataElement($writer, $loyto);
		$this->writeAdministrativeMetadata($writer, $loyto);
		$writer->endElement();
		$writer->endElement();
	}

	private function writeDescriptiveMetadataElement($writer, $loyto)
	{
		$writer->startElement('lido:descriptiveMetadata');
		$writer->writeAttribute('xml:lang', 'fi');
		self::writeObjectClassificationWrap($writer, $loyto);
		$this->writeObjectIdentificationWrap($writer, $loyto);
		self::writeEventWrap($writer, $loyto);
		$this->writeObjectRelationWrap($writer, $loyto);
		$writer->endElement();
	}

	private static function writeObjectClassificationWrap($writer, $loyto)
	{
		$writer->startElement('lido:objectClassificationWrap');
		$writer->startElement('lido:objectWorkTypeWrap');
		$writer->startElement('lido:objectWorkType');
		$writer->startElement('lido:term');
		$writer->text($loyto['loytotyyppi']['nimi_fi']);
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
	}

	private function writeObjectIdentificationWrap($writer, $loyto)
	{

		$writer->startElement('lido:objectIdentificationWrap');
		self::writeTitleWrap($writer, $loyto);
		$this->writeRepositoryWrap($writer, $loyto);
		self::writeObjectDescriptionWrap($writer, $loyto);
		self::writeObjectMeasurementsWrap($writer, $loyto);
		$writer->endElement();
	}

	private static function writeTitleWrap($writer, $loyto)
	{

		// 1. Tyyppitarkenne 2. Ensisijainen materiaali 3.tyyppi
		// Jos tyyppitarkenne on tyhjä, niin tilalle materiaalikoodin nimi
		// Jos ensisijainen materiaali on "Puuttuu", jätetään se tyhjäksi
		$yleisnimi = '';
		$yleisnimiSe = '';
		$yleisnimiEn = '';

		// Esineen nimi = loytotyyppi_tarkenteet
		if ($loyto['loytotyyppi_tarkenteet'] != null && sizeof($loyto['loytotyyppi_tarkenteet']) > 0) {
			foreach ($loyto['loytotyyppi_tarkenteet'] as $tyyppitarkenne) {
				$yleisnimi .= $tyyppitarkenne['nimi_fi'] . ', ';
				$yleisnimiSe .= $tyyppitarkenne['nimi_se'] . ', ';
				$yleisnimiEn .= $tyyppitarkenne['nimi_en'] . ', ';
			}
		} else if ($loyto['materiaalikoodi'] != null) {
			$yleisnimi = $loyto['materiaalikoodi']['nimi_fi'] . ', ';
		}
		// ID 65 = puuttuu - sitä ei haluta näkyväksi
		if ($loyto['ensisijainen_materiaali'] && $loyto['ensisijainen_materiaali']['id'] != 65) {
			// Jos ensisijainen materiaali on sama kuin jo olemassa oleva teksti, ei lisätä samaa toistamiseen
			// Esimerkkinä ensisijainen materiaali: Lasi, ja materiaalikoodi LA Lasi --> vältetään tilanne "Lasi, Lasi"
			if ($loyto['ensisijainen_materiaali']['nimi_fi'] != substr($yleisnimi, 0, -2)) { // Poistetaan pilkku ja välilyönti tätä vertailua varten
				$yleisnimi .= $loyto['ensisijainen_materiaali']['nimi_fi'];
			} else {
				$yleisnimi = substr($yleisnimi, 0, -2);
			}
		} else {
			$yleisnimi = substr($yleisnimi, 0, -2); // Poistetaan välilyönti ja pilkku
		}

		// Ei käytetä erityisnimeä, koska aiheuttaa Finnan listausnäkymässä toistoa.
		if ($loyto['loytotyyppi']) {
			if (strlen($yleisnimi) > 0) {
				$yleisnimi .= '; ' . $loyto['loytotyyppi']['nimi_fi'];
			} else {
				$yleisnimi = $loyto['loytotyyppi']['nimi_fi'];
			}
		}

		$writer->startElement('lido:titleWrap');
		$writer->startElement('lido:titleSet');
		$writer->writeAttribute('lido:type', 'title');
		$writer->startElement('lido:appellationValue');
		$writer->writeAttribute('xml:lang', 'fi');
		$writer->writeAttribute('lido:label', 'yleisnimi');
		$writer->text($yleisnimi);
		$writer->endElement();
		/* Käännökset puuttuvat kannasta
	        $writer->startElement('lido:appellationValue');
	          $writer->writeAttribute('xml:lang', 'en');
	          $writer->writeAttribute('lido:label', 'title');
	          $writer->text($yleisnimiEn);
	        $writer->endElement();
	        $writer->startElement('lido:appellationValue');
	          $writer->writeAttribute('xml:lang', 'sv');
	          $writer->writeAttribute('lido:label', 'titel');
	          $writer->text($yleisnimiSe);
	        $writer->endElement();
	        */
		$writer->endElement();
		/* Emme käytä erityisnimeä (subtitle)
	      $writer->startElement('lido:titleSet');
	        $writer->writeAttribute('lido:type', 'subtitle');
	        $writer->startElement('lido:appellationValue');
	          $writer->writeAttribute('xml:lang', 'fi');
	          $writer->writeAttribute('lido:label', 'erityisnimi');
	          $writer->text($erityisnimi);
	        $writer->endElement();
	        // Käännökset puuttuvat kannasta
	        $writer->startElement('lido:appellationValue');
	          $writer->writeAttribute('xml:lang', 'en');
	          $writer->writeAttribute('lido:label', 'specialtitle');
	          $writer->text($erityisnimiEn);
	        $writer->endElement();
	        $writer->startElement('lido:appellationValue');
	          $writer->writeAttribute('xml:lang', 'sv');
	          $writer->writeAttribute('lido:label', 'specialtitel');
	          $writer->text($erityisnimiSe);
	        $writer->endElement();
	      $writer->endElement();
	      */
		$writer->endElement();
	}

	private function writeRepositoryWrap($writer, $loyto)
	{
		$writer->startElement('lido:repositoryWrap');
		$writer->startElement('lido:repositorySet');
		$writer->startElement('lido:repositoryName');
		$writer->startElement('lido:legalBodyName');
		$writer->startElement('lido:appellationValue');
		$writer->text($this->ORGANISATION);
		$writer->endElement();
		$writer->endElement();
		$writer->startElement('lido:legalBodyWeblink');
		$writer->text($this->MUSEOURL);
		$writer->endElement();
		$writer->endElement();
		$writer->startElement('lido:workID');
		$writer->text($loyto['luettelointinumero']);
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
	}

	private static function writeObjectDescriptionWrap($writer, $loyto)
	{
		$writer->startElement('lido:objectDescriptionWrap');
		$writer->startElement('lido:objectDescriptionSet');
		$writer->writeAttribute('lido:type', 'description');
		$writer->startElement('lido:descriptiveNoteValue');
		$writer->writeAttribute('xml:lang', 'fi');
		$writer->writeAttribute('lido:label', 'kuvaus');
		$kuvaus = self::fixKuvausText($loyto['kuvaus']);
		$writer->text($kuvaus);
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
	}

	/*
	 * Otetaan migraatiossa muokatusta kuvauksesta ainoastaan oleelliset tiedot:
	 * Esimerkiksi
	   Muunnettu migraatiossa löydöstä:
       MORFOLOGIA/ARVO/MATERIAALI: Kylki
       KERAMIIKAN TYYPPI: Huokoinen lasitettu punasavi
       KATEGORIA: Keramiikka-astia
       Murtumapinta punaruskea; sisäpinnassa savilietteellä tehtyjä vaakasuoria raitoja, päällä väritön lyijylasite; ulkopinta sileä
       -->
       Kylki. Huokoinen lasitettu punasavi. Keramiikka-astia. Murtumapinta punaruskea; sisäpinnassa savilietteellä tehtyjä vaakasuoria raitoja, päällä väritön lyijylasite; ulkopinta sileä
	 */
	private static function fixKuvausText($kuvaus)
	{
		$newKuvaus = '';
		// HUOM: Ei huomioida tekstiä 'Muunnettu migraatiossa löydöstä' ollenkaan - eli poistetaan se + isoilla
		// kirjoitettu otsikko, jos rivi jakaantuu 2 osaan : merkistä. Tämä johtuu siitä, että datoja on muokattu käsipelillä.

		// Jos kuvaus ei ala 'Muunnettu migraatiossa löydöstä:' tekstillä, se ei vaadi muuttamista
		//if(strpos($kuvaus, 'Muunnettu migraatiossa löydöstä:') === false) {
		//   return $kuvaus;
		//}

		// 1. Pilkotaan kuvausteksti osiin rivinvaihdosta
		$kuvausArr = preg_split('/\r\n|\r|\n/', $kuvaus);

		// 2. Käydään jokainen rivi läpi
		for ($i = 0; $i < sizeof($kuvausArr); $i++) {
			$kuvausArr[$i] = trim($kuvausArr[$i]);
			// Jätetään pois rivi jolla teksti 'Muunnettu migraatiossa löydöstä:'
			// Ilmeisesti tekstin perässä voi olla lisäksi vanha luettelointinumero
			//if(strcmp($kuvausArr[$i], 'Muunnettu migraatiossa löydöstä:') == 0) {
			if (stripos($kuvausArr[$i], 'Muunnettu migraatiossa löydöstä:') !== false) {
				continue;
			}

			// Splitataan teksti : -merkistä osiin
			$riviArr = explode(':', $kuvausArr[$i]);
			$ra = [];

			// Trimmataan splitattu teksti
			foreach ($riviArr as $rivi) {
				array_push($ra, trim($rivi));
			}
			// 3. Jos teksti on mennyt kahteen osaan ja ensimmäinen osio on kokonaan
			// capseilla kirjoitettu, jätetään ensimmäinen osa pois ja otetaan ainoastaan toinen
			// Muutoin otetaan koko rivi
			if (sizeof($ra) != 2 || strlen($ra[0]) == 0 || strlen($ra[1]) == 0) {
				$newKuvaus .= $kuvausArr[$i] . '. '; // Otetaan koko rivi, ei splittaantunut kuten haluttu
				continue;
			}

			// Rivi koostuu kahdesta osasta, joissa molemmissa on tekstiä.
			// Koitetaan onko ensimmäinen osa kokonaan capseilla kirjoitettu, ja jos on, niin otetaan siitä ainoastaan 'tieto-osa'
			if (strcmp(strtoupper($ra[0]), $ra[0]) == 0) {
				// Ensimmäinen osio on capseilla kirjoitettu, otetaan ainoastaan jälkimmäinen osio
				$newKuvaus .= $ra[1] . '. ';
			} else {
				// Rivin ensimmäinen osio ei ole kokonaan capseilla, lisätään koko rivi kuvaukseen sellaisenaan.
				$newKuvaus .= $kuvausArr[$i] . '. ';
			}
		}

		// Trim the whitespaces and dots and add single dot.
		$newKuvaus = trim($newKuvaus, ". ");
		$newKuvaus .= ".";

		// Trimmataan myös mahdolliset peräkkäiset pisteet ja peräkkäiset välilyönnit tekstin keskeltä.
		// Ei poista yksittäisiä välilyöntejä tai pisteitä.
		$newKuvaus = preg_replace('/\.{2,}/', "", $newKuvaus);
		$newKuvaus = preg_replace('/ {2,}/', "", $newKuvaus);

		return $newKuvaus;
	}

	private static function writeObjectMeasurementsWrap($writer, $loyto)
	{
		$writer->startElement('lido:objectMeasurementsWrap');
		$writer->startElement('lido:objectMeasurementsSet');
		self::writeDisplayObjectMeasurements($writer, $loyto);
		$writer->startElement('lido:objectMeasurements');
		self::writeMeasurementsSet($writer, $loyto);
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
	}

	private static function writeDisplayObjectMeasurements($writer, $loyto)
	{
		$text = '';

		// Toivottu järjestys: pituus x leveys x paksuus/korkeus
		if ($loyto['pituus']) {
			$text .= $loyto['pituus'] . $loyto['pituusyksikko'] . ' x ';
		}
		if ($loyto['leveys']) {
			$text .= $loyto['leveys'] . $loyto['leveysyksikko'] . ' x ';
		}
		if ($loyto['paksuus']) {
			$text .= $loyto['paksuus'] . $loyto['paksuusyksikko'] . ' x ';
		} else if ($loyto['korkeus']) {
			$text .= $loyto['korkeus'] . $loyto['korkeusyksikko'] . ' x ';
		}

		// Trimmataan viimeinen x tässä välissä
		$text = substr($text, 0, -3);

		if (strlen($text) > 0) {
			$text .= '. ';
		}

		if ($loyto['paino']) {
			$text .= 'Paino: ' . $loyto['paino'] . $loyto['painoyksikko'] . ', ';
		}

		// Jos jostain syystä paksuuden lisäksi myös korkeus ilmoitettu, kerrotaan sekin, koska yllä olevassa
		// mittojen ilmoituksessa ei korkeutta kerrota jos paksuus on ilmoitettu.
		if ($loyto['paksuus'] && $loyto['korkeus']) {
			$text .= 'Korkeus: ' . $loyto['korkeus'] . $loyto['korkeusyksikko'] . ', ';
		}

		if ($loyto['halkaisija']) {
			$text .= 'Halkaisija: ' . $loyto['halkaisija'] . $loyto['halkaisijayksikko'] . '. ';
		}

		if ($loyto['muut_mitat']) {
			$text .= 'Muut mitat: ' . $loyto['muut_mitat'];
		}

		// Trimmataan lopusta turhat välimerkit ja lisätään piste.
		$text = trim($text, "., ");
		if(strlen($text) > 0) {
			$text .= ".";
		}

		$writer->startElement('lido:displayObjectMeasurements');
		$writer->text($text);
		$writer->endElement();
	}

	private static function writeMeasurementsSet($writer, $loyto)
	{
		$writer->startElement('lido:measurementsSet');
		// korkeus
		$writer->startElement('lido:measurementType');
		$writer->text('korkeus');
		$writer->endElement();
		$writer->startElement('lido:measurementUnit');
		$writer->text($loyto['korkeusyksikko']);
		$writer->endElement();
		$writer->startElement('lido:measurementValue');
		$writer->text($loyto['korkeus']);
		$writer->endElement();
		// leveys
		$writer->startElement('lido:measurementType');
		$writer->text('leveys');
		$writer->endElement();
		$writer->startElement('lido:measurementUnit');
		$writer->text($loyto['leveysyksikko']);
		$writer->endElement();
		$writer->startElement('lido:measurementValue');
		$writer->text($loyto['leveys']);
		$writer->endElement();
		// pituus
		$writer->startElement('lido:measurementType');
		$writer->text('pituus');
		$writer->endElement();
		$writer->startElement('lido:measurementUnit');
		$writer->text($loyto['pituusyksikko']);
		$writer->endElement();
		$writer->startElement('lido:measurementValue');
		$writer->text($loyto['pituus']);
		$writer->endElement();
		// paksuus
		$writer->startElement('lido:measurementType');
		$writer->text('paksuus');
		$writer->endElement();
		$writer->startElement('lido:measurementUnit');
		$writer->text($loyto['paksuusyksikko']);
		$writer->endElement();
		$writer->startElement('lido:measurementValue');
		$writer->text($loyto['paksuus']);
		$writer->endElement();
		// paino
		$writer->startElement('lido:measurementType');
		$writer->text('paino');
		$writer->endElement();
		$writer->startElement('lido:measurementUnit');
		$writer->text($loyto['painoyksikko']);
		$writer->endElement();
		$writer->startElement('lido:measurementValue');
		$writer->text($loyto['paino']);
		$writer->endElement();
		// halkaisija
		$writer->startElement('lido:measurementType');
		$writer->text('halkaisija');
		$writer->endElement();
		$writer->startElement('lido:measurementUnit');
		$writer->text($loyto['halkaisijayksikko']);
		$writer->endElement();
		$writer->startElement('lido:measurementValue');
		$writer->text($loyto['halkaisija']);
		$writer->endElement();
		$writer->endElement();
	}

	private static function writeEventWrap($writer, $loyto)
	{
		// Finnaan riittää pelkästään ajoituksen vuosiluvut.
		$ajoitusText = '';
		if ($loyto['alkuvuosi']) {
			$ajoitusText = $loyto['alkuvuosi'];
		}
		if ($loyto['paatosvuosi']) {
			$ajoitusText .= '-' . $loyto['paatosvuosi'];
		}

		$writer->startElement('lido:eventWrap');
		$writer->startElement('lido:eventSet');
		$writer->startElement('lido:event');
		$writer->startElement('lido:eventType');
		$writer->startElement('lido:term');
		$writer->text('valmistus');
		$writer->endElement();
		$writer->endElement();
		$writer->startElement('lido:eventDate');
		$writer->startElement('lido:displayDate');
		$writer->text($ajoitusText);
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
	}

	private function writeObjectRelationWrap($writer, $loyto)
	{
		$writer->startElement('lido:objectRelationWrap');
		self::writeSubjectWrap($writer, $loyto);
		$this->writeRelatedWorksWrap($writer, $loyto);
		$writer->endElement();
	}

	private static function writeSubjectWrap($writer, $loyto)
	{
		$writer->startElement('lido:subjectWrap');
		$writer->startElement('lido:subjectSet');
		$writer->startElement('lido:displaySubject');
		$kuvaus = self::fixKuvausText($loyto['kuvaus']);
		$writer->text($kuvaus);
		$writer->endElement();
		$writer->startElement('lido:subject'); // AIHEEN PAIKKA
		$writer->startElement('lido:subjectPlace');
		$writer->startElement('lido:displayPlace');
		$writer->text(self::getSubjectPlace($loyto));
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		$writer->startElement('lido:subjectSet'); // ASIASANAT
		$writer->startElement('lido:subject');
		$asiasanat = [];
		foreach ($loyto['loydon_asiasanat'] as $asiasana) {
			array_push($asiasanat, $asiasana['asiasana']);
		}
		// 10223: Lisätään lisäksi aiheiksi Maalöydöt, Kaivauslöydöt, Arkeologiset löydöt
		array_push($asiasanat, "Maalöydöt");
		array_push($asiasanat, "Kaivauslöydöt");
		array_push($asiasanat, "Arkeologiset löydöt");

		foreach ($asiasanat as $asiasana) {
			$writer->startElement('lido:subjectConcept');
			$writer->startElement('lido:conceptID');
			$writer->writeAttribute('lido:source', 'maotao');
			$writer->writeAttribute('lido:type', 'URI');
			$writer->text('https://finto.fi/maotao/fi/');
			$writer->endElement();
			$writer->startElement('lido:term');
			$writer->writeAttribute('lido:label', 'asiasana');
			$writer->writeAttribute('xml:lang', 'fi');
			$writer->text($asiasana);
			$writer->endElement();
			$writer->endElement();
		}
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
	}

	// Kunta, kohteen nimi, tutkimuksen nimi
	private static function getSubjectPlace($loyto)
	{
		$tutkimus = $loyto['tutkimus'];

		$kuntanimi = null;
		if (isset($tutkimus['kunnat_kylat']) && sizeof($tutkimus['kunnat_kylat']) > 0 && $tutkimus['kunnat_kylat'][0]['kunta']['nimi']) {
			$kuntanimi = $tutkimus['kunnat_kylat'][0]['kunta']['nimi'];
		}
		$kohde = self::getKohde($tutkimus['id']);
		$kohdenimi = null;

		if(!$kuntanimi) {
			if(isset($kohde) && isset($kohde['kunnatkylat']) && sizeof($kohde['kunnatkylat']) > 0 && isset($kohde['kunnatkylat'][0]['kunta']['nimi'])) {
				$kuntanimi = $kohde['kunnatkylat'][0]['kunta']['nimi'];
			}
		}

		if ($kohde && $kohde['nimi']) {
			$kohdenimi = $kohde['nimi'];
		}
		// 1. Lisätään näytettävään tekstiin kuntanimi
		$displayText = $kuntanimi != null ? $kuntanimi : '';
		if (strlen($displayText) > 0) {
			$displayText .= ', ';
		}
		// 2. Lisätään näytettävään tekstiin kohde
		$displayText .= $kohdenimi != null ? $kohdenimi : '';
		// 3. Lisätään näytettävään tekstiin tutkimus
		$displayText .= strlen($displayText) > 0 ? ', ' . $tutkimus['nimi'] : $tutkimus['nimi'];
		return $displayText;
	}

	private function writeRelatedWorksWrap($writer, $loyto)
	{
		$writer->startElement('lido:relatedWorksWrap');
		$writer->startElement('lido:relatedWorkSet');
		$writer->startElement('lido:relatedWork');
		$writer->startElement('lido:displayObject');
		$writer->text($this->ARKEOLOGINENKOKOELMA);
		$writer->endElement();
		$writer->endElement();
		$writer->startElement('lido:relatedWorkRelType');
		$writer->startElement('lido:term');
		$writer->text($this->KOKOELMA);
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
	}

	private function writeAdministrativeMetadata($writer, $loyto)
	{
		$writer->startElement('lido:administrativeMetadata');
		$writer->writeAttribute('xml:lang', 'fi');
		$this->writeRightsWorkWrap($writer, $loyto);
		$this->writeRecordWrap($writer, $loyto);
		$this->writeResourceWrap($writer, $loyto);

		$writer->endElement();
	}

	private function writeRightsWorkWrap($writer, $loyto)
	{
		$writer->startElement('lido:rightsWorkWrap');
		$writer->startElement('lido:rightsWorkSet');
		$writer->startElement('lido:rightsHolder');
		$writer->startElement('lido:legalBodyName');
		$writer->startElement('lido:appellationValue');
		$writer->text($this->ORGANISATION);
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
	}

	private function writeRecordWrap($writer, $loyto)
	{
		$writer->startElement('lido:recordWrap');
		$writer->startElement('lido:recordID');
		$writer->writeAttribute('lido:type', 'local');
		$writer->text($loyto['id']);
		$writer->endElement();
		$writer->startElement('lido:recordType');
		$writer->startElement('lido:term');
		$writer->text('item');
		$writer->endElement();
		$writer->endElement();
		$writer->startElement('lido:recordSource');
		$writer->startElement('lido:legalBodyName');
		$writer->startElement('lido:appellationValue');
		$writer->text($this->ORGANISATION);
		$writer->endElement();
		$writer->endElement();
		$writer->startElement('lido:legalBodyWeblink');
		$writer->text($this->MUSEOURL);
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
	}

	private function writeResourceWrap($writer, $loyto)
	{
		// Haetaan erikokoiset kuvat löydölle
		$kuva = ArkKuva::loytoTunnistekuva($loyto['id'])->first();
		if ($kuva && $kuva->polku && $kuva->tiedostonimi) {
			$kuva->makeVisible(['polku']); // Hidden by default
			/*
             * $urls->original, ->tiny, ->small, ->medium, ->large
             */

			// HUOM: Testatessa lokaalissa ympäristössä voidaan tarvita kovakoodattu linkki olemassaolevaan kuvaan
			// koska kuvan tietoja ei saada jos tiedostoa ei löydy yritetystä sijainnista.

			$storagePath = storage_path(config('app.image_upload_path'));
			$urls = ArkKuva::getImageUrls($kuva->polku . $kuva->tiedostonimi);

			// Huom: Originaali kuva säilyy alkuperäisellä tiedostopäättellä, tiny, small, medium ja large tallennetaan aina jpg-muodossa
			$fileNameArr = explode('.', $kuva->tiedostonimi);
			$fileNameSmall = $fileNameArr[0] . '_SMALL.jpg';
			$fileNameLarge = $fileNameArr[0] . '_LARGE.jpg';
			$fileNameOriginal = $kuva->tiedostonimi;

			// Jos kuvatiedostoa ei löydy jätetään kuvatieto kirjoittamatta ja kirjoitetaan logiin tieto, mutta ei lopeteta siirtoa muuten
			try {
				$fileSizeSmall = File::size($storagePath . $kuva->polku . $fileNameSmall);
				$fileSizeLarge = File::size($storagePath . $kuva->polku . $fileNameLarge);
				$fileSizeOriginal = File::size($storagePath . $kuva->polku . $fileNameOriginal);

				// data[0] = width, data[1] = height
				$dataSmall = getimagesize($storagePath . $kuva->polku . $fileNameSmall);
				$dataLarge = getimagesize($storagePath . $kuva->polku . $fileNameLarge);
				$dataOriginal = getimagesize($storagePath . $kuva->polku . $fileNameOriginal);
				$originalFileType = File::extension($urls->original);

				$writer->startElement('lido:resourceWrap');
				$writer->startElement('lido:resourceSet');
				$writer->startElement('lido:resourceID');
				$writer->writeAttribute('lido:type', 'urn');
				$writer->text('urn:mip.turku.fi:' . $kuva->id);
				$writer->endElement();
				self::writeResourceRepresentation($writer, $loyto, 'image_thumb', $urls->small, $fileSizeSmall, $dataSmall[0], $dataSmall[1]);
				self::writeResourceRepresentation($writer, $loyto, 'image_large', $urls->large, $fileSizeLarge, $dataLarge[0], $dataLarge[1]);
				self::writeResourceRepresentation($writer, $loyto, 'image_original', $urls->original, $fileSizeOriginal, $dataOriginal[0], $dataOriginal[1], $originalFileType);
				$writer->startElement('lido:resourceType');
				$writer->startElement('lido:term');
				$writer->text('valokuva');
				$writer->endElement();
				$writer->endElement();
				$writer->startElement('lido:resourceRelType');
				$writer->startElement('lido:term');
				$writer->text('dokumentointikuva');
				$writer->endElement();
				$writer->endElement();
				$writer->startElement('lido:resourceDescription');
				$writer->writeAttribute('lido:type', 'photographer'); // Sovittiin Finnalaisten kanssa, että laitetaan kuvaaja myös tähän
				$writer->text($kuva->kuvaaja);
				$writer->endElement();
				//Jätetty pois kuvaussuuntaa yms
				$writer->startElement('lido:rightsResource');
				$writer->startElement('lido:rightsType');
				$writer->startElement('lido:conceptID');
				$writer->writeAttribute('lido:type', 'copyright');
				$writer->text('CC BY 4.0');
				$writer->endElement();
				$writer->startElement('lido:term');
				$writer->writeAttribute('xml:lang', 'fi');
				$writer->text($kuva->tekijanoikeuslauseke);
				$writer->endElement();
				$writer->endElement(); //rightsType
				$writer->startElement('lido:rightsHolder');
				$writer->startElement('lido:legalBodyName');
				$writer->startElement('lido:appellationValue');
				$writer->text($this->ORGANISATION);
				$writer->endElement();
				$writer->endElement();
				$writer->startElement('lido:legalBodyWeblink');
				$writer->text($this->MUSEOURL);
				$writer->endElement();
				$writer->endElement();
				$writer->startElement('lido:creditLine');
				$writer->text($kuva->kuvaaja);
				$writer->endElement();
				$writer->endElement(); // rightsResource
				$writer->endElement(); // resourceSet
				$writer->endElement(); // resourceWrap
			} catch (Exception $e) {
				Log::channel('finna')->error("No image file found for loyto: " . $loyto['id'] . ", image: " . $kuva['id']);
			}
		}
	}

	private static function writeResourceRepresentation($writer, $loyto, $type, $url, $fileSize, $width, $height, $fileType = null)
	{
		$writer->startElement('lido:resourceRepresentation');
		$writer->writeAttribute('lido:type', $type);
		$writer->startElement('lido:linkResource');
		if ($fileType != null) {
			$writer->writeAttribute('lido:formatResource', $fileType);
		}
		$writer->text($url);
		$writer->endElement();
		// Filesize
		$writer->startElement('lido:resourceMeasurementsSet');
		$writer->startElement('lido:measurementType');
		$writer->writeAttribute('xml:lang', 'fi');
		$writer->text('koko');
		$writer->endElement();
		$writer->startElement('lido:measurementType');
		$writer->writeAttribute('xml:lang', 'en');
		$writer->text('size');
		$writer->endElement();
		$writer->startElement('lido:measurementUnit');
		$writer->text('byte');
		$writer->endElement();
		$writer->startElement('lido:measurementValue');
		$writer->text($fileSize);
		$writer->endElement();
		$writer->endElement(); // resourceMeasurementsSet
		// Leveys
		$writer->startElement('lido:resourceMeasurementsSet');
		$writer->startElement('lido:measurementType');
		$writer->writeAttribute('xml:lang', 'fi');
		$writer->text('leveys');
		$writer->endElement();
		$writer->startElement('lido:measurementType');
		$writer->writeAttribute('xml:lang', 'en');
		$writer->text('width');
		$writer->endElement();
		$writer->startElement('lido:measurementUnit');
		$writer->text('pixel');
		$writer->endElement();
		$writer->startElement('lido:measurementValue');
		$writer->text($width);
		$writer->endElement();
		$writer->endElement(); // resourceMeasurementsSet
		// Korkeus
		$writer->startElement('lido:resourceMeasurementsSet');
		$writer->startElement('lido:measurementType');
		$writer->writeAttribute('xml:lang', 'fi');
		$writer->text('korkeus');
		$writer->endElement();
		$writer->startElement('lido:measurementType');
		$writer->writeAttribute('xml:lang', 'en');
		$writer->text('height');
		$writer->endElement();
		$writer->startElement('lido:measurementUnit');
		$writer->text('pixel');
		$writer->endElement();
		$writer->startElement('lido:measurementValue');
		$writer->text($height);
		$writer->endElement();
		$writer->endElement(); // resourceMeasurementsSet
		$writer->EndElement(); // resourceRepresentation
	}

	private static function writeResumptionToken($writer, $cursor, $completeListSize)
	{
		$writer->startElement('resumptionToken');
		$writer->writeAttribute('cursor', $cursor);
		$writer->writeAttribute('completeListSize', $completeListSize);
		$writer->text('finna.17.' . $cursor);
		$writer->endElement();

		Log::channel('finna')->info('ResumptionToken: <resumptionToken cursor="' . $cursor . '" completeListSize="' . $completeListSize . '">finna.17.' . $cursor . '</resumptionToken>');
	}


	// Tietueiden haku
	private function getLoydot($from = null, $until = null)
	{
		$loydot = Loyto::getAllForFinna()
			->withDate($from, $until)
			->with(array(
				'yksikko.tutkimusalue.tutkimus.loytoKokoelmalaji',
				'yksikko.tutkimusalue.tutkimus.kohde',
				'yksikko.tutkimusalue.tutkimus.kunnatKylat.kunta',
				'materiaalikoodi',
				'ensisijainenMateriaali',
				'materiaalit', // hakee välitaulun avulla muut löydön materiaalit
				'loytotyyppi',
				'loytotyyppiTarkenteet',
				'merkinnat',
				'loydonAsiasanat',
				'tutkimusalue.tutkimus.kohde', //IRTOLÖYTÖ tai tarkastus
				'tutkimusalue.tutkimus.kunnatKylat.kunta',
				'finnaLog' => function ($q) {
					$q->orderBy('id', 'desc')->first();
				}
			))->distinct();
		// Rivien määrän laskenta resumptionTokenia varten
		$this->completeListSize = Utils::getCount($loydot);

		Log::channel('finna')->info("Cursor position: " . $this->cursor);

		$loydot = $loydot->withLimit($this->cursor, $this->HAKUMAARA + 1)->orderBy('ark_loyto.id', 'asc');
		// Sorttaus ja haku
		$loydot = $loydot->orderBy('ark_loyto.id', 'asc')->get();

		// Asetetaan tutkimus siten, että se löytyy suoraan löydöltä
		foreach ($loydot as $loyto) {
			if ($loyto->yksikko == null) {
				$loyto->tutkimus = $loyto->tutkimusalue->tutkimus;
				unset($loyto->yksikko);
			} else if ($loyto->tutkimusalue == null) {
				$loyto->tutkimus = $loyto->yksikko->tutkimusalue->tutkimus;
				unset($loyto->tutkimusalue);
			}
		}
		// Palautetaan array, jostain syystä relaatiot jäävät palauttamatta muutoin.
		return $loydot->toArray();
	}

	private function getLoyto($id)
	{
		$loyto = Loyto::getSingle($id)->with(array(
			'yksikko.tutkimusalue.tutkimus.loytoKokoelmalaji',
			'yksikko.tutkimusalue.tutkimus.kunnatKylat.kunta',
			'yksikko.tutkimusalue.tutkimus.kohde',
			'materiaalikoodi',
			'ensisijainenMateriaali',
			'materiaalit', // hakee välitaulun avulla muut löydön materiaalit
			'loytotyyppi',
			'loytotyyppiTarkenteet',
			'merkinnat',
			'loydonAsiasanat',
			'tutkimusalue.tutkimus.kohde', // IRTOLÖYTÖ tai tarkastus
			'tutkimusalue.tutkimus.kunnatKylat.kunta'
		));

		// Asetetaan tutkimus siten, että se löytyy suoraan löydöltä
		$loyto = $loyto->first();
		if ($loyto->yksikko == null) {
			$loyto->tutkimus = $loyto->tutkimusalue->tutkimus;
			unset($loyto->yksikko);
		} else if ($loyto->tutkimusalue == null) {
			$loyto->tutkimus = $loyto->yksikko->tutkimusalue->tutkimus;
			unset($loyto->tutkimusalue);
		}
		// Palautetaan array, jostain syystä relaatiot jäävät palauttamatta muutoin.
		return $loyto->toArray();
	}

	private static function getKohde($tutkimusId)
	{
		// Hae välitaulusta mahdollinen kohteen id
		$kohdeTutkimus = KohdeTutkimus::select('ark_kohde_tutkimus.*')->where('ark_tutkimus_id', '=', $tutkimusId)->first();
		if (!empty($kohdeTutkimus)) {
			$kohde = Kohde::getSingle($kohdeTutkimus->ark_kohde_id)->with(array(
				'kunnatkylat.kunta',
				'kunnatkylat.kyla'
			))->first()->toArray();
			return $kohde;
		}

		return null;
	}
}
