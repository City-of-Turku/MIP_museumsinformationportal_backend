<?php
namespace App\Integrations;

use App\Kunta;
use App\Utils;
use App\Ark\AlakohdeAjoitus;
use App\Ark\AlakohdeSijainti;
use App\Ark\Kohde;
use App\Ark\KohdeAjoitus;
use App\Ark\KohdeAlakohde;
use App\Ark\KohdeKuntaKyla;
use App\Ark\KohdeMjrTutkimus;
use App\Ark\KohdeSijainti;
use App\Ark\KohdeTyyppi;
use App\Ark\KohdeVanhaKunta;
use App\Library\Gis\MipGis;
use App\Library\String\MipJson;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Integraatio Museoviraston muinaisjäännösrekisterin rajapintapalveluun.
 */
class KyppiService{

    private $username;
    private $password;
    private $kyppiMuinaisjaannosUri;
    private $kyppiMuinaisjaannosEndpoint;

    /*
     * Arvot asetetaan .env tiedostossa
     */
    function __construct() {

        $this->username = config('app.kyppi_username');
        $this->password = config('app.kyppi_password');
        $this->kyppiMuinaisjaannosUri = config('app.kyppi_muinaisjaannos_uri');
        $this->kyppiMuinaisjaannosEndpoint = config('app.kyppi_muinaisjaannos_endpoint');

    }

