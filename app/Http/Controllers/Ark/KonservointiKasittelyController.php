<?php

namespace App\Http\Controllers\Ark;

use App\Utils;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Ark\KonservointiKasittely;
use App\Ark\KonservointiKasittelytapahtumat;
use App\Ark\KonservointiLoyto;
use App\Ark\KonservointiNayte;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class KonservointiKasittelyController extends Controller
{
    /**
     * Konservoinnin käsittelyiden haku
     * @param Request $request
     * @return array
     */
    public function index(Request $request) {

        /*
         * Käyttöoikeustarkistus
         */
        if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 10;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "kasittelytunnus";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $kasittelyt = KonservointiKasittely::getAll($jarjestys_kentta, $jarjestys_suunta)->with(array('tapahtumat', 'luoja', 'muokkaaja'));

            // Haku käsittelytunnuksella
            if($request->kasittelytunnus){
                $kasittelyt->withKasittelytunnus($request->kasittelytunnus);
            }

            /*
             * Päättyneiden haku
             * 1 = avoimet
             * 2 = päättyneet
             * 3 = kaikki
             */
            if($request->valittu_paattyminen){
                if($request->valittu_paattyminen == 1){
                    $kasittelyt->where('paattyy', '=', null);
                }else if($request->valittu_paattyminen == 2){
                    $kasittelyt->where('paattyy', '!=', null);
                }
            }else{
                // oletus: avoimet
                $kasittelyt->where('paattyy', '=', null);
            }

            // Uniikkitarkistus
            if($request->uniikki && $request->kasittelytunnus){
                $kasittelyt->where('kasittelytunnus', 'ILIKE', $request->kasittelytunnus);
            }

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($kasittelyt);

            // Rivimäärien rajoitus parametrien mukaan
            $kasittelyt->withLimit($rivi, $riveja);

            // suorita query
            $kasittelyt = $kasittelyt->get();

            MipJson::initGeoJsonFeatureCollection(count($kasittelyt), $total_rows);

            foreach ($kasittelyt as $kasittely) {
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $kasittely);
            }

            MipJson::addMessage(Lang::get('konservointi.treatment_search_success'));

        } catch (Exception $e) {
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointi.treatment_search_failed'));
        }

        return MipJson::getJson();
    }

    /**
     * Näytteen haku
     */
    public function show($id) {

        /*
         * Käyttöoikeustarkistus
         */
        if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        if(!is_numeric($id)) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        }
        else {
            try {

                // Hae käsittely
                $kasittely = KonservointiKasittely::getSingle($id)->with( array(
                    'tapahtumat' => function($query) {
                     $query->orderBy('paivamaara', 'DESC');
                    }
                ))->first();

                if(!$kasittely) {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('konservointi.treatment_search_not_found'));
                    return MipJson::getJson();
                }

                // Käsittelyyn kuuluvat löydöt ja näytteet
                $loydotNaytteet = KonservointiKasittely::haeLoydotNaytteet($id);

                if($loydotNaytteet){
                    $kasittely->loydot_naytteet = $loydotNaytteet;
                }else{
                    $kasittely->loydot_naytteet = array();
                }

                // Muodostetaan propparit
                $properties = clone($kasittely);

                MipJson::setGeoJsonFeature(null, $properties);

                MipJson::addMessage(Lang::get('konservointi.search_success'));
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('konservointi.treatment_search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Uuden käsittelyn lisäys
     * @param Request $request
     * @throws Exception
     * @return array
     */
    public function store(Request $request) {

        /*
         * Käyttöoikeustarkistus
         */
        if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all()['properties'], [
            'kasittelytunnus'		=> 'required|string'
        ]);

        if ($validator->fails()) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            foreach($validator->errors()->all() as $error) {
                MipJson::addMessage($error);
            }
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            return MipJson::getJson();
        }

        try {
            try {
                DB::beginTransaction();
                Utils::setDBUser();

                $kasittely = new KonservointiKasittely($request->all()['properties']);

                $author_field = KonservointiKasittely::CREATED_BY;
                $kasittely->$author_field = Auth::user()->id;

                $kasittely->save();

                DB::commit();
            } catch(Exception $e) {

                DB::rollback();
                throw $e;
            }

            // Hae käsittely
            $kasittely = KonservointiKasittely::getSingle($kasittely->id)->with( array(
                'tapahtumat' => function($query) {
                $query->orderBy('paivamaara', 'DESC');
                }
                ))->first();

            MipJson::addMessage(Lang::get('konservointi.treatment_save_success'));
            MipJson::setGeoJsonFeature(null, $kasittely);

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointi.treatment_save_failed'));
        }
        return MipJson::getJson();
    }

    /**
     * Päivitä käsittely
     */
    public function update(Request $request, $id) {

        /*
         * Käyttöoikeustarkistus
         */
        if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all()['properties'], [
            'kasittelytunnus'		=> 'required|string'
        ]);

        if ($validator->fails()) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            foreach($validator->errors()->all() as $error) {
                MipJson::addMessage($error);
            }
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            return MipJson::getJson();
        }

        try {
            $kasittely = KonservointiKasittely::find($id);

            if(!$kasittely){
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('konservointi.treatment_search_not_found'));
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                return MipJson::getJson();
            }

            try {
                DB::beginTransaction();
                Utils::setDBUser();

                $kasittely->fill($request->all()['properties']);

                $author_field = KonservointiKasittely::UPDATED_BY;
                $kasittely->$author_field = Auth::user()->id;

                $kasittely->save();

                // Käsittelyn tapahtumien päivitys
                KonservointiKasittelytapahtumat::paivita_kasittelytapahtumat($kasittely->id, $request->input('properties.tapahtumat'));

                /*
                 * Jos käsittelyn päätöspäivä annettu, päätetään kaikki käsittelyn löydöt ja näytteet,
                 * jos päätöspäivää ei niillä vielä ole.
                 */
                if($request->input('properties.paattyy')){

                    // Käsittelyyn kuuluvat löydöt ja näytteet
                    $loydotNaytteet = KonservointiKasittely::haeLoydotNaytteet($kasittely->id);

                    if($loydotNaytteet){
                         foreach ($loydotNaytteet as $ln){
                            // Löytö
                            if($ln->tyyppi == 'L'){
                                KonservointiLoyto::paivita_kasittelyn_paatospaiva($ln->toimenpide_id, $kasittely->id, $ln->loyto_nayte_id, $request->input('properties.paattyy'));
                            }
                            // Näyte
                            if($ln->tyyppi == 'N'){
                                KonservointiNayte::paivita_kasittelyn_paatospaiva($ln->toimenpide_id, $kasittely->id, $ln->loyto_nayte_id, $request->input('properties.paattyy'));
                            }
                         }
                    }
                }

                DB::commit();
            } catch(Exception $e) {
                DB::rollback();
                throw $e;
            }

            // Hae käsittely
            $kasittely = KonservointiKasittely::getSingle($id)->with( array(
                'tapahtumat' => function($query) {
                $query->orderBy('paivamaara', 'DESC');
                }
                ))->first();

            // Käsittelyyn kuuluvat löydöt ja näytteet
            $loydotNaytteet = KonservointiKasittely::haeLoydotNaytteet($id);

            if($loydotNaytteet){
                $kasittely->loydot_naytteet = $loydotNaytteet;
            }else{
                $kasittely->loydot_naytteet = array();
            }

            MipJson::addMessage(Lang::get('konservointi.treatment_save_success'));
            MipJson::setGeoJsonFeature(null, $kasittely);
            MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointi.treatment_save_failed'));
        }
        return MipJson::getJson();
    }

    /**
     * Poista käsittely
     */
    public function destroy($id) {

        /*
         * Käyttöoikeustarkistus
         */
        if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $kasittely = KonservointiKasittely::find($id);

        if(!$kasittely) {
            // return: not found
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('konservointi.treatment_search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            $author_field = KonservointiKasittely::DELETED_BY;
            $when_field = KonservointiKasittely::DELETED_AT;
            $kasittely->$author_field = Auth::user()->id;
            $kasittely->$when_field = \Carbon\Carbon::now();
            $kasittely->save();

            DB::commit();

            MipJson::addMessage(Lang::get('konservointi.treatment_delete_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $kasittely->id));

        } catch (Exception $e) {
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('konservointi.treatment_delete_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }
}
