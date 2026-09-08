<?php

namespace App\Http\Controllers;

use App\Library\String\MipJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Exception;

class VapaaTekstiHakuController extends Controller {

    /*
     * Väliaikainen kiinteä testidata käyttöliittymän kehitystä varten.
     * Korvataan myöhemmin postgresql:n search_vector-pohjaisella hakumoottorilla.
     */
    private const TESTIDATA_PATH = "/resources/data/vapaatekstihaku_testidata.csv";

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {

        try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? (int) $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? (int) $request->rivit : 100;

            $entities = $this->readTestidata();

            if($request->input("hakusana")) {
                $hakusana = mb_strtolower($request->input("hakusana"));
                $entities = array_values(array_filter($entities, function($entity) use ($hakusana) {
                    return mb_strpos(mb_strtolower($entity['title']), $hakusana) !== false;
                }));
            }

            if($request->input("item_type")) {
                $item_type = $request->input("item_type");
                $entities = array_values(array_filter($entities, function($entity) use ($item_type) {
                    return $entity['item_type'] === $item_type;
                }));
            }

            $total_count = count($entities);
            $page = array_slice($entities, $rivi, $riveja);

            MipJson::setGeoJsonFeature();
            MipJson::setData($page, count($page), $total_count);
            MipJson::addMessage(Lang::get('vapaatekstihaku.found_count', ["count" => $total_count]));
        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('vapaatekstihaku.search_failed'));
        }

        return MipJson::getJson();
    }

    /**
     * Lukee väliaikaisen testidatan csv-tiedostosta.
     *
     * @return array
     */
    private function readTestidata() {
        $rows = [];
        $handle = fopen(base_path() . self::TESTIDATA_PATH, 'r');

        if($handle !== false) {
            $header = fgetcsv($handle, 0, ';');
            while(($row = fgetcsv($handle, 0, ';')) !== false) {
                $entity = array_combine($header, $row);
                $entity['rank'] = (float) $entity['rank'];
                $rows[] = $entity;
            }
            fclose($handle);
        }

        return $rows;
    }
}
