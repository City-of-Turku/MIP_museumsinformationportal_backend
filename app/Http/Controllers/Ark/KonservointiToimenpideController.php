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
use App\Ark\KonservointiToimenpide;
use App\Ark\KonsToimenpideMenetelma;

/**
 * Konservoinnin hallinta: toimenpiteet
 *
 */
class KonservointiToimenpideController extends Controller
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
        if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
            $sallittu = false;
            if(Auth::user()->ark_rooli == 'katselija' && $request->tutkimus_id) {
                $tutkimus = Tutkimus::find($request->tutkimus_id);
                $permissions = Kayttaja::getArkTutkimusPermissions($tutkimus);
                // Varmistetaan että käyttäjä löytyy tutkimuksen oikeuksista
                if(isset($permissions['katselu']) && $permissions['katselu'] && isset($permissions['kayttaja_id']) && $permissions['kayttaja_id'] == Auth::user()->id) {
                    $sallittu = true;
                }
            }
            if(!$sallittu) {
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
                MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
                return MipJson::getJson();
            }
        }

        try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 10;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $toimenpiteet = KonservointiToimenpide::getAll($jarjestys_kentta, $jarjestys_suunta)->with(array('menetelmat', 'luoja', 'muokkaaja'));

            // Haku id:n mukaan
            if($request->id){
                $toimenpiteet->withId($request->id);
            }

            // Nimihaku
            if($request->nimi){
                $toimenpiteet->withNimi($request->nimi);
            }

            // Uniikkitarkistus
            if($request->uniikki && $request->nimi){
                $toimenpiteet->where('nimi', 'ILIKE', $request->nimi);
            }

            // Suodatus menetelmillä
            if($request->menetelmat){
                $toimenpiteet->withMenetelmat($request->menetelmat);
            }

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($toimenpiteet);

            // Rivimäärien rajoitus parametrien mukaan
            $toimenpiteet->withLimit($rivi, $riveja);

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
     * Uuden toimenpiteen lisäys
     * @param Request $request
     * @throws Exception
     * @return array
     */
    public function store(Request $request) {

        /*
         * Käyttöoikeustarkistus
         */
        if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
            $sallittu = false;
            $tutkimus_id = $request->tutkimus_id ?? ($request->input('properties.tutkimus_id') ?? null);
            if(Auth::user()->ark_rooli == 'katselija' && $tutkimus_id) {
                $tutkimus = Tutkimus::find($tutkimus_id);
                $permissions = Kayttaja::getArkTutkimusPermissions($tutkimus);
                // Varmistetaan että käyttäjä löytyy tutkimuksen oikeuksista
                if(isset($permissions['luonti']) && $permissions['luonti'] && isset($permissions['kayttaja_id']) && $permissions['kayttaja_id'] == Auth::user()->id) {
                    $sallittu = true;
                }
            }
            if(!$sallittu) {
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
                MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
                return MipJson::getJson();
            }
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

                $toimenpide = new KonservointiToimenpide($request->all()['properties']);

                $author_field = KonservointiToimenpide::CREATED_BY;
                $toimenpide->$author_field = Auth::user()->id;

                $toimenpide->save();

                // Välitaulun päivitys
                KonsToimenpideMenetelma::toimenpiteen_menetelmat($toimenpide->id, $request->input('properties.menetelmat'));

                DB::commit();
            } catch(Exception $e) {

                DB::rollback();
                throw $e;
            }

            MipJson::addMessage(Lang::get('konservointiHallinta.operation_save_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $toimenpide->id));

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointiHallinta.operation_save_failed'));
        }
        return MipJson::getJson();
    }

    /**
     * Päivitä toimenpide
     */
    public function update(Request $request, $id) {

        /*
         * Käyttöoikeustarkistus
         */
        if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
            $sallittu = false;
            $tutkimus_id = $request->tutkimus_id ?? ($request->input('properties.tutkimus_id') ?? null);
            if(Auth::user()->ark_rooli == 'katselija' && $tutkimus_id) {
                $tutkimus = Tutkimus::find($tutkimus_id);
                $permissions = Kayttaja::getArkTutkimusPermissions($tutkimus);
                // Varmistetaan että käyttäjä löytyy tutkimuksen oikeuksista
                if(isset($permissions['muokkaus']) && $permissions['muokkaus'] && isset($permissions['kayttaja_id']) && $permissions['kayttaja_id'] == Auth::user()->id) {
                    $sallittu = true;
                }
            }
            if(!$sallittu) {
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
                MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
                return MipJson::getJson();
            }
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
            $toimenpide = KonservointiToimenpide::find($id);

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

                $author_field = KonservointiToimenpide::UPDATED_BY;
                $toimenpide->$author_field = Auth::user()->id;

                $toimenpide->save();

                // Välitaulun päivitys
                KonsToimenpideMenetelma::toimenpiteen_menetelmat($toimenpide->id, $request->input('properties.menetelmat'));

                DB::commit();
            } catch(Exception $e) {
                DB::rollback();
                throw $e;
            }

            MipJson::addMessage(Lang::get('konservointiHallinta.operation_save_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $toimenpide->id));
            MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('konservointiHallinta.operation_save_failed'));
        }
        return MipJson::getJson();
    }

    /**
     * Poista toimenpide
     */
    public function destroy($id) {

        /*
         * Käyttöoikeustarkistus
         */
        if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
            $sallittu = false;
            // Haetaan toimenpiteen tutkimus_id, jos mahdollista
            $toimenpide = KonservointiToimenpide::find($id);
            $tutkimus_id = $toimenpide ? $toimenpide->tutkimus_id : null;
            if(Auth::user()->ark_rooli == 'katselija' && $tutkimus_id) {
                $tutkimus = Tutkimus::find($tutkimus_id);
                $permissions = Kayttaja::getArkTutkimusPermissions($tutkimus);
                // Varmistetaan että käyttäjä löytyy tutkimuksen oikeuksista
                if(isset($permissions['poisto']) && $permissions['poisto'] && isset($permissions['kayttaja_id']) && $permissions['kayttaja_id'] == Auth::user()->id) {
                    $sallittu = true;
                }
            }
            if(!$sallittu) {
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
                MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
                return MipJson::getJson();
            }
        }

        $toimenpide = KonservointiToimenpide::find($id);

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

            $author_field = KonservointiToimenpide::DELETED_BY;
            $when_field = KonservointiToimenpide::DELETED_AT;
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
}
