<?php

namespace App\Http\Controllers\Ark;

use App\Kayttaja;
use App\Ark\Kohde;
use App\Http\Controllers\Controller;
use App\Integrations\KyppiService;
use App\Library\String\MipJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

/**
 * Kyppi integraation controller
 */
class KyppiController extends Controller{



    /**
     * Hakee muinaisjäännöksen tunnuksella ja palautetaan json-muodossa.
     */
    public function muinaisjaannosHaku($id) {

        $kyppiService = new KyppiService();

        $response = $kyppiService->haeMuinaisjaannosSoap($id, true);

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
     * Muodostaa uuden muinaisjäännöksen tiedoista xml-version ja välittää sen KyppiServicelle.
     */
    public function muinaisjaannosLisays(Request $request) {

        $kyppiService = new KyppiService();

        // xml muodostus

        // palvelun kutsu
        $response = $kyppiService->lisaaMuinaisjaannos('xml_todo');

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

}
