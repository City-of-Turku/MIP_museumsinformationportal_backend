<?php

namespace App\Http\Controllers\Ark;

use App\Kayttaja;
use App\Utils;
use App\Ark\Ajoitus;
use App\Ark\Ajoitustarkenne;
use App\Ark\Kohde;
use App\Ark\Tyyppi;
use App\Ark\Tyyppitarkenne;
use App\Http\Controllers\Controller;
use App\Integrations\KyppiService;
use App\Library\String\MipJson;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Library\Gis\MipGis;

/**
 * Kyppi integraation controller
 */
class KyppiController extends Controller{



    /**
     * Hakee muinaisjäännöksen tunnuksella,palautetaan muutospäivä.
     */
    public function muinaisjaannosHaku($id) {

        $kyppiService = new KyppiService();

        $response = $kyppiService->haeMuinaisjaannosSoap($id, true);

        return $response;
    }

    /**
     * Testaamiseen.
     * Hakee muinaisjäännöksen tunnuksella ja palautetaan json-muodossa.
     */
    public function muinaisjaannosHakuTest($id) {

        $kyppiService = new KyppiService();

        $response = $kyppiService->haeMuinaisjaannosSoap($id, false);

        return $response;
    }

    /**
     * Hakee muinaisjäännökset annetun muutospäivän mukaan. Tämä on testausta varten.
     */
    public function haeMuinaisjaannokset($pvm, $maakunta = null, $kunta = null) {

        $kyppiService = new KyppiService();
        $maakunta = 'varsinais-suomi';

        $response = $kyppiService->haeMuinaisjaannoksetSoapTesti($pvm, $maakunta, $kunta);

        return $response;
    }

