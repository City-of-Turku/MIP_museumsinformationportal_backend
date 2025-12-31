<?php

namespace App\Http\Controllers\Ark;

use App\Utils;
use App\Ark\Tutkimus;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Ark\KonservointiLoyto;
use App\Ark\KonservointiNayte;
use App\Ark\KonsToimenpiteet;
use App\Ark\KonsToimenpideMateriaalit;
use App\Kayttaja;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class KonsToimenpiteetController extends Controller
{
    /**
     * Konservoinnin toimenpiteiden haku
     * @param Request $request
     * @return array
     */
    public function index(Request $request) {

        /*
         * Käyttöoikeustarkistus
         */
        if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä'&& Auth::user()->ark_rooli != 'katselija') {
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
                MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
                return MipJson::getJson();
            }

        try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 1000;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "alkaa";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "desc";

            $toimenpiteet = KonsToimenpiteet::getAll()->with(array(
                'toimenpide',
                'toimenpide.menetelmat',
                'menetelma',
                'materiaalit',
                'loydot',
                'naytteet',
                'kasittely',
                'tekija',
                'luoja',
                'muokkaaja'));


            // Suodatus löydön mukaan
            if($request->loyto_id){
                $toimenpiteet->withLoyto($request->loyto_id);
            }

            // Suodatus näytteen mukaan
            if($request->nayte_id){
                $toimenpiteet->withNayte($request->nayte_id);
            }

            // Toimenpiteellä
            if($request->toimenpiteet){
                $toimenpiteet->withToimenpiteet($request->toimenpiteet);
            }

            // Menetelmillä
            if($request->menetelmat){
                $toimenpiteet->withMenetelmat($request->menetelmat);
            }

            // Tekijällä
            if($request->kayttajat){
                $toimenpiteet->where('tekija', '=', $request->kayttajat);
            }

            // Materiaaleilla
            if($request->materiaalit){
                $toimenpiteet->withMateriaalit($request->materiaalit);
            }

            // Alkamispäivän mukaan
            if($request->alkaa){
                $toimenpiteet->whereDate('alkaa', '=', $request->alkaa);
            }

            // Käsittelyt
            if($request->kasittelyt){
                $toimenpiteet->whereIn('ark_kons_kasittely_id', explode(',', $request->kasittelyt));
            }

            // Sorttaus
            $toimenpiteet->withOrderBy($jarjestys_kentta, $jarjestys_suunta);

            // Rivimäärien rajoitus parametrien mukaan
            $toimenpiteet->withLimit($rivi, $riveja);


            // Rivien määrän laskenta
            $total_rows = Utils::getCount($toimenpiteet);

            // suorita query
            $toimenpiteet = $toimenpiteet->get();

            MipJson::initGeoJsonFeatureCollection(count($toimenpiteet), $total_rows);

            foreach ($toimenpiteet as $toimenpide) {
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $toimenpide);
            }

            MipJson::addMessage(Lang::get('konservointiHallinta.operation_search_success'));

        } catch (Exception $e) {
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointiHallinta.operation_search_failed'));
        }

        return MipJson::getJson();
    }

    /**
     * Konservointitoimenpiteen haku
     */
    public function show($id) {
        //TODO hae konservointitoimenpide $id arvolla ja sieltä sen loyto_id
        $ark_loyto_id = null;
        $toimenpide = KonsToimenpiteet::getSingle($id)->with(array('loydot.loyto'))->first();
        if($toimenpide && $toimenpide->loydot && count($toimenpide->loydot) > 0){
            $ark_loyto_id = $toimenpide->loydot[0]->loyto->id;
        }  

    	if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.katselu', $ark_loyto_id)) {
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

                // Hae toimenpide
                $toimenpide = KonsToimenpiteet::getSingle($id)->with(array(
                    'toimenpide',
                    'menetelma',
                    'materiaalit',
                    'loydot.loyto',
                    'naytteet.nayte',
                    'kasittely',
                    'tekija',
                    'luoja',
                    'muokkaaja'))->first();

                    if(!$toimenpide) {
                        MipJson::setGeoJsonFeature();
                        MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                        MipJson::addMessage(Lang::get('konservointiHallinta.operation_search_not_found'));
                        return MipJson::getJson();
                    }

                    // Luettelointinumero, löydön id kaivetaan löydöltä ja alkamispäivä kaikilla toimenpiteen alkamispäivä
                    foreach ($toimenpide->loydot as $loyto_tp){
                        $loyto_tp->luettelointinumero = $loyto_tp->loyto->luettelointinumero;
                        $loyto_tp->ark_loyto_id = $loyto_tp->loyto->id;
                        $loyto_tp->alkaa = $toimenpide->alkaa;
                    }

                    // Luettelointinumero, näytteen id kaivetaan näytteeltä ja alkamispäivä kaikilla toimenpiteen alkamispäivä
                    foreach ($toimenpide->naytteet as $nayte_tp){
                        $nayte_tp->luettelointinumero = $nayte_tp->nayte->luettelointinumero;
                        $nayte_tp->ark_nayte_id = $nayte_tp->nayte->id;
                        $nayte_tp->alkaa = $toimenpide->alkaa;
                    }

                    // Muodostetaan propparit
                    $properties = clone($toimenpide);

                    MipJson::setGeoJsonFeature(null, $properties);

                    MipJson::addMessage(Lang::get('konservointiHallinta.operation_search_success'));
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('konservointiHallinta.operation_search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Uuden konservointitoimenpiteen lisäys
     * @param Request $request
     * @throws Exception
     * @return array
     */
    public function store(Request $request) {

        $ark_loyto_id = $request->input('properties.ark_loyto_id');

        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.luonti', $ark_loyto_id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all()['properties'], [
            //'toimenpide.id'	    => 'required|numeric',
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

                $toimenpide = new KonsToimenpiteet($request->all()['properties']);

                // Valintalistojen id:t
                $toimenpide->ark_kons_toimenpide_id = $request->input('properties.toimenpide.id');
                $toimenpide->ark_kons_menetelma_id = $request->input('properties.menetelma.id');
                $toimenpide->ark_kons_kasittely_id = $request->input('properties.kasittely.id');

                $author_field = KonsToimenpiteet::CREATED_BY;
                $toimenpide->$author_field = Auth::user()->id;
                $toimenpide->tekija = $request->input('properties.tekija.id');

                $toimenpide->save();

                // Löytöjen välitaulun päivitys
                if($request->input('properties.loydot')){
                    KonservointiLoyto::paivita_loydot($toimenpide->id, $request->input('properties.loydot'), $toimenpide->ark_kons_kasittely_id);
                }

                // Näytteiden välitaulun päivitys
                if($request->input('properties.naytteet')){
                    KonservointiNayte::paivita_naytteet($toimenpide->id, $request->input('properties.naytteet'), $toimenpide->ark_kons_kasittely_id);
                }

                // Materiaalit
                KonsToimenpideMateriaalit::paivita_materiaalit($toimenpide->id, $request->input('properties.materiaalit'));

                DB::commit();
            } catch(Exception $e) {
                DB::rollback();
                throw $e;
            }

            // Hae toimenpide
            $toimenpide = KonsToimenpiteet::getSingle($toimenpide->id)->with(array(
                'toimenpide',
                'menetelma',
                'materiaalit',
                'loydot.loyto',
                'naytteet.nayte',
                'kasittely',
                'tekija',
                'luoja',
                'muokkaaja'))->first();

            // Luettelointinumero, löydön id kaivetaan löydöltä ja alkamispäivä kaikilla toimenpiteen alkamispäivä
            foreach ($toimenpide->loydot as $loyto_tp){
                $loyto_tp->luettelointinumero = $loyto_tp->loyto->luettelointinumero;
                $loyto_tp->ark_loyto_id = $loyto_tp->loyto->id;
                $loyto_tp->alkaa = $toimenpide->alkaa;
            }

            // Luettelointinumero, näytteen id kaivetaan näytteeltä ja alkamispäivä kaikilla toimenpiteen alkamispäivä
            foreach ($toimenpide->naytteet as $nayte_tp){
                $nayte_tp->luettelointinumero = $nayte_tp->nayte->luettelointinumero;
                $nayte_tp->ark_nayte_id = $nayte_tp->nayte->id;
                $nayte_tp->alkaa = $toimenpide->alkaa;
            }

            MipJson::addMessage(Lang::get('konservointiHallinta.operation_save_success'));
            MipJson::setGeoJsonFeature(null, $toimenpide);

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointiHallinta.operation_save_failed'));
        }
        return MipJson::getJson();
    }

    /**
     * Päivitä konservointitoimenpide
     */
    public function update(Request $request, $id) {                //TODO hae konservointitoimenpide $id arvolla ja sieltä sen loyto_id
        $ark_loyto_id = null;
        $toimenpide = KonsToimenpiteet::getSingle($id)->with(array('loydot.loyto'))->first();
        if($toimenpide && $toimenpide->loydot && count($toimenpide->loydot) > 0){
            $ark_loyto_id = $toimenpide->loydot[0]->loyto->id;
        }  

        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.muokkaus', $ark_loyto_id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all()['properties'], [
            //'kasittelytunnus'		=> 'required|string'
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
            $toimenpide = KonsToimenpiteet::find($id);

            if(!$toimenpide){
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('konservointiHallinta.operation_search_not_found'));
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                return MipJson::getJson();
            }

            try {
                DB::beginTransaction();
                Utils::setDBUser();

                $toimenpide->fill($request->all()['properties']);

                // Valintalistojen id:t
                $toimenpide->ark_kons_toimenpide_id = $request->input('properties.toimenpide.id');
                $toimenpide->ark_kons_menetelma_id = $request->input('properties.menetelma.id');
                $toimenpide->ark_kons_kasittely_id = $request->input('properties.kasittely.id');

                $author_field = KonsToimenpiteet::UPDATED_BY;
                $toimenpide->$author_field = Auth::user()->id;
                $toimenpide->tekija = $request->input('properties.tekija.id');

                $toimenpide->save();

                // Toimenpiteen päätöspäivä annettu. Asetetaan kaikkien löytöjen/näytteiden päätöspäiväksi se, jos ei ole päätetty jo.
                if($request->input('properties.paata_toimenpide')){
                    KonservointiLoyto::paivita_toimenpiteen_paatospaiva($id, $request->input('properties.paata_toimenpide'));
                    KonservointiNayte::paivita_toimenpiteen_paatospaiva($id, $request->input('properties.paata_toimenpide'));
                }else{
                    // Löytöjen välitaulun päivitys
                    KonservointiLoyto::paivita_loydot($id, $request->input('properties.loydot'), $toimenpide->ark_kons_kasittely_id);

                    // Näytteiden välitaulun päivitys
                    KonservointiNayte::paivita_naytteet($id, $request->input('properties.naytteet'), $toimenpide->ark_kons_kasittely_id);
                }

                // Materiaalit
                KonsToimenpideMateriaalit::paivita_materiaalit($toimenpide->id, $request->input('properties.materiaalit'));

                DB::commit();
            } catch(Exception $e) {
                DB::rollback();
                throw $e;
            }

            // Hae toimenpide
            $toimenpide = KonsToimenpiteet::getSingle($id)->with(array(
                'toimenpide',
                'menetelma',
                'materiaalit',
                'loydot.loyto',
                'naytteet.nayte',
                'kasittely',
                'tekija',
                'luoja',
                'muokkaaja'))->first();

            // Luettelointinumero, löydön id kaivetaan löydöltä ja alkamispäivä kaikilla toimenpiteen alkamispäivä
            foreach ($toimenpide->loydot as $loyto_tp){
                $loyto_tp->luettelointinumero = $loyto_tp->loyto->luettelointinumero;
                $loyto_tp->ark_loyto_id = $loyto_tp->loyto->id;
                $loyto_tp->alkaa = $toimenpide->alkaa;
            }

            // Luettelointinumero, näytteen id kaivetaan näytteeltä ja alkamispäivä kaikilla toimenpiteen alkamispäivä
            foreach ($toimenpide->naytteet as $nayte_tp){
                $nayte_tp->luettelointinumero = $nayte_tp->nayte->luettelointinumero;
                $nayte_tp->ark_nayte_id = $nayte_tp->nayte->id;
                $nayte_tp->alkaa = $toimenpide->alkaa;
            }

                MipJson::addMessage(Lang::get('konservointiHallinta.operation_save_success'));
                MipJson::setGeoJsonFeature(null, $toimenpide);
                MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {

            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointiHallinta.operation_save_failed'));
        }
        return MipJson::getJson();
    }

    /**
     * Poista konservointitoimenpide
     */
    public function destroy($id) {

        //TODO hae konservointitoimenpide $id arvolla ja sieltä sen loyto_id
        $ark_loyto_id = null;
        $toimenpide = KonsToimenpiteet::getSingle($id)->with(array('loydot.loyto'))->first();
        if($toimenpide && $toimenpide->loydot && count($toimenpide->loydot) > 0){
            $ark_loyto_id = $toimenpide->loydot[0]->loyto->id;
        }
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.poisto', $ark_loyto_id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }  

        $toimenpide = KonsToimenpiteet::find($id);

        if(!$toimenpide) {
            // return: not found
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('konservointiHallinta.operation_search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            $author_field = KonsToimenpiteet::DELETED_BY;
            $when_field = KonsToimenpiteet::DELETED_AT;
            $toimenpide->$author_field = Auth::user()->id;
            $toimenpide->$when_field = \Carbon\Carbon::now();
            $toimenpide->save();

            DB::commit();

            MipJson::addMessage(Lang::get('konservointiHallinta.operation_delete_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $toimenpide->id));

        } catch (Exception $e) {
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('konservointiHallinta.operation_delete_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }
     /**
     * Palauttaa tutkimus_id:n requestista tai loyto_id:n perusteella
     */
    private function getTutkimusIdFromRequest(Request $request) {
        $tutkimus_id = $request->tutkimus_id ?? ($request->input('properties.tutkimus_id') ?? null);
        if (!$tutkimus_id) {
            $loyto_id = $request->header('loyto_id') ?? ($request->query('loyto_id') ?? (isset($request->all()['properties']['ark_loyto_id']) ? $request->all()['properties']['ark_loyto_id'] : null));
            if ($loyto_id) {
                $loyto = \App\Ark\Loyto::find($loyto_id);
                if ($loyto) {
                    // 1. Yritetään yksikön kautta
                    if ($loyto->ark_tutkimusalue_yksikko_id) {
                        $yksikko = \App\Ark\TutkimusalueYksikko::find($loyto->ark_tutkimusalue_yksikko_id);
                        if ($yksikko && $yksikko->ark_tutkimusalue_id) {
                            $tutkimusalue = \App\Ark\Tutkimusalue::find($yksikko->ark_tutkimusalue_id);
                            if ($tutkimusalue && $tutkimusalue->ark_tutkimus_id) {
                                $tutkimus_id = $tutkimusalue->ark_tutkimus_id;
                            }
                        }
                    }
                    // 2. Jos ei yksikön kautta, kokeillaan suoraan tutkimusalueen kautta
                    if (!$tutkimus_id && $loyto->ark_tutkimusalue_id) {
                        $tutkimusalue = \App\Ark\Tutkimusalue::find($loyto->ark_tutkimusalue_id);
                        if ($tutkimusalue && $tutkimusalue->ark_tutkimus_id) {
                            $tutkimus_id = $tutkimusalue->ark_tutkimus_id;
                        }
                    }
                }
            }
        }
        return $tutkimus_id;
    }
}
