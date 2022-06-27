<?php

namespace App\Http\Controllers;

use App\Kori;
use App\Korityyppi;
use App\KoriKayttajat;
use App\Utils;
use App\Library\String\MipJson;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * Koritoiminnallisuuden palvelut
 */
class KoriController extends Controller
{
    /**
     * Korien haku
     */
    public function index(Request $request) {

        if(!$request->mip_alue) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            return MipJson::getJson();
        }

        try {
            $korityyppi = (isset($request->korityyppi) && is_numeric($request->korityyppi)) ? $request->korityyppi : null;
            $korijako = (isset($request->korijako) && is_numeric($request->korijako)) ? $request->korijako : null;
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 25;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";


            // TODO requestiin vipu hae kaikki tai sitten vain aina user tai valittu käyttäjä...
            // mip_alue päättää haetaanko RAK vai ARK koreja
            $korit = Kori::haeKayttajanKorit(Auth::user()->id, $korityyppi, $korijako, $request->nimi)->with( array(
                'korityyppi',
                'luoja',
                'muokkaaja'
            ))->where('kori.mip_alue', '=', $request->mip_alue);

            // Haetaan kaikki korit, jos tyyppiä ei ole rajattu
            if($korityyppi != null){
                $korit->withKorityyppi($korityyppi);
            }
            // Rivien määrän laskenta
            $total_rows = Utils::getCount($korit);

            // Rivimäärien rajoitus parametrien mukaan
            //$korit->withLimit($rivi, $riveja);
            // suorita query
            $korit = $korit->get();

            MipJson::initGeoJsonFeatureCollection(count($korit), $total_rows);

            /*
             * Koritoiminnallisuus. Palautetaan id:t listana.
             */
            $kori = new \stdClass;
            $kori->kori_id_lista = array();
            foreach ($korit as $kori) {
                if ($kori->kayttaja_id_lista != null){
                    $kori->kayttajat = KoriKayttajat::getKayttajat($kori->kayttaja_id_lista);
                }
                // korin lisäys feature listaan
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $kori);
            }