    /**
     * Tarkistaa onko muinaisjäännös jo MIP:ssä, jos ei ole niin
     * hakee Kypistä muinaisjäännöksen "haeMuinaisjaannos"-palvelulla ja luo kohteen MIP:iin.
     */
    public function tuoKohde($id)
    {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_kohde.muokkaus')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $kyppiService = new KyppiService();

        // Kyppi kutsu, vastaus xml muodossa
        $xml = $kyppiService->haeMuinaisjaannosSoap($id, false);

        if(empty($xml)){
            MipJson::setGeoJsonFeature(null, null);
            MipJson::addMessage('Tietoja ei loytynyt muinaisjaannosrekisterista tunnuksella: ' .$id);
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;

        $dom->loadXML($xml);
        $muinaisjaannos = $dom->getElementsByTagName( 'muinaisjaannos' )->item(0);

        if(empty($muinaisjaannos)){
            MipJson::setGeoJsonFeature(null, null);
            MipJson::addMessage('Tietoja ei loytynyt muinaisjaannosrekisterista tunnuksella: ' .$id);
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        // Haku MIP:stä
        $kohde = Kohde::where('muinaisjaannostunnus', $id)->first();
        //var_dump($kohde);

        // Tuodaan uusi kohde.
        if (empty($kohde)) {
            $response = $kyppiService->lisaaTaiPaivitaKohde($dom, null, true);

            return $response;
        } else {
            // Päivitetään kohde
            $response = $kyppiService->lisaaTaiPaivitaKohde($dom, $kohde, true);

            return $response;
        }
    }

    /**
     * Muodostaa MIP kohteen tiedoista uuden muinaisjäännöksen xml-version ja välittää sen KyppiServicelle.
     * Jos lisäys kyppiin onnistui palautetaan uusi muinaisjäännöstunnus, joka tallennetaan kohteelle.
     */
    public function muinaisjaannosLisays($id) {

        /*
         * Vain pääkäyttäjät voivat lähettää muinaisjäännöksiä
         */
        if(Auth::user()->ark_rooli != 'pääkäyttäjä') {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        // Haku MIP:stä
        $kohde = $this->haeKohde($id);

        // Tarkistetaan että kohde löytyy MIPstä.
        if (empty($kohde)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            MipJson::addMessage(Lang::get('kohde.search_not_found'));
        } else {
            // Validoi kohteen pakolliset tiedot
            $errorResponse =  $this->validoiKohde($kohde);

            if(!empty($errorResponse)){
                return $errorResponse;
            }

            // Xml muodostus
            $kohdeXml = $this->muodostaXml($kohde);

            // Lähetetään xml kyppiin
            $kyppiService = new KyppiService();

            // Uusi muinaisjäännöstunnus palautetaan ok tilanteessa
            $muinaisjaannostunnus = $kyppiService->lisaaMuinaisjaannos($kohdeXml);

            if(!empty($muinaisjaannostunnus)){
                if($muinaisjaannostunnus == '-1'){
                   Log::error('Lisää muinaisjäännös-palvelu palautti virheen: -1');
                   MipJson::setGeoJsonFeature();
                   MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
                   MipJson::addMessage('Lisää muinaisjäännös-palvelu palautti virheen: -1');

                } else {
                    // Päivitä kohteelle muinaisjäännöstunnus ja julkinen url
                    try{

                        // Haetaan kypista luotu muinaisjäännös uudelleen, jotta saadaan myös julkinen url.
                        // Nykyisellään Kyppi ei palauta kuin suppeat tiedot LisaaMuinaisjaannos palvelun käyttäjille.
                        $xml = $kyppiService->haeMuinaisjaannosSoap($muinaisjaannostunnus, false);

                        if(empty($xml)){
                            MipJson::setGeoJsonFeature(null, null);
                            MipJson::addMessage('Tietoja ei loytynyt muinaisjaannosrekisterista tunnuksella: ' .$muinaisjaannostunnus);
                            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                            return MipJson::getJson();
                        }

                        $dom = new \DOMDocument('1.0', 'utf-8');
                        $dom->preserveWhiteSpace = false;

                        $dom->loadXML($xml);
                        $muinaisjaannos = $dom->getElementsByTagName( 'muinaisjaannos' )->item(0);

                        if(empty($muinaisjaannos)){
                            MipJson::setGeoJsonFeature(null, null);
                            MipJson::addMessage('Tietoja ei loytynyt muinaisjaannosrekisterista tunnuksella: ' .$muinaisjaannostunnus);
                            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                            return MipJson::getJson();
                        }

                        // Hae arvot domista
                        foreach ($muinaisjaannos->childNodes as $item) {

                            if($item->nodeName == 'muinaisjaannostunnus'){
                                $kohde->muinaisjaannostunnus = $item->nodeValue;
                            }

                            if($item->nodeName == 'julkinenurl'){
                                $kohde->julkinenurl = $item->nodeValue;
                            }
                        }

                        // Tallennus Mippiin
                        DB::beginTransaction();
                        Utils::setDBUser();

                        $author_field = Kohde::UPDATED_BY;
                        $kohde->$author_field = Auth::user()->id;
                        $kohde->touch(); // tällä saadaan päätason muutokset muutoshistoriaan.
                        $kohde->update();

                        // Onnistunut lisääminen
                        MipJson::setGeoJsonFeature(null, array("id" => $kohde->id));
                        MipJson::addMessage(Lang::get('kohde.save_success'));
                        MipJson::setResponseStatus(Response::HTTP_OK);
                    }

                    catch(Exception $e) {
                        DB::rollback();
                        throw $e;
                    }
                    DB::commit();
                }
            }
        }

        return MipJson::getJson();
    }

    /**
     * Rajapintaan tulee lähettää muinaisjäännösrekisterin kannalta pakolliset tiedot:
     * kunta, kohdenimi, laji, tietueen avaaja, tietueen avausajankohta ja joko kohdetiedon julkinen URL
     * tai viranomaisille tarkoitettu URL. Jompi kumpi URL on pakollinen.
     * URL-tiedot on suunniteltu jaettaviksi Museoviraston selainsovelluksissa.
     */
    private function validoiKohde($kohde){

        $errors = array();

        if(empty($kohde->nimi)){
            array_push($errors, 'Kohteen nimi puuttuu');
            MipJson::addMessage(Lang::get('Kohteen nimi puuttuu'));
        }
        if(empty($kohde->kunnatkylat[0])){
            array_push($errors, 'Kohteen kunta puuttuu');
            MipJson::addMessage(Lang::get('Kohteen kunta puuttuu'));
        }
        if(empty($kohde->laji->nimi_fi)){
            array_push($errors, 'Kohteen laji puuttuu');
            MipJson::addMessage(Lang::get('Kohteen laji puuttuu'));
        }
        if(!empty($kohde->muinaisjaannostunnus)){
            array_push($errors, 'Kohde on jo lisätty muinaisjäännösrekisteriin');
            MipJson::addMessage(Lang::get('Kohde on jo lisätty muinaisjäännösrekisteriin'));
        }

        if(!empty($errors)){
            MipJson::setGeoJsonFeature();
            MipJson::setGeoJsonFeature(null, $errors);
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            return MipJson::getJson();
        }

    }

    /**
     * Hae MIP kohde tarvittavilla tiedoilla
     */
    private function haeKohde($id){
        $kohde = Kohde::getSingle($id)
        ->with( array(
            'ajoitukset.ajoitus',
            'ajoitukset.tarkenne',
            'alkuperaisyys',
            'hoitotarve',
            'laji',
            'luoja',
            'kunto',
            'kunnatkylat.kunta',
            'kunnatkylat.kyla',
            'maastomerkinta',
            'muokkaaja',
            'rajaustarkkuus',
            'rauhoitusluokka',
            'sijainnit',
            'suojelutiedot.suojelutyyppi',
            'tyypit.tyyppi',
            'tyypit.tarkenne',
            'tuhoutumissyy',
            'kiinteistotrakennukset',
            'kiinteistotrakennukset.osoitteet',
            'tuhoutumissyy',
            'vanhatKunnat',
            'mjrtutkimukset',
            'mjrtutkimukset.mjrtutkimuslaji',
            'tutkimukset'
        ))->first();

        return $kohde;
    }

    /**
     * Muodostetaan Muinaisjäännös xml kohteen tiedoilla.
     */
    private function muodostaXml($kohde){
        //Log::debug('KOHDE: '.$kohde);

        // xml muodostus
        $xw = xmlwriter_open_memory();
        xmlwriter_set_indent($xw, 1);

        // alku tag muinaisjaannos
        xmlwriter_start_element($xw, 'muinaisjaannos');

        xmlwriter_start_element($xw, 'nimi');
        xmlwriter_text($xw, $kohde->nimi);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'muutnimet');
        xmlwriter_text($xw, $kohde->muutnimet);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'maakuntanimi');
        xmlwriter_text($xw, $kohde->maakuntanimi);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'kuntanimi');
        xmlwriter_text($xw, $kohde->kunnatkylat[0]->kunta->nimi);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'kuntakoodi');
        xmlwriter_text($xw, $kohde->kunnatkylat[0]->kunta->kuntanumero);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'laji');
        xmlwriter_text($xw, mb_strtolower($kohde->laji->nimi_fi));
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'vedenalainen');
        xmlwriter_text($xw, $kohde->laji->vedenalainen);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'suojelukriteeri');
        xmlwriter_text($xw, $kohde->suojelukriteeri);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'lukumaara');
        xmlwriter_text($xw, $kohde->lukumaara);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'koordselite');
        xmlwriter_text($xw, $kohde->koordselite);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'etaisyystieto');
        xmlwriter_text($xw, $kohde->etaisyystieto);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'zala');
        xmlwriter_text($xw, $kohde->korkeus_min);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'zyla');
        xmlwriter_text($xw, $kohde->korkeus_max);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'syvyysmax');
        xmlwriter_text($xw, $kohde->syvyys_max);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'syvyysmin');
        xmlwriter_text($xw, $kohde->syvyys_min);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'peruskarttanumero');
        xmlwriter_text($xw, $kohde->peruskarttanumero);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'peruskarttanimi');
        xmlwriter_text($xw, $kohde->peruskarttanimi);
        xmlwriter_end_element($xw);

        if(!empty($kohde->alkuperaisyys)){
            xmlwriter_start_element($xw, 'alkuperaisyys');
            xmlwriter_text($xw, mb_strtolower($kohde->alkuperaisyys->nimi_fi));
            xmlwriter_end_element($xw);
        }

        if(!empty($kohde->rajaustarkkuus)){
            xmlwriter_start_element($xw, 'rajaustarkkuus');
            xmlwriter_text($xw, mb_strtolower($kohde->rajaustarkkuus->nimi_fi));
            xmlwriter_end_element($xw);
        }

        if(!empty($kohde->maastomerkinta)){
            xmlwriter_start_element($xw, 'maastomerkinta');
            xmlwriter_text($xw, mb_strtolower($kohde->maastomerkinta->nimi_fi));
            xmlwriter_end_element($xw);
        }

        if(!empty($kohde->kunto)){
            xmlwriter_start_element($xw, 'kunto');
            xmlwriter_text($xw, mb_strtolower($kohde->kunto->nimi_fi));
            xmlwriter_end_element($xw);
        }

        if(!empty($kohde->hoitotarve)){
            xmlwriter_start_element($xw, 'hoitotarve');
            xmlwriter_text($xw, mb_strtolower($kohde->hoitotarve->nimi_fi));
            xmlwriter_end_element($xw);
        }

        xmlwriter_start_element($xw, 'huomautus');
        xmlwriter_text($xw, $kohde->huomautus);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'lahteet');
        xmlwriter_text($xw, $kohde->lahteet);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'avattu');
        xmlwriter_text($xw, $kohde->luotu->toDateString());
        xmlwriter_end_element($xw);

        // Max 25mrk. Lisätään muodossa x.sukunimi
        $avaaja =  Kayttaja::where('id', $kohde->luoja)->first();
        $tunnus = substr($avaaja->etunimi, 0, 1) . '.' . substr($avaaja->sukunimi, 0, 22);

        xmlwriter_start_element($xw, 'avaaja');
        xmlwriter_text($xw, $tunnus);
        xmlwriter_end_element($xw);

