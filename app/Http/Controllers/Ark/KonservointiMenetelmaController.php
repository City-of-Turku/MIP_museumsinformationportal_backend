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
use App\Ark\KonservointiMenetelma;
use App\Ark\KonsToimenpideMenetelma;

/**
 * Konservoinnin hallinta: menetelmät
 *
 */
class KonservointiMenetelmaController extends Controller
{
    /**
     * Konservoinnin menetelmien haku
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

            $menetelmat = KonservointiMenetelma::getAll($jarjestys_kentta, $jarjestys_suunta)->with(array('toimenpiteet', 'luoja', 'muokkaaja'));

            // Nimihaku
            if($request->nimi){
                $menetelmat->withNimi($request->nimi);
            }

            // Uniikkitarkistus
            if($request->uniikki && $request->nimi){
                $menetelmat->where('nimi', 'ILIKE', $request->nimi);
            }

            // Kuvauksen mukaan
            if($request->kuvaus) {
                $menetelmat->withKuvaus($request->kuvaus);
            }

            // Suodatus toimenpiteillä
            if($request->toimenpiteet){
                $menetelmat->withToimenpiteet($request->toimenpiteet);
            }

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($menetelmat);

            // Rivimäärien rajoitus parametrien mukaan
            $menetelmat->withLimit($rivi, $riveja);

            // suorita query
            $menetelmat = $menetelmat->get();

            MipJson::initGeoJsonFeatureCollection(count($menetelmat), $total_rows);

            foreach ($menetelmat as $menetelma) {
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $menetelma);
            }

            MipJson::addMessage(Lang::get('konservointiHallinta.method_search_success'));

        } catch (Exception $e) {
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointiHallinta.method_search_failed'));
        }

        return MipJson::getJson();
    }

    /**
     * Uuden menetelmän lisäys
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

                $menetelma = new KonservointiMenetelma($request->all()['properties']);

                $author_field = KonservointiMenetelma::CREATED_BY;
                $menetelma->$author_field = Auth::user()->id;

                $menetelma->save();

                // Välitaulun päivitys
                KonsToimenpideMenetelma::menetelman_toimenpiteet($menetelma->id, $request->input('properties.toimenpiteet'));

                DB::commit();
            } catch(Exception $e) {

                DB::rollback();
                throw $e;
            }

            MipJson::addMessage(Lang::get('konservointiHallinta.method_save_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $menetelma->id));

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointiHallinta.method_save_failed'));
        }
        return MipJson::getJson();
    }

    /**
     * Päivitä menetelmä
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
            $menetelma = KonservointiMenetelma::find($id);

            if(!$menetelma){
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('konservointiHallinta.method_search_not_found'));
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                return MipJson::getJson();
            }

            try {
                DB::beginTransaction();
                Utils::setDBUser();

                $menetelma->fill($request->all()['properties']);

                $author_field = KonservointiMenetelma::UPDATED_BY;
                $menetelma->$author_field = Auth::user()->id;

                $menetelma->save();

                // Välitaulun päivitys
                KonsToimenpideMenetelma::menetelman_toimenpiteet($menetelma->id, $request->input('properties.toimenpiteet'));

                DB::commit();
            } catch(Exception $e) {
                DB::rollback();
                throw $e;
            }

            MipJson::addMessage(Lang::get('konservointiHallinta.method_save_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $menetelma->id));
            MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointiHallinta.method_save_failed'));
        }
        return MipJson::getJson();
    }

    /**
     * Poista menetelmä
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

        $menetelma = KonservointiMenetelma::find($id);

        if(!$menetelma) {
            // return: not found
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('konservointiHallinta.method_search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            $author_field = KonservointiMenetelma::DELETED_BY;
            $when_field = KonservointiMenetelma::DELETED_AT;
            $menetelma->$author_field = Auth::user()->id;
            $menetelma->$when_field = \Carbon\Carbon::now();
            $menetelma->save();

            DB::commit();

            MipJson::addMessage(Lang::get('konservointiHallinta.method_delete_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $menetelma->id));

        } catch (Exception $e) {
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('konservointiHallinta.method_delete_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }
}
