<?php

namespace App\Http\Controllers\Ark;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Utils;
use App\Ark\KonservointiMateriaali;

/**
 * Konservoinnin hallinta: materiaalit
 *
 */
class KonservointiMateriaaliController extends Controller
{

    /**
     * Konservoinnin materiaalien haku
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
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $materiaalit = KonservointiMateriaali::getAll($jarjestys_kentta, $jarjestys_suunta)->with( array('luoja', 'muokkaaja'));

            // Nimihaku
            if($request->nimi){
                $materiaalit->withNimi($request->nimi);
            }

            // Kemiallinen kaava
            if($request->kemiallinen_kaava){
                $materiaalit->withKemiallinenKaava($request->kemiallinen_kaava);
            }

            // Muut nimet
            if($request->muut_nimet){
                $materiaalit->withMuutNimet($request->muut_nimet);
            }

            // Uniikkitarkistus
            if($request->uniikki && $request->nimi){
                $materiaalit->where('nimi', 'ILIKE', $request->nimi);
            }

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($materiaalit);

            // Rivimäärien rajoitus parametrien mukaan
            $materiaalit->withLimit($rivi, $riveja);

            // suorita query
            $materiaalit = $materiaalit->get();

            MipJson::initGeoJsonFeatureCollection(count($materiaalit), $total_rows);

            foreach ($materiaalit as $materiaali) {

                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $materiaali);
            }

            MipJson::addMessage(Lang::get('konservointiHallinta.material_search_success'));

        } catch (Exception $e) {
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointiHallinta.material_search_failed'));
        }

        return MipJson::getJson();
    }

    /**
     * Uuden materiaalin lisäys
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
            'nimi'		=> 'required|string'
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

                $materiaali = new KonservointiMateriaali($request->all()['properties']);

                $author_field = KonservointiMateriaali::CREATED_BY;
                $materiaali->$author_field = Auth::user()->id;

                $materiaali->save();

                DB::commit();
            } catch(Exception $e) {

                DB::rollback();
                throw $e;
            }

            MipJson::addMessage(Lang::get('konservointiHallinta.material_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $materiaali->id));

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointiHallinta.material_save_failed'));
        }
        return MipJson::getJson();
    }

    /**
     * Päivitä materiaali
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
            'nimi'		=> 'required|string'
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
            $materiaali = KonservointiMateriaali::find($id);

            if(!$materiaali){
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('konservointiHallinta.material_search_not_found'));
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                return MipJson::getJson();
            }

            try {
                DB::beginTransaction();
                Utils::setDBUser();

                $materiaali->fill($request->all()['properties']);

                $author_field = KonservointiMateriaali::UPDATED_BY;
                $materiaali->$author_field = Auth::user()->id;

                $materiaali->save();

                DB::commit();
            } catch(Exception $e) {
                DB::rollback();
                throw $e;
            }

            MipJson::addMessage(Lang::get('konservointiHallinta.material_save_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $materiaali->id));
            MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointiHallinta.material_save_failed'));
        }
        return MipJson::getJson();
    }

    /**
     * Poista materiaali
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

        $materiaali = KonservointiMateriaali::find($id);

        if(!$materiaali) {
            // return: not found
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('konservointiHallinta.material_search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            $author_field = KonservointiMateriaali::DELETED_BY;
            $when_field = KonservointiMateriaali::DELETED_AT;
            $materiaali->$author_field = Auth::user()->id;
            $materiaali->$when_field = \Carbon\Carbon::now();
            $materiaali->save();

            DB::commit();

            MipJson::addMessage(Lang::get('konservointiHallinta.material_delete_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $materiaali->id));

        } catch (Exception $e) {
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('konservointiHallinta.material_delete_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }
}