/*         xmlwriter_start_element($xw, 'muutettu');
        xmlwriter_text($xw, $kohde->muutettu);
        xmlwriter_end_element($xw); */

        // MIP:issä luodulla kohteella aina tunnus 17 Turun Museokeskus
        xmlwriter_start_element($xw, 'yllapitoorganisaatiotunnus');
        xmlwriter_text($xw, $kohde->yllapitoorganisaatiotunnus);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'yllapitoorganisaatio');
        xmlwriter_text($xw, $kohde->yllapitoorganisaatio);
        xmlwriter_end_element($xw);

        // Julkinen url on pakollinen ja oltava uniikki. MIP laittaa kohteen id tähän toistaiseksi.
        xmlwriter_start_element($xw, 'julkinenurl');
        xmlwriter_text($xw, $kohde->id);
        xmlwriter_end_element($xw);

        xmlwriter_start_element($xw, 'kuvaus');
        xmlwriter_text($xw, $kohde->kuvaus);
        xmlwriter_end_element($xw);

        // Ajoitustiedot
        xmlwriter_start_element($xw, 'ajoitustiedot');
        $ajoitukset = $kohde->ajoitukset;
        foreach($ajoitukset as $ajoitus) {

            $kohdeAjoitus = Ajoitus::where('id', $ajoitus->ajoitus_id)->first();
            $ajoitusTarkenne = Ajoitustarkenne::where('id', $ajoitus->ajoitustarkenne_id)->first();

            xmlwriter_start_element($xw, 'ajoitustieto');

            xmlwriter_start_element($xw, 'ajoitus');
            xmlwriter_text($xw, mb_strtolower($kohdeAjoitus->nimi_fi));
            xmlwriter_end_element($xw);

            if(!empty($ajoitusTarkenne)){
                xmlwriter_start_element($xw, 'ajoitustarkenne');
                xmlwriter_text($xw, mb_strtolower($ajoitusTarkenne->nimi_fi));
                xmlwriter_end_element($xw);
            }

            xmlwriter_start_element($xw, 'ajoituskriteeri');
            xmlwriter_text($xw, $ajoitus->ajoituskriteeri);
            xmlwriter_end_element($xw);

            xmlwriter_end_element($xw); //ajoitustieto
        }
        xmlwriter_end_element($xw); // Ajoitustiedot loppu

        // Paikkatiedot
        xmlwriter_start_element($xw, 'paikkatiedot');
        // Kohteen sijainneista löytyy pisteet ja alueet
        $sijainnit = $kohde->sijainnit;

        foreach($sijainnit as $sijainti) {

            // Piste
            if($sijainti['geometry']['type'] == 'Point') {

                $lon = $sijainti['geometry']['coordinates'][0];
                $lat = $sijainti['geometry']['coordinates'][1];

                // Koordinaatit pitää muuntaa 3067 projektioon näppärästi näin.
                $points = 'POINT('.$lon .' '. $lat .')';

                $convertedCoords = MipGis::transformSSRID(4326, 3067, $points);
                $asGeoJson = MipGis::asGeoJson($convertedCoords);
                $point = json_decode($asGeoJson);

                xmlwriter_start_element($xw, 'pistetieto');

                xmlwriter_start_element($xw, 'gml:pos');
                xmlwriter_text($xw, $point->coordinates[0] . " ".  $point->coordinates[1]);
                xmlwriter_end_element($xw);

                xmlwriter_end_element($xw); // pistetieto
            }
            // Alue
            if($sijainti['geometry']['type'] == 'Polygon') {

                // Koordinaatit muutetaan 3067 projektioon ennen xml asetusta.
                $koordinaatit = $this->muodostaPolygon3067($sijainti->geometry);

                // Alueen tagit
                xmlwriter_start_element($xw, 'aluetieto');
                xmlwriter_start_element($xw, 'gml:Surface');
                xmlwriter_start_element($xw, 'gml:patches');
                xmlwriter_start_element($xw, 'gml:PolygonPatch');
                xmlwriter_start_element($xw, 'gml:exterior');
                xmlwriter_start_element($xw, 'gml:LinearRing');

                xmlwriter_start_element($xw, 'gml:posList');
                xmlwriter_text($xw, $koordinaatit);
                xmlwriter_end_element($xw);

                xmlwriter_end_element($xw); // LinearRing
                xmlwriter_end_element($xw); // exterior
                xmlwriter_end_element($xw); // PolygonPatch
                xmlwriter_end_element($xw); // patches
                xmlwriter_end_element($xw); // Surface
                xmlwriter_end_element($xw); // aluetieto

            }
        }

        // Paikkatiedot loppu
        xmlwriter_end_element($xw);

        // Tyyppitiedot
        xmlwriter_start_element($xw, 'tyyppitiedot');

        $tyypit = $kohde->tyypit;
        foreach($tyypit as $type) {

            $tyyppi = Tyyppi::where('id', $type->tyyppi_id)->first();
            $alatyyppi = Tyyppitarkenne::where('id', $type->tyyppitarkenne_id)->first();

            xmlwriter_start_element($xw, 'tyyppitieto');

            xmlwriter_start_element($xw, 'tyyppi');
            xmlwriter_text($xw, mb_strtolower($tyyppi->nimi_fi));
            xmlwriter_end_element($xw);

            xmlwriter_start_element($xw, 'alatyyppi');
            xmlwriter_text($xw, mb_strtolower($alatyyppi->nimi_fi));
            xmlwriter_end_element($xw);

            xmlwriter_end_element($xw); // tyyppitieto
        }
        xmlwriter_end_element($xw); // tyyppitiedot

        // Loppu tag muinaisjaannos
        xmlwriter_end_element($xw);


        return xmlwriter_output_memory($xw);
    }

    /**
     * Konvertoi polygon koordinaatit 3067 projektioon
     */
    private function muodostaPolygon3067($geometry){

        $pointcount = count($geometry['coordinates'][0]);
        $posList = "";

        for($i = 0; $i < $pointcount; $i++) {
            $lat = $geometry['coordinates'][0][$i][0];
            $lon = $geometry['coordinates'][0][$i][1];
            $posList = "$posList $lat $lon,";
            if ($i < $pointcount-1) {
                $posList = "$posList ";
            }
        }

        // Objekti Gis muuntoa varten
        $polygon = 'POLYGON((';
        $polygon .= $posList;
        $polygon = rtrim($polygon, ','); // viimeinen pilkku veks
        $polygon .=  '))';

        $converted = MipGis::transformSSRID(4326, 3067, $polygon);
        $geoJson = MipGis::asGeoJson($converted);
        $polygon3067 = json_decode($geoJson);

        $koordinaatit = $this->muodostaKoordinaattiLista($polygon3067);

        return $koordinaatit;
    }

    /**
     * Yhdistetään alueen koordinaatit posList muotoon eli välilyönnillä eroteltuina.
     */
    private function muodostaKoordinaattiLista($polygon){

        $pointcount = count($polygon->coordinates[0]);
        $posList = "";

        for($i = 0; $i < $pointcount; $i++) {
            $lat = $polygon->coordinates[0][$i][0];
            $lon = $polygon->coordinates[0][$i][1];
            $posList = "$posList $lat $lon";
            if ($i < $pointcount-1) {
                $posList = "$posList ";
            }
        }
        return $posList;
    }
}