            MipJson::addMessage(Lang::get('kori.search_success'));

        } catch (Exception $e) {
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('kori.search_failed'));
        }
        return MipJson::getJson();
    }

    /**
     * Korin haku
     */
    public function show($id) {

        if(!is_numeric($id)) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        }
        else {
            try {

                // Hae kori
                $kori = Kori::getSingle($id)->with( array(
                    'korityyppi',
                    'luoja',
                    'muokkaaja'))->first();
                if(!$kori) {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('kori.search_not_found'));
                    return MipJson::getJson();
                }
                if ($kori->mip_alue == 'ARK'){ //RAK-puolelle ei ole toteutettu korin jakoa
                    $kori_kayttajat = KoriKayttajat::getKoriKayttajat($id);
                    $kori->kayttajat = $kori_kayttajat["kayttajat"];
                    $kori->museon_kori = $kori_kayttajat["museon_kori"];
                }

                // Muodostetaan propparit
                $properties = clone($kori);

                MipJson::setGeoJsonFeature(null, $properties);
                MipJson::addMessage(Lang::get('kori.search_success'));
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('kori.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }



    /**
     * Tallenna uusi kori
     *
     */
    public function store(Request $request) {

        /*
         * Validointi
         */
        $validator = Validator::make($request->kori['properties'], [
            'korityyppi' => 'required',
            'nimi' => 'required|string|unique:kori',
            'kuvaus' => 'required|string'
        ]);

        if ($validator->fails()) {

            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            foreach($validator->errors()->all() as $error) {
                MipJson::addMessage($error);
                Log::debug($error);
            }
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            try {

                $kori = new Kori($request->kori['properties']);

                // Valintalistojen id:t
                $kori->korityyppi_id = $request->input('kori.properties.korityyppi.id');

                $kori->luoja = Auth::user()->id;
                $kori->save();
                if ($request->jaetut_kayttajat || (array_key_exists("museon_kori", $request->kori['properties']) && $request->kori['properties']["museon_kori"] == true)){
                    KoriKayttajat::lisaaKorinKayttajat($kori->id, $request->jaetut_kayttajat, $request->kori['properties']["museon_kori"]);
                }
            } catch(Exception $e) {
                Log::debug("Error " .json_encode($e));
                DB::rollback();
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
                MipJson::addMessage(Lang::get('kori.save_failed'));
            }

            // Onnistunut case
            DB::commit();

            // Haetaan tallennettu kori
            $kori = Kori::getSingle($kori->id)->with( array(
                'korityyppi',
                'luoja',
                'muokkaaja'))->first();


            if ($kori->mip_alue == 'ARK'){ //RAK-puolelle ei ole toteutettu korin jakoa
                $kori_kayttajat = KoriKayttajat::getKoriKayttajat($kori->id);
                $kori->kayttajat = $kori_kayttajat["kayttajat"];
                $kori->museon_kori = $kori_kayttajat["museon_kori"];
                /*
                if ($kori_kayttajat != null && count($kori_kayttajat) > 0){
                    $kori->kayttajat = $kori_kayttajat;
                    $kori->museon_kori = $kori_kayttajat->first()->museon_kori;
                }*/
            }

            MipJson::addMessage(Lang::get('kori.save_success'));
            MipJson::setGeoJsonFeature(null, $kori);
            MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('kori.save_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Korin päivitys
     */
    public function update(Request $request, $id) {
        /*
         * Validointi
         */
         $validator = Validator::make($request->kori['properties'], [
            'nimi'			=> 'required|string|unique:kori,nimi,'.$id,
            'kuvaus'	    => 'required|string'
        ]);

        if ($validator->fails()) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            foreach($validator->errors()->all() as $error)
                MipJson::addMessage($error);
                MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        }
        else {
            try {
                $kori = Kori::find($id);
                if(!$kori){
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('kori.search_not_found'));
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                }
                else {
                    DB::beginTransaction();
                    Utils::setDBUser();

                    try {
                        $kori->fill($request->kori['properties']);

                        // Valintalistojen id:t
                        $kori->korityyppi_id = $request->input('kori.properties.korityyppi.id');

                        $author_field = Kori::UPDATED_BY;
                        $kori->$author_field = Auth::user()->id;

                        $kori->update();

                        if ($request->jaetut_kayttajat || array_key_exists("museon_kori", $request->kori['properties'])){
                            KoriKayttajat::lisaaKorinKayttajat($id, $request->jaetut_kayttajat, $request->kori['properties']["museon_kori"]);
                        }

                    } catch(Exception $e) {
                        DB::rollback();
                        throw $e;
                    }

                    // Päivitys onnistui
                    DB::commit();

                    // Hae tallennettu kori
                    $kori = Kori::getSingle($id)->with( array(
                        'korityyppi',
                        'luoja',
                        'muokkaaja'))->first();

                    if ($kori->mip_alue == 'ARK'){ //RAK-puolelle ei ole toteutettu korin jakoa
                        $kori_kayttajat = KoriKayttajat::getKoriKayttajat($id);
                        $kori->kayttajat = $kori_kayttajat["kayttajat"];
                        $kori->museon_kori = $kori_kayttajat["museon_kori"];
                    }
                    MipJson::addMessage(Lang::get('kori.save_success'));
                    MipJson::setGeoJsonFeature(null, $kori);
                    MipJson::setResponseStatus(Response::HTTP_OK);
                }
            }
            catch(QueryException $qe) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage($qe->getMessage());
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            catch (Exception $e) {
                MipJson::setGeoJsonFeature();
                MipJson::setMessages(array(Lang::get('kori.update_failed'), $e->getMessage()));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Palauttaa korityypin annetulla taulun nimellä.
     */
    public function haeKorityyppi($taulu){

        if(!$taulu) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        }
        else {
            try {
                $korityyppi = Korityyppi::select('*')->where('taulu', '=', $taulu)->first();

                $properties = clone($korityyppi);
                MipJson::setGeoJsonFeature(null, $properties);
                MipJson::setResponseStatus(Response::HTTP_OK);
                MipJson::addMessage(Lang::get('kori.type_search_success'));

            } catch(Exception $e) {

                MipJson::setGeoJsonFeature();
                MipJson::setMessages(array(Lang::get('kori.type_search_failed'),$e->getMessage()));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Poista kori.
     */
    public function destroy($id) {

        try {

            DB::beginTransaction();
			Utils::setDBUser();

            $kori = Kori::find($id);


            $author_field = Kori::DELETED_BY;
			$when_field = Kori::DELETED_AT;
			$kori->$author_field = Auth::user ()->id;
			$kori->$when_field = \Carbon\Carbon::now();

			$kori->save();

            DB::commit();

            MipJson::addMessage(Lang::get('kori.delete_success'));

        } catch(Exception $e) {

            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('kori.delete_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    public function getKoriKayttajat($id) {
        try {
            $korikayttajat = KoriKayttajat::getKoriKayttajat($id);

            $properties = clone($korikayttajat);
            MipJson::setGeoJsonFeature(null, $properties);
            MipJson::setResponseStatus(Response::HTTP_OK);
            MipJson::addMessage(Lang::get('kori.type_search_success'));

        } catch(Exception $e) {

            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('kori.type_search_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
    }

}
