<?php

namespace App\Http\Controllers\Ark;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Utils;
use App\Kayttaja;
use App\Ark\Loyto;
use App\Ark\Nayte;
use App\Ark\Rontgenkuva;
use App\Ark\RontgenkuvaNayte;
use App\Ark\RontgenkuvaLoyto;

/**
 * Röntgenkuvan käsittelyt
 */
class RontgenkuvaController extends Controller
{

    /**
     * haku
     */
    public function index(Request $request) {
        //TODO: Käyttäjä joka on tutkija tai pääkäyttäjä näkee ainoastaan röntgenkuvat?
        /*
         * Käyttöoikeus
         */
//         if(!Kayttaja::hasPermission('arkeologia.ark_kuva.katselu')) {
//             MipJson::setGeoJsonFeature();
//             MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
//             MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
//             return MipJson::getJson();
//         }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all(), [
            "ark_loyto_id"				    => "numeric|exists:ark_loyto,id",
            "ark_nayte_id"				    => "numeric|exists:ark_nayte,id"
        ]);
        if ($validator->fails()) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            foreach($validator->errors()->all() as $error) {
                MipJson::addMessage($error);
                MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            }

            return MipJson::getJson();
        }

            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 25;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "numero";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $rk = Rontgenkuva::getAll();

            if($request->input("ark_loyto_id")) {
                $rk->withLoytoId($request->input("ark_loyto_id"));
            }
            if($request->input("ark_nayte_id")) {
                $rk->withNayteId($request->input("ark_nayte_id"));
            }
            if($request->input("numero") && $request->input("tarkka")) {
                $rk->where('numero', 'ilike', $request->input("numero"));
            }

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($rk);

            // Rivimäärien rajoitus parametrien mukaan
            $rk->withLimit($rivi, $riveja);

            // Sorttaus
            $rk->withOrderBy($jarjestys_kentta, $jarjestys_suunta);

            // suorita query
            $rk = $rk->get();

            MipJson::initGeoJsonFeatureCollection(count($rk), $total_rows);
            foreach ($rk as $r) {
                // kuvan lisäys feature listaan
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $r);
            }

            MipJson::addMessage(Lang::get('kuva.search_success'));

        return MipJson::getJson();
    }

    /**
     *
     */
    public function show($id) {
        /*
         * Käyttöoikeus
         */
    	if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.katselu', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        if(!is_numeric($id)) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);

            return MipJson::getJson();
        }

        // Hae
        $rk = Rontgenkuva::getSingle($id)->with(array(
            'luoja',
            'muokkaaja',
            'xrayloydot.loyto',
            'xraynaytteet.nayte'))->first();
        if(!$rk) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            MipJson::addMessage(Lang::get('kuva.search_not_found'));
            return MipJson::getJson();
        }

        //Fiksataan myös löydöt ja yksiköt
        $tmpLoydot = [];
        foreach($rk->xrayloydot as $xl) {
            array_push($tmpLoydot, $xl->loyto);
        }
        $tmpNaytteet = [];
        foreach($rk->xraynaytteet as $xn) {
            array_push($tmpNaytteet, $xn->nayte);
        }

        $rk->loydot = $tmpLoydot;
        $rk->naytteet = $tmpNaytteet;
        unset($rk->xrayloydot);
        unset($rk->xraynaytteet);

        // Muodostetaan propparit
        $properties = clone($rk);

        MipJson::setGeoJsonFeature(null, $properties);

        MipJson::addMessage(Lang::get('kuva.search_success'));

        return MipJson::getJson();
    }

    /**
     * Tallenna uusi
     *
     */
    public function store(Request $request) {

        /*
         * Käyttöoikeus
         */
    	if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_rontgenkuva.luonti', $request->input('properties.ark_tutkimus_id'))) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'numero'			=> 'required|string'
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
            DB::beginTransaction();
            Utils::setDBUser();

            $rk = new Rontgenkuva($request->all()['properties']);

            $rk->luoja = Auth::user()->id;
            $rk->save();

            $this->linkToItem($rk, $request);

            //Linkitetään muihin
            //Tallennetaan linkattavat löydöt ja yksikot
            Rontgenkuva::linkita_loydot($rk->id, $request->properties['loydot']);
            Rontgenkuva::linkita_naytteet($rk->id, $request->properties['naytteet']);

            // Haetaan tallennettu, jotta saadaan materiaalikoodi yms mukaan
            $rk = Rontgenkuva::getSingle($rk->id)->with(array(
                'luoja',
                'muokkaaja',
                'xrayloydot.loyto',
                'xraynaytteet.nayte'))->first();
            //Fiksataan myös löydöt ja yksiköt
            $tmpLoydot = [];
            foreach($rk->xrayloydot as $xl) {
                array_push($tmpLoydot, $xl->loyto);
            }
            $tmpNaytteet = [];
            foreach($rk->xraynaytteet as $xn) {
                array_push($tmpNaytteet, $xn->nayte);
            }

            $rk->loydot = $tmpLoydot;
            $rk->naytteet = $tmpNaytteet;
            unset($rk->xrayloydot);
            unset($rk->xraynaytteet);

            // Onnistunut case
            DB::commit();

            MipJson::addMessage(Lang::get('kuva.save_success'));
            MipJson::setGeoJsonFeature(null, $rk);
            MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {
            DB::rollback();
            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('kuva.save_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Löydön päivitys
     */
    public function update(Request $request, $id) {

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_rontgenkuva.muokkaus', $request->input('properties.ark_tutkimus_id'))) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'numero'			=> 'required|string'
        ]);

        if ($validator->fails()) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            foreach($validator->errors()->all() as $error) {
                MipJson::addMessage($error);
                MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            }

            return MipJson::getJson();
        }
        $rk = Rontgenkuva::find($id);

        if(!$rk){
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kuva.search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }
        try {
            DB::beginTransaction();
            Utils::setDBUser();

            $rk->fill($request->all()['properties']);

            $author_field = Rontgenkuva::UPDATED_BY;
            $rk->$author_field = Auth::user()->id;

            $rk->update();

            //Linkitetään muihin
            //Tallennetaan linkattavat löydöt ja yksikot
            Rontgenkuva::linkita_loydot($rk->id, $request->properties['loydot']);
            Rontgenkuva::linkita_naytteet($rk->id, $request->properties['naytteet']);

            // Päivitys onnistui
            DB::commit();
        } catch (Exception $e) {

        }

        // Hae löytö päivitetyin tiedoin
        $rk = Rontgenkuva::getSingle($id)->with(array(
            'luoja',
            'muokkaaja',
            'xrayloydot.loyto',
            'xraynaytteet.nayte'))->first();

        //Fiksataan myös löydöt ja yksiköt
        $tmpLoydot = [];
        foreach($rk->xrayloydot as $xl) {
            array_push($tmpLoydot, $xl->loyto);
        }
        $tmpNaytteet = [];
        foreach($rk->xraynaytteet as $xn) {
            array_push($tmpNaytteet, $xn->nayte);
        }

        $rk->loydot = $tmpLoydot;
        $rk->naytteet = $tmpNaytteet;
        unset($rk->xrayloydot);
        unset($rk->xraynaytteet);

        MipJson::addMessage(Lang::get('kuva.save_success'));
        MipJson::setGeoJsonFeature(null, $rk);
        MipJson::setResponseStatus(Response::HTTP_OK);

        return MipJson::getJson();
    }

    /**
     * Poista röntgenkuva. Asetetaan poistettu aikaleima ja poistaja, ei deletoida riviä.
     */
    public function destroy($id) {

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_rontgenkuva.poisto', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }
        $rk = Rontgenkuva::find($id);

        if(!$rk) {
            // return: not found
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kuva.search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            $author_field = Rontgenkuva::DELETED_BY;
            $when_field = Rontgenkuva::DELETED_AT;
            $rk->$author_field = Auth::user()->id;
            $rk->$when_field = \Carbon\Carbon::now();

            $rk->save();

            DB::commit();

            MipJson::addMessage(Lang::get('kuva.delete_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $rk->id));

        } catch(Exception $e) {
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('kuva.delete_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    private function linkToItem($entity, $request) {
        //Linkataan oletuksena itemiin josta kuva lisättiin
        switch ($request->input('properties.entiteetti_tyyppi')) {
            case 17:
                $loyto = Loyto::find($request->input('properties.entiteetti_id'));
                $kuvaLoyto = new RontgenkuvaLoyto();
                $kuvaLoyto->luoja = Auth::user()->id;
                $kuvaLoyto->ark_rontgenkuva_id = $entity->id;
                $kuvaLoyto->ark_loyto_id = $loyto->id;
                $kuvaLoyto->save();
                break;
            case 18:
                $nayte = Nayte::find($request->input('properties.entiteetti_id'));
                $kuvaNayte = new RontgenkuvaNayte();
                $kuvaNayte->luoja = Auth::user()->id;
                $kuvaNayte->ark_rontgenkuva_id = $entity->id;
                $kuvaNayte->ark_nayte_id = $nayte->id;
                $kuvaNayte->save();
                break;
        }
    }
}