    /**
     * Palauttaa yksittäisen muinaisjäännöksen tiedot xml-muodossa tai pelkän muutospäivän, jos $vainPvm = true. Haku muinaisjäännöstunnuksella.
     * Kyppi-rajapinta: HaeMuinaisjaannos
     * Mallitunnus: 538000003
     * @param muinaisjaannostunnus
     * @param $vainPvm true/false
     */
    public function haeMuinaisjaannosSoap($id, $vainPvm) {

        $soapHeader = $this->muodostaSoapHeader();

        $soapBody = '<soapenv:Body>';
        $soapBody .=    '<mjr:HaeMuinaisjaannos>';
        $soapBody .=        '<mjr:muinaisjaannostunnus>' . $id . '</mjr:muinaisjaannostunnus>';
        $soapBody .=    '</mjr:HaeMuinaisjaannos>';
        $soapBody .= '</soapenv:Body>';

        $soapFooter = '</soapenv:Envelope>';
        $xmlRequest = $soapHeader . $soapBody . $soapFooter;

        try {

            Log::channel('kyppi')->info('KyppiService: haeMuinaisjaannos SOAP mj-tunnus = ' . $id);
            Log::channel('kyppi')->info('KyppiService: endpoint = ' . $this->kyppiMuinaisjaannosEndpoint);

            $time_start = microtime(true);

            try {
                $endpoint = $this->kyppiMuinaisjaannosEndpoint;

                $client = new Client();
                $response = $client->request("POST", $endpoint, [
                    'headers' => [
                        'Content-Type' => 'text/xml',
                        'SOAPAction' => $this->kyppiMuinaisjaannosUri . 'HaeMuinaisjaannos'
                    ],
                    'body' => $xmlRequest
                ]);

                if ($response->getStatusCode() != Response::HTTP_OK) {
                    throw new Exception(" HaeMuinaisjaannos-kutsu epäonnistui: " . $response->getStatusCode() . " : " . $response->getReasonPhrase());
                }
            } catch (Exception $e) {
                Log::channel('kyppi')->error('haeMuinaisjaannosSoap virhe: ' .$e);

                throw $e;
            }

            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start);

            Log::channel('kyppi')->info('Kyppi vastausaika: '.round($execution_time, 2) .' sec');

            if($vainPvm){

                $body = $response->getBody();

                $dom = new \DOMDocument('1.0', 'utf-8');
                $dom->preserveWhiteSpace = false;

                $dom->loadXML($body);
                $muinaisjaannos = $dom->getElementsByTagName( 'muinaisjaannos' )->item(0);
                $kyppiMuutosPvm = null;

                if($muinaisjaannos){
                    foreach ($muinaisjaannos->childNodes as $item) {
                        if($item->nodeName == 'muutettu'){
                            $kyppiMuutosPvm = $item->nodeValue;
                            break;
                        }
                    }
                }

                return $kyppiMuutosPvm;

            }else{
                // Palautetaan koko xml
                return $response->getBody();
            }

        } catch (Exception $e) {
            Log::channel('kyppi')->error('Exception: ' . $e);
            throw new Exception(" haeMuinaisjaannos-kutsu epäonnistui: " .$e);
        }
    }

    /**
     * Muodostaa ja tallentaa yhden muinaisjäännöksen Kohde-tiedot DomDocument xml tiedoilla.
     * Päivittää kohteen kyppi_status tiedon.
     *
     * @param DOMDocument xml Kyppi scheman mukaan
     * @param $mipTallennus = Tietojen päivitys UI:sta valittu
     */
    public function lisaaTaiPaivitaKohde($dom, $kohde, $mipTallennus) {

        $isUpdate = false;

        if(empty($kohde)){

            Log::channel('kyppi')->info('Lisaa kohde');

            // Uusi Kohde model
            $kohde = new Kohde();

            // Kohteen status = uusi
            $kohde->kyppi_status = '1';

        }else{
            Log::channel('kyppi')->info('Paivita kohde ' .$kohde->muinaisjaannostunnus);
            $isUpdate = true;

            // Kohteen status = päivitetty
            $kohde->kyppi_status = '2';
        }

        // Tätä käytettiin aiemmin tunnistamaan, että ollaan valittu tietojen tuonti (Päivitä) UI:n kautta.
        if($mipTallennus){
            $kohde->kyppi_status = null;
        }

        // <muinaisjaannos> elementin alta löytyy kaikki tarvittava
        $muinaisjaannos = $dom->getElementsByTagName( 'muinaisjaannos' )->item(0);

        Log::channel('kyppi')->debug($dom->saveXML());

        // Kohteen luonti ja tietojen populointi
        $this->muodostaKohdeKypinTiedoilla($muinaisjaannos, $kohde);

        // Tallenna kohde ja relaatiot
        if( $this->tallennaKohde($kohde, $dom, $muinaisjaannos, $isUpdate) ){
            // Lokitusta
            Log::channel('kyppi')->info('Kyppi-palvelusta tuotu kohde onnistuneesti');
            Log::channel('kyppi')->info('Kohde save = ' .$kohde);

            // Onnistunut tuonti
            MipJson::setGeoJsonFeature(null, array("id" => $kohde->id));
            MipJson::addMessage(Lang::get('kohde.save_success'));
            MipJson::setResponseStatus(Response::HTTP_OK);

        }else{
            Log::channel('kyppi')->info('Kohteen tallennus ei onnistunut, tarkista lokit = ' .$kohde);

            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kohde.save_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Yöllinen ajo päivittää kohteelle statuksen, jos uusi tai muokattu.
     * Timestampit jätetään päivittämättä jotta vertailu muokattu vs mj-rekisterin muutos saadaan kiinni.
     */
    public function paivitaKohteenStatus($kohde) {

        Log::channel('kyppi')->info('Paivita kohde status ' .$kohde->muinaisjaannostunnus);

        // Kohteen status = päivitetty
        $kohde->kyppi_status = '2';

        try {

            DB::beginTransaction();
            Utils::setDBUserAsSystem();

            // Estetään päivämäärän päivittyminen, koska vain status tieto halutaan päivittää
            $kohde->timestamps = false;
            $kohde->update();

            DB::commit();

        } catch(Exception $e) {
            // Rollback jos virhe
            DB::rollback();

            Log::channel('kyppi')->error('KyppiService paivitaKohteenStatus virhe: ' .$e);
        }
        return MipJson::getJson();
    }

    /**
     * Integraation alustus. Tällä tehdään kertaluontoinen muinaisjäännösten tuonti Kyppi xml datan mukaan.
     * Kutsutaan ark_kohdeSeeder.php luokasta eli ajetaan konsolista.
     * Muodostaa ja tallentaa Kohde-tiedot DomDocument xml tiedoilla.
     *
     * @param DOMDocument xml Kyppi scheman mukaan
     */
    public function tuoKohteetKypista($dom) {

        // <muinaisjaannos> elementin alta löytyy kaikki tarvittava
        $muinaisjaannos = $dom->getElementsByTagName( 'muinaisjaannos' )->item(0);

        // Kohteen luonti ja tietojen populointi
        $kohde = new Kohde();
        $this->muodostaKohdeKypinTiedoilla($muinaisjaannos, $kohde);

        // Tallenna kohde ja relaatiot
        if( $this->tallennaKohde($kohde, $dom, $muinaisjaannos, false) ){
            Log::channel('kyppi')->info('Kohde = ' .$kohde);

        }else{
            Log::channel('kyppi')->info('Kohteen tallennus ei onnistunut, tarkista lokit ' .$kohde);
        }

    }

    /**
     * Hakee annetun päivämäärän jälkeen luodut (avattu) ja muutetut muinaisjäännökset. Palauttaa tiedot xml-muodossa
     * Kyppi-rajapinta: HaeMuinaisjaannokset
     * @param  $muutettuPvm = muutettu pvm jota uudemmat tiedot haetaan
     */
    public function haeMuinaisjaannoksetSoap($muutettuPvm, $kunta, $maakunta){

        try {
            /*
             * Kyppi rajapinnan parametrit queryyn.
             * Kunta voi olla Ypäjä, jolloin maakunta parametri tyhjennetään. Muuten aina maakunta on Varsinais-Suomi ja kuntaa ei anneta.
             */
            if( empty($kunta) ){

                Log::channel('kyppi')->info('KyppiService: haeMuinaisjaannokset pvm = ' . $muutettuPvm . " " . $maakunta );

                // Oletus
                $maakunta = $maakunta;
                $kunta = null;
            } else {

                Log::channel('kyppi')->info('KyppiService: haeMuinaisjaannokset pvm = ' . $muutettuPvm . " " . $kunta );

                $maakunta = null;
            }

            // Muodostetaan soap
            $soapHeader = $this->muodostaSoapHeader();

            $soapBody = '<soapenv:Body>';
            $soapBody .=    '<mjr:HaeMuinaisjaannokset>';

            if($kunta){
                $soapBody .=   '<mjr:kunta>' . $kunta . '</mjr:kunta>';
            }else{
                $soapBody .=   '<mjr:kunta></mjr:kunta>';
            }

            if($maakunta){
                $soapBody .=   '<mjr:maakunta>' . $maakunta . '</mjr:maakunta>';
            }else{
                $soapBody .=   '<mjr:maakunta></mjr:maakunta>';
            }

            $soapBody .=        '<mjr:luontileima></mjr:luontileima>';
            $soapBody .=        '<mjr:muutosleima>' . $muutettuPvm . '</mjr:muutosleima>';
            $soapBody .=        '<mjr:omistajasuodatus></mjr:omistajasuodatus>';
            $soapBody .=    '</mjr:HaeMuinaisjaannokset>';
            $soapBody .= '</soapenv:Body>';

            $soapFooter = '</soapenv:Envelope>';
            $xmlRequest = $soapHeader . $soapBody . $soapFooter;
            // Log::channel('kyppi')->debug($xmlRequest);
            // Aika alkaa nyt
            $time_start = microtime(true);

            try {
                $endpoint = $this->kyppiMuinaisjaannosEndpoint;

                $client = new Client();
                $response = $client->request("POST", $endpoint, [
                    'headers' => [
                        'Content-Type' => 'text/xml;charset=UTF-8',
                        'SOAPAction' => $this->kyppiMuinaisjaannosUri . 'HaeMuinaisjaannokset'
                    ],
                    'body' => $xmlRequest
                ]);

                if ($response->getStatusCode() != Response::HTTP_OK) {
                    throw new Exception(" HaeMuinaisjaannokset-kutsu epäonnistui: " . $response->getStatusCode() . " : " . $response->getReasonPhrase());
                }
            } catch (Exception $e) {
                Log::channel('kyppi')->error('haeMuinaisjaannoksetSoap virhe: ' .$e);

                throw $e;
            }

            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start);

            Log::channel('kyppi')->info('Kyppi HaeMuinaisjaannokset vastausaika: '.round($execution_time, 2) .' sec');

            return $response->getBody();

        } catch (Exception $e) {
            Log::channel('kyppi')->error('KyppiService exception: ' .$e);
            throw new Exception(" HaeMuinaisjaannokset-kutsu epäonnistui: " .$e);
        }
    }

    /**
     * Muinaisjäännösten testaukseen esim. Postman clientilla.
     * Aseta ensin Bearer token.
     * GET: http://localhost:8000/api/kyppi/haemuinaisjaannokset/2020-08-01
     */
    public function haeMuinaisjaannoksetSoapTesti($muutettuPvm, $maakunta, $kunta) {

        $soapHeader = $this->muodostaSoapHeader();

        $soapBody = '<soapenv:Body>';
        $soapBody .=    '<mjr:HaeMuinaisjaannokset>';
        $soapBody .=        '<mjr:kunta>' . $kunta . '</mjr:kunta>';
        $soapBody .=        '<mjr:maakunta>' . $maakunta . '</mjr:maakunta>';
        $soapBody .=        '<mjr:luontileima></mjr:luontileima>';
        $soapBody .=        '<mjr:muutosleima>' . $muutettuPvm . '</mjr:muutosleima>';
        $soapBody .=        '<mjr:omistajasuodatus></mjr:omistajasuodatus>';
        $soapBody .=    '</mjr:HaeMuinaisjaannokset>';
        $soapBody .= '</soapenv:Body>';

        $soapFooter = '</soapenv:Envelope>';
        $xmlRequest = $soapHeader . $soapBody . $soapFooter;

        Log::channel('kyppi')->info('Request: '.$xmlRequest);

        // Aika alkaa nyt
        $time_start = microtime(true);

        try {
            $endpoint = $this->kyppiMuinaisjaannosEndpoint;

            $client = new Client();
            $response = $client->request("POST", $endpoint, [
                 'http_errors' => true,
                 'headers' => [
                     'Content-Type' => 'text/xml',
                     'SOAPAction' => $this->kyppiMuinaisjaannosUri . 'HaeMuinaisjaannokset'
                 ],
                 'body' => $xmlRequest
             ]);

            if ($response->getStatusCode() != Response::HTTP_OK) {
                throw new Exception(" HaeMuinaisjaannokset-kutsu epäonnistui: " . $response->getStatusCode() . " : " . $response->getReasonPhrase());
            }
        } catch (Exception $e) {
            Log::channel('kyppi')->error('haeMuinaisjaannoksetSoap virhe: ' .$e);

            throw $e;

        }

        $time_end = microtime(true);
        $execution_time = ($time_end - $time_start);

        Log::channel('kyppi')->info('Kyppi HaeMuinaisjaannokset vastausaika: '.round($execution_time, 2) .' sec');

        return $response->getBody();
    }

    /**
     * Lisää muinaisjäännöksen muinaisjäännösrekisteriin (Kyppi) ja palauttaa muinaisjäännöstunnuksen.
     * Kyppi-rajapinta: LisaaMuinaisjaannos
     */
    public function lisaaMuinaisjaannos($xml) {

        $soapHeader = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $soapHeader .= '<soap:Header>';
        $soapHeader .= '<TunnistusHeader xmlns="http://paikkatieto.nba.fi/mvrpp/MjrekiRajapinta/">';
        $soapHeader .=      '<Tunnus>' . $this->username . '</Tunnus>';
        $soapHeader .=      '<Salasana>' . $this->password . '</Salasana>';
        $soapHeader .= '</TunnistusHeader>';
        $soapHeader .= '</soap:Header>';

        $soapBody = '<soap:Body>';
        $soapBody .=    '<LisaaMuinaisjaannos xmlns="http://paikkatieto.nba.fi/mvrpp/MjrekiRajapinta/" xmlns:gml="http://www.opengis.net/gml">';
        $soapBody .=        '<muinaisjaannos>';
        $soapBody .= $xml;
        $soapBody .=        '</muinaisjaannos>';
        $soapBody .=    '</LisaaMuinaisjaannos>';
        $soapBody .= '</soap:Body>';

        $soapFooter = '</soap:Envelope>';
        $xmlRequest = $soapHeader . $soapBody . $soapFooter;

        Log::channel('kyppi')->info('LisaaMuinaisjaannos Request: '.$xmlRequest);

        // Aika alkaa nyt
        $time_start = microtime(true);

        try {
            $endpoint = $this->kyppiMuinaisjaannosEndpoint;

            $client = new Client();
            $response = $client->request("POST", $endpoint, [
                'headers' => [
                    'Content-Type' => 'text/xml;charset=UTF-8',
                    'SOAPAction' => $this->kyppiMuinaisjaannosUri . 'LisaaMuinaisjaannos'
                ],
                'body' => $xmlRequest
            ]);

            if ($response->getStatusCode() != Response::HTTP_OK) {
                throw new Exception(" LisaaMuinaisjaannos-kutsu epäonnistui: " . $response->getStatusCode() . " : " . $response->getReasonPhrase());
            }
        } catch (Exception $e) {
            Log::channel('kyppi')->error('LisaaMuinaisjaannos virhe: ' .$e);

            throw $e;

        }

        $time_end = microtime(true);
        $execution_time = ($time_end - $time_start);

        Log::channel('kyppi')->info('Kyppi LisaaMuinaisjaannos vastausaika: '.round($execution_time, 2) .' sec');

        $body = $response->getBody();

        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;

        $dom->loadXML($body);

        // Palautetaan uusi muinaisjäännöstunnus tai -1 palautuu, jos luominen epäonnistuu.
        $muinaisjaannostunnus = $dom->getElementsByTagName( 'LisaaMuinaisjaannosResult' )->item(0)->nodeValue;

        Log::channel('kyppi')->info('Muinaisjäännöstunnus luotu: '.$muinaisjaannostunnus);

        return $muinaisjaannostunnus;
    }

    /**
     * Muodosta Kyppi soap header
     */
    private function muodostaSoapHeader(){
        $soapHeader  = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:mjr="http://paikkatieto.nba.fi/mvrpp/MjrekiRajapinta/">';
        $soapHeader .= '<soapenv:Header>';
        $soapHeader .= '<mjr:TunnistusHeader>';
        $soapHeader .=      '<mjr:Tunnus>' . $this->username . '</mjr:Tunnus>';
        $soapHeader .=      '<mjr:Salasana>' . $this->password . '</mjr:Salasana>';
        $soapHeader .= '</mjr:TunnistusHeader>';
        $soapHeader .= '</soapenv:Header>';
        return $soapHeader;
    }

    /**
     * Kohde tietojen ja sen relaatioiden tallennus
     * $kohde model
     * $dom koko DomDocument
     * $muinaisjaannos DomElement
     */
    private function tallennaKohde($kohde, $dom, $muinaisjaannos, $isUpdate){

        try {

            DB::beginTransaction();
            Utils::setDBUserAsSystem();

            if($isUpdate){
                // Päivitä kohde
                $kohde->muokkaaja = Utils::getSystemUserId();
                $kohde->touch(); // tällä saadaan päätason muutokset muutoshistoriaan.
                $kohde->update();
            }else{
                // Luo kohde
                $kohde->save();
            }

            /*
             * Relaatiotaulujen tiedot. Poistetaan aina vanhat ja luodaan uusilla arvoilla.
             */

            // <tyyppitiedot> alta tyypit ja tarkenteet <alatyyppi>
            $this->muodostaKohdeTyypit($kohde, $muinaisjaannos);

            // Kohteen ajoitukset.
            $this->muodostaKohdeAjoitukset($kohde, $dom, $muinaisjaannos);

            // Kunta
            $this->muodostaKohdeKunta($kohde, $muinaisjaannos);


            // Hae kohteen pisteen sijainti
            $kohdePiste = $this->muodostaKohteenPisteSijainti($kohde, null, $dom, $muinaisjaannos);
            DB::table('ark_kohde_sijainti')->where('kohde_id', $kohde->id)->delete();

            if( !empty($kohdePiste)){
                $kohde->sijainnit()->save($kohdePiste);
            }

            // Hae alueiden sijainnit, voi olla useita
            $aluesijainnit = $this->muodostaKohteenAlueSijainnit($kohde, $dom, $muinaisjaannos);

            if( !empty($aluesijainnit) ){
                foreach ($aluesijainnit as $aluesijainti) {
                    $kohde->sijainnit()->save($aluesijainti);
                }
            }

            // Alakohteiden käsittelyt.
            $this->muodostaAlakohteet($kohde, $dom, $muinaisjaannos, $isUpdate);
            
            // Kohteen tutkimustiedot
            $this->muodostaTutkimukset($kohde, $muinaisjaannos);

            // Vanhat kunnat
            $this->muodostaVanhatKunnat($kohde, $muinaisjaannos);

            DB::commit();
            // Tallennus ok
            return true;

        } catch(Exception $e) {
            // Rollback jos virhe
            DB::rollback();

            Log::channel('kyppi')->error('KyppiService tallennaKohde virhe: ' .$e);
            return false;
        }
    }

    /**
     * Kohde modelin päätason tietojen asetus Kyppi tietojen mukaan
     * @param $muinaisjaannos = <muinaisjaannos> elementti kokonaisuus
     */
    private function muodostaKohdeKypinTiedoilla($muinaisjaannos, $kohde){

        // <muinaisjaannos> elementin alta suoraan löytyvien tietojen läpikäynti ja asetus kohteelle
        $kohde = $this->haePaatasonArvot($muinaisjaannos, $kohde);

        // Kohteen luojana system
        $author_field = Kohde::CREATED_BY;
        $kohde->$author_field = Utils::getSystemUserId();

        return $kohde;
    }

    /**
     * Kohteen tai alakohteen pisteen sijainnin muodostus
     * <paikkatiedot> elementin alta haetaan piste
     */
    private function muodostaKohteenPisteSijainti($kohde, $alakohde, $dom, $contextNode)
    {
        $xpath = $this->luoXpath($dom);
        $query = './kyppi:paikkatiedot/kyppi:pistetieto/gml:pos';
        $gmlPos = $xpath->query($query, $contextNode);

        if ($gmlPos->length > 0) {
            $lonLat = explode(" ", $gmlPos->item(0)->nodeValue);
            $geom = MipGis::haeEpsg3067Sijainti($lonLat[0], $lonLat[1]);
            if ($alakohde) {
                return $this->muodostaAlakohdesijainti($alakohde, $geom);
            } else {
                return $this->muodostaKohdesijainti($kohde, $geom);
            }
        } else {
            if ($alakohde) {
                Log::channel('kyppi')->error('Alakohteen sijaintitieto puuttuu: ' . $alakohde->id);
            } else {
                Log::channel('kyppi')->error('Sijaintitieto puuttuu: ' . $kohde->muinaisjaannostunnus);
            }
        }
    }

    /**
     * Alueiden sijaintien muodostus.
     * <paikkatiedot> elementin alta haetaan polygon tiedot
     */
    private function muodostaKohteenAlueSijainnit($kohde, $dom, $muinaisjaannos){

        $xpath = $this->luoXpath($dom);

        // <aluetieto> alta löytyy Polygon alue <gml:posList>. Alueita voi olla useita, joten tehdään omat sijainnit kaikille
        $query = './kyppi:paikkatiedot/kyppi:aluetieto/gml:surfaceMember/gml:Polygon/gml:exterior/gml:LinearRing/gml:posList';
        $gmlPosList = $xpath->query($query, $muinaisjaannos);

        if($gmlPosList->length > 0){

            $aluesijainnit = array();
            $i = 0;
            foreach ($gmlPosList as $poslist) {

                // Kyppi palauttaa kaikki pisteet välilyönneillä eroteltuna -> muunnetaan pareiksi pilkulla erotelluksi arrayksi
                $polygonArray = $this->explodeByCount(' ', $poslist->nodeValue, 2);

                // Muunnetaan array stringiksi
                $polygonPisteet = implode(',', $polygonArray);

                // Muodostetaan geometry arvo
                $aluerajaus = MipGis::haeEpsg3067PolygonSijainti($polygonPisteet);

                // Kohdesijainnin luonti
                $alueSijainti = $this->muodostaKohdesijainti($kohde, $aluerajaus);

                // Lisää kohteen alueen sijainnit
                $aluesijainnit[] =  $alueSijainti;

                $i++;
            }

            return $aluesijainnit;
        }
    }

    /**
     * <muinaisjaannos> elementin alta löytyvien yksitasoisten tietojen asetus Kohde objektille.
     */
    private function haePaatasonArvot($muinaisjaannos, $kohde){

        foreach ($muinaisjaannos->childNodes as $item) {

            if($item->nodeName == 'muinaisjaannostunnus'){
                $kohde->muinaisjaannostunnus = $item->nodeValue;
            }

            if($item->nodeName == 'nimi'){
                $kohde->nimi = $item->nodeValue;
            }

            if($item->nodeName == 'muutnimet'){
                $kohde->muutnimet = $item->nodeValue;
            }

            if($item->nodeName == 'maakuntanimi'){
                $kohde->maakuntanimi = $item->nodeValue;
            }

            if($item->nodeName == 'tarkenne'){
                $kohde->tarkenne = $item->nodeValue;
            }

            if($item->nodeName == 'jarjestysnumero'){
                $kohde->jarjestysnumero = $item->nodeValue;
            }

            if($item->nodeName == 'laji'){
                $kohde->ark_kohdelaji_id = $this->haeValintalistanId('ark_kohdelaji', $item->nodeValue);
            }

            if($item->nodeName == 'vedenalainen'){
                $kohde->vedenalainen = $item->nodeValue;
            }

            if($item->nodeName == 'suojelukriteeri'){
                $kohde->suojelukriteeri = $item->nodeValue;
            }

            if($item->nodeName == 'rauhoitusluokka'){
                $kohde->rauhoitusluokka_id = $this->haeValintalistanId('rauhoitusluokka', $item->nodeValue);
            }

            if($item->nodeName == 'lukumaara'){
                $kohde->lukumaara = $item->nodeValue;
            }

            if($item->nodeName == 'alkuperamaa'){
                $kohde->alkuperamaa = $item->nodeValue;
            }

            if($item->nodeName == 'alkuperamaanperustelu'){
                $kohde->alkuperamaanperustelu = $item->nodeValue;
            }

            if($item->nodeName == 'koordselite'){
                $kohde->koordselite = $item->nodeValue;
            }

            if($item->nodeName == 'etaisyystieto'){
                $kohde->etaisyystieto = $item->nodeValue;
            }

            if($item->nodeName == 'syvyysmax'){
                $kohde->syvyys_max = $item->nodeValue;
            }

            if($item->nodeName == 'syvyysmin'){
                $kohde->syvyys_min = $item->nodeValue;
            }

            if($item->nodeName == 'peruskarttanumero'){
                $kohde->peruskarttanumero = $item->nodeValue;
            }

            if($item->nodeName == 'peruskarttanimi'){
                $kohde->peruskarttanimi = $item->nodeValue;
            }

            if($item->nodeName == 'alkuperaisyys'){
                $kohde->alkuperaisyys_id = $this->haeValintalistanId('alkuperaisyys', $item->nodeValue);
            }

            if($item->nodeName == 'rajaustarkkuus'){
                $kohde->rajaustarkkuus_id = $this->haeValintalistanId('rajaustarkkuus', $item->nodeValue);
            }

            if($item->nodeName == 'maastomerkinta'){
                $kohde->maastomerkinta_id = $this->haeValintalistanId('maastomerkinta', $item->nodeValue);
            }

            if($item->nodeName == 'kunto'){
                $kohde->kunto_id = $this->haeValintalistanId('kunto', $item->nodeValue);
            }

            if($item->nodeName == 'hoitotarve'){
                $kohde->hoitotarve_id = $this->haeValintalistanId('hoitotarve', $item->nodeValue);
            }

            if($item->nodeName == 'huomautus'){
                $kohde->huomautus = $item->nodeValue;
            }

            if($item->nodeName == 'lahteet'){
                $kohde->lahteet = $item->nodeValue;
            }

            if($item->nodeName == 'avattu'){
                $kohde->avattu = $item->nodeValue;
            }

            if($item->nodeName == 'avaaja'){
                $kohde->avaaja = $item->nodeValue;
            }

            if($item->nodeName == 'muutettu'){
                $kohde->muutettu = $item->nodeValue;
            }

            if($item->nodeName == 'muuttaja'){
                $kohde->muuttaja = $item->nodeValue;
            }

            if($item->nodeName == 'yllapitoorganisaatiotunnus'){
                $kohde->yllapitoorganisaatiotunnus = $item->nodeValue;
            }

            if($item->nodeName == 'yllapitoorganisaatio'){
                $kohde->yllapitoorganisaatio = $item->nodeValue;
            }

            if($item->nodeName == 'julkinenurl'){
                $kohde->julkinenurl = $item->nodeValue;
            }

            if($item->nodeName == 'viranomaisurl'){
                $kohde->viranomaisurl = $item->nodeValue;
            }

            if($item->nodeName == 'kuvaus'){
                $kohde->kuvaus = $item->nodeValue;
            }

        }
        return $kohde;
    }

    /**
     * Valintalistan id arvojen haku nimen mukaan.
     * @param $table = 'valintalistan taulu'
     * @param $nimi_fi = 'valintalistan teksti'
     */
    private function haeValintalistanId($table, $nimi_fi){

        if(empty($nimi_fi)){
            Log::channel('kyppi')->error('KyppiService: valintalistan hakuparametri puuttuu');
            return '';
        }

        try {
            // Tutkimuslajille mäpätään omalla kentällä oikea id.
            if($table == 'ark_tutkimuslaji'){
                $valintalista = DB::select('select distinct id from ' . $table . ' where mjr_nimi_fi ilike :nimi', ['nimi' => $nimi_fi]);
            }else{
                // Normitapaus
                $valintalista = DB::select('select distinct id from ' . $table . ' where nimi_fi ilike :nimi', ['nimi' => $nimi_fi]);
            }

            if(empty($valintalista)){
                Log::channel('kyppi')->error('Valintalistan id puuttuu table, kentta: ' .$table .' ,' .$nimi_fi);
                return null;
            }else{

                // Palautetaan valintalistan id
                return $valintalista[0]->id;
            }

        } catch(Exception $e) {
            Log::channel('kyppi')->error('Valintalistan haun virhe ' . $e);
            throw $e;
        }
    }

    /**
     * Luo DOMXPath ja rekisteröi namespacet
     */
    private function luoXpath($dom){
        // Paikkatietojen hakua varten rekisteröidään xpath namespacet
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('kyppi', "http://paikkatieto.nba.fi/mvrpp/MjrekiRajapinta");
        $xpath->registerNamespace('gml', "http://www.opengis.net/gml");

        return $xpath;
    }

    /**
     * Muodostaa uuden KohdeSijainti modelin
     */
    private function muodostaKohdesijainti($kohde, $geom){

        $kohdeSijainti = new KohdeSijainti();
        $kohdeSijainti->kohde_id = $kohde->id;
        $kohdeSijainti->sijainti = $geom;
        $kohdeSijainti->luoja = Utils::getSystemUserId();

        return $kohdeSijainti;
    }

    /**
     * Muodostaa uuden AlakohdeSijainti modelin
     */
    private function muodostaAlakohdesijainti($alakohde, $geom){

        $kohdeSijainti = new AlakohdeSijainti();
        $kohdeSijainti->ark_kohde_alakohde_id = $alakohde->id;
        $kohdeSijainti->sijainti = $geom;
        $kohdeSijainti->luoja = Utils::getSystemUserId();

        return $kohdeSijainti;
    }

    /**
     * KohdeTyyppi modelien luonti ja tallennus
     */
    private function muodostaKohdeTyypit($kohde, $muinaisjaannos){

        // <tyyppitieto> alta löytyvät tyyppi ja alatyyppi eli tarkenne
        $tyyppitiedot = $muinaisjaannos->getElementsByTagName('tyyppitieto');

        $kohdetyypit = array();

        foreach ($tyyppitiedot as $tyyppitieto) {
            $tyyppi = $tyyppitieto->getElementsByTagName('tyyppi')->item(0);
            $alatyyppi = $tyyppitieto->getElementsByTagName('alatyyppi')->item(0);

            $kohdetyyppi = new KohdeTyyppi();
            $kohdetyyppi->ark_kohde_id = $kohde->id;

            $kohdetyyppi->tyyppi_id = $this->haeValintalistanId('ark_kohdetyyppi', $tyyppi->nodeValue);
            $kohdetyyppi->tyyppitarkenne_id =  $this->haeValintalistanId('ark_kohdetyyppitarkenne', $alatyyppi->nodeValue);
            $kohdetyyppi->luoja = Utils::getSystemUserId();

            $kohdetyypit[] = $kohdetyyppi;
        }

        // Pois vanhat
        DB::table('ark_kohde_tyyppi')->where('ark_kohde_id', $kohde->id)->delete();

        // Tallennetaan
        foreach ($kohdetyypit as $kohdetyyppi) {
            $kohde->tyypit()->save($kohdetyyppi);
        }
    }

    /**
     * KohdeAjoitus modelien luonti ja tallennus
     * Haku xpath:lla koska alakohteilla on myös ajoitustietoja.
     */
    private function muodostaKohdeAjoitukset($kohde, $dom, $muinaisjaannos){

        $xpath = $this->luoXpath($dom);

        // <ajoitustieto> alta löytyvät ajoitus, ajoitustarkenne ja ajoituskriteeri
        $query = './kyppi:ajoitustiedot';
        $ajoitustiedot = $xpath->query($query, $muinaisjaannos);

        $kohdeajoitukset = array();

        foreach ($ajoitustiedot as $ajoitustieto) {

            $ajoitus = $ajoitustieto->getElementsByTagName('ajoitus')->item(0);
            $ajoitustarkenne = $ajoitustieto->getElementsByTagName('ajoitustarkenne')->item(0);
            $ajoituskriteeri = $ajoitustieto->getElementsByTagName('ajoituskriteeri')->item(0);

            $kohdeajoitus = new KohdeAjoitus();
            $kohdeajoitus->ark_kohde_id = $kohde->id;

            if( empty($ajoitus) ){
                Log::channel('kyppi')->info('Ajoitus puuttuu, asetetaan oletusarvona: Ei maaritelty');
                $kohdeajoitus->ajoitus_id = '1';
            }else{
                $kohdeajoitus->ajoitus_id = $this->haeValintalistanId('ajoitus', $ajoitus->nodeValue);
            }

            if( !empty($ajoitustarkenne) ){
                $kohdeajoitus->ajoitustarkenne_id = $this->haeValintalistanId('ajoitustarkenne', $ajoitustarkenne->nodeValue);
            }

            if( !empty($ajoituskriteeri) ){
                $kohdeajoitus->ajoituskriteeri = $ajoituskriteeri->nodeValue;
            }

            $kohdeajoitus->luoja = Utils::getSystemUserId();

            $kohdeajoitukset[] = $kohdeajoitus;
        }
        // Pois vanhat
        DB::table('ark_kohde_ajoitus')->where('ark_kohde_id', $kohde->id)->delete();
        // Tallennetaan
        foreach ($kohdeajoitukset as $kohdeajoitus) {
            $kohde->ajoitukset()->save($kohdeajoitus);
        }
    }

    /**
     * KohdeKuntaKyla modelin luonti ja tallennus
     */
    private function muodostaKohdeKunta($kohde, $muinaisjaannos){

        // <kuntakoodi> ensimmäinen on aina muinaisjäännöksen kunta (erikseen vanhat kunnat)
        $kuntakoodi = $muinaisjaannos->getElementsByTagName('kuntakoodi')->item(0);

        // Jos kuntakoodi ei ole 3 merkkinen Laitetaan 0 eteen esim aura 019
        if(strlen($kuntakoodi->nodeValue) != 3){
            $kuntakoodi->nodeValue = str_pad($kuntakoodi->nodeValue, 3, "0", STR_PAD_LEFT);
        }
        // Haetaan Kunnan id kuntanumerolla
        $kunta = Kunta::where('kuntanumero', $kuntakoodi->nodeValue)->first();
        if( empty($kunta) ){
            // Jos kuntaa ei löydy tallennus epäonnistuu
            Log::channel('kyppi')->error('Virhe: Kuntaa ei löydy kuntanumerolla: '.$kuntakoodi->nodevalue .' mj: ' .$muinaisjaannos->muinaisjaannostunnus->nodevalue);
        }

        $kohdeKuntaKyla = new KohdeKuntaKyla();
        $kohdeKuntaKyla->ark_kohde_id = $kohde->id;
        $kohdeKuntaKyla->kunta_id = $kunta->id;
        $kohdeKuntaKyla->luoja = Utils::getSystemUserId();

        // Pois vanha
        DB::table('ark_kohde_kuntakyla')->where('ark_kohde_id', $kohde->id)->delete();
        // Tallennetaan
        $kohde->kunnatkylat()->save($kohdeKuntaKyla);
    }

    /**
     * Alakohde modelien luonti ja tallennus.
     * Alakohde sisältää paikkatietoja ja ajoitustietoja.
     * Haku xpath:lla.
     */
    private function muodostaAlakohteet($kohde, $dom, $muinaisjaannos, $isUpdate){

        $xpath = $this->luoXpath($dom);

        // <alakohteet> <alakohde>
        $query = './kyppi:alakohteet/kyppi:alakohde';
        $alakohteet = $xpath->query($query, contextNode: $muinaisjaannos);
        
        if ($isUpdate) {
            // Poistetaan kannasta alakohteet ja korvataan uusilla rekisteristä
            // Ensiksi poistetaan alakohteen ajoitukset ja sijainnit, jotta foreign key rajoitteet eivät estä poistoa
            $alakohdeIds = KohdeAlakohde::where('ark_kohde_id', $kohde->id)->pluck('id')->toArray();
            AlakohdeAjoitus::whereIn('ark_kohde_alakohde_id', $alakohdeIds)->delete();
            AlakohdeSijainti::whereIn('ark_kohde_alakohde_id', $alakohdeIds)->delete();
            // Sitten itse alakohteet
            KohdeAlakohde::where('ark_kohde_id', $kohde->id)->delete();
            Log::channel('kyppi')->debug('Poistettu alakohteet kannasta kohteelta ' . $kohde->muinaisjaannostunnus);
        }

        foreach ($alakohteet as $alakohdeDom) {

            // Jos ei nimeä löydy ei ole alakohteitakaan joten poistutaan
            if($alakohdeDom->getElementsByTagName('nimi')->length == 0){
                break;
            }

            // Alakohteen model
            $kohdeAlakohde = new KohdeAlakohde();

            $nimi = $alakohdeDom->getElementsByTagName('nimi')->item(0);
            $tyyppi = $alakohdeDom->getElementsByTagName('tyyppi')->item(0);
            $alatyyppi = $alakohdeDom->getElementsByTagName('alatyyppi')->item(0);
            $kuvaus = $alakohdeDom->getElementsByTagName('kuvaus')->item(0);

            $kohdeAlakohde->nimi = $nimi->nodeValue;
            $kohdeAlakohde->kuvaus = $kuvaus->nodeValue;

            // Alakohteen tyyppi
            if( !empty($tyyppi) ){
                $kohdeAlakohde->ark_kohdetyyppi_id = $this->haeValintalistanId('ark_kohdetyyppi', $tyyppi->nodeValue);
            }

            // Alakohteen alatyyppi
            if( !empty($alatyyppi) ){
                $kohdeAlakohde->ark_kohdetyyppitarkenne_id = $this->haeValintalistanId('ark_kohdetyyppitarkenne', $alatyyppi->nodeValue);
            }

            // Liitos kohteeseen
            $kohdeAlakohde->ark_kohde_id = $kohde->id;
            $kohdeAlakohde->luoja = Utils::getSystemUserId();

            // Tallennetaan alakohde ja saadaan alakohteen id
            $kohde->alakohteet()->save($kohdeAlakohde);

            // Alakohteen ajoitukset
            $ajoitukset = $this->muodostaAlaKohdeAjoitukset($kohdeAlakohde, $alakohdeDom);

            // Ajoitusten tallennus alakohteelle
            foreach ($ajoitukset as $alakohdeajoitus) {
                $kohdeAlakohde->ajoitukset()->save($alakohdeajoitus);
            }

            // Hae alakohteen pisteen sijainti
            $kohdePiste = $this->muodostaKohteenPisteSijainti($kohde, $kohdeAlakohde, $dom, $alakohdeDom);

            // Sijainnin tallennus alakohteelle
            if( !empty($kohdePiste)){
                $kohdeAlakohde->sijainnit()->save($kohdePiste);
            }
        }
    }

    /**
     * AlakohdeAjoitus modelien luonti
     */
    private function muodostaAlaKohdeAjoitukset($kohdeAlakohde, $alakohdeDom){

        // Xml datasta ajoitukset
        $ajoitustiedot = $alakohdeDom->getElementsByTagName('ajoitustieto');

        $alakohdeajoitukset = array();

        foreach ($ajoitustiedot as $ajoitustieto) {

            $ajoitus = $ajoitustieto->getElementsByTagName('ajoitus')->item(0);
            $ajoitustarkenne = $ajoitustieto->getElementsByTagName('ajoitustarkenne')->item(0);
            $ajoituskriteeri = $ajoitustieto->getElementsByTagName('ajoituskriteeri')->item(0);

            $alakohdeajoitus = new AlakohdeAjoitus();


            if( empty($ajoitus) ){
                Log::channel('kyppi')->info('Ajoitus puuttuu, asetetaan oletusarvona: Ei maaritelty');
                $alakohdeajoitus->ajoitus_id = '1';
            }else{
                $alakohdeajoitus->ajoitus_id = $this->haeValintalistanId('ajoitus', $ajoitus->nodeValue);
            }

            if( !empty($ajoitustarkenne) ){
                $alakohdeajoitus->ajoitustarkenne_id = $this->haeValintalistanId('ajoitustarkenne', $ajoitustarkenne->nodeValue);
            }

            if( !empty($ajoituskriteeri) ){
                $alakohdeajoitus->ajoituskriteeri = $ajoituskriteeri->nodeValue;
            }

            // Luodun alakohteen id FK
            $alakohdeajoitus->ark_kohde_alakohde_id = $kohdeAlakohde->id;
            $alakohdeajoitus->luoja = Utils::getSystemUserId();

            $alakohdeajoitukset[] = $alakohdeajoitus;
        }

        return $alakohdeajoitukset;
    }

    /**
     * KohdeMjrTutkimus modelien luonti ja tallennus
     */
    private function muodostaTutkimukset($kohde, $muinaisjaannos){

        // <tutkimukset> <tutkimus>
        $tutkimuksetDom = $muinaisjaannos->getElementsByTagName('tutkimus');

        $kohdetutkimukset = array();

        foreach ($tutkimuksetDom as $tutkimusDom) {

            // Lopetetaan jos ei tietoja
            if($tutkimusDom->getElementsByTagName('tutkija')->length == 0){
                break;
            }

            $tutkijaDom = $tutkimusDom->getElementsByTagName('tutkija')->item(0);
            $vuosiDom = $tutkimusDom->getElementsByTagName('vuosi')->item(0);
            $tutkimuslajiDom = $tutkimusDom->getElementsByTagName('tutkimuslaji')->item(0);
            $huomioDom = $tutkimusDom->getElementsByTagName('huomio')->item(0);

            $tutkimus = new KohdeMjrTutkimus();
            $tutkimus->ark_kohde_id = $kohde->id;
            $tutkimus->luoja = Utils::getSystemUserId();

            $tutkimus->ark_tutkimuslaji_id = $this->haeValintalistanId('ark_tutkimuslaji', $tutkimuslajiDom->nodeValue);
            $tutkimus->tutkija = $tutkijaDom->nodeValue;
            $tutkimus->vuosi = $vuosiDom->nodeValue;
            $tutkimus->huomio = $huomioDom->nodeValue;

            $kohdetutkimukset[] = $tutkimus;
        }

        // Vanhat pois
        DB::table('ark_kohde_mjrtutkimus')->where('ark_kohde_id', $kohde->id)->delete();

        // Tallennus
        foreach ($kohdetutkimukset as $kohdetutkimus) {
            $kohde->mjrTutkimukset()->save($kohdetutkimus);
        }
    }

    /**
     * KohdeVanhaKunta modelien luonti ja tallennus
     */
    private function muodostaVanhatKunnat($kohde, $muinaisjaannos){

        // <vanhatkunnat>
        $vanhatkunnatDom = $muinaisjaannos->getElementsByTagName('vanhatkunnat');

        $kohdevanhatkunnat = array();

        foreach ($vanhatkunnatDom as $vanhakuntaDom) {

            // Lopetetaan jos ei tietoja
            if($vanhakuntaDom->getElementsByTagName('kuntanimi')->length == 0){
                break;
            }

            $kuntanimiDom = $vanhakuntaDom->getElementsByTagName('kuntanimi')->item(0);

            $vanhakunta = new KohdeVanhaKunta();
            $vanhakunta->ark_kohde_id = $kohde->id;
            $vanhakunta->luoja = Utils::getSystemUserId();
            $vanhakunta->kuntanimi = $kuntanimiDom->nodeValue;

            $kohdevanhatkunnat[] = $vanhakunta;
        }

        // Vanhat pois
        DB::table('ark_kohde_vanhakunta')->where('ark_kohde_id', $kohde->id)->delete();

        // Tallennus
        foreach ($kohdevanhatkunnat as $kohdevanhakunta) {
            $kohde->vanhatKunnat()->save($kohdevanhakunta);
        }
    }

    /**
     * Pilkkoo stringistä arrayn annetun erotinmerkin ja määrän mukaan.
     */
    private function explodeByCount($delimiter, $string, $n) {
        $arr = explode($delimiter, $string);
        $arr2 = array_chunk($arr, $n);
        $out = array();
        for ($i = 0, $t = count($arr2); $i < $t; $i++) {
            $out[] = implode($delimiter, $arr2[$i]);
        }
        return $out;
    }

}