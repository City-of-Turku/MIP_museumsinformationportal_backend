<?php

namespace App\Http\Controllers\Ark;

use App\Kayttaja;
use App\Utils;
use App\Ark\TutkimusalueYksikko;
use App\Ark\YksikkoMuutMaalajit;
use App\Ark\YksikkoPaasekoitteet;
use App\Ark\YksikkoTyovaihe;
use App\Ark\YksikkoStratigrafia;
use App\Http\Controllers\Controller;
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
 * Yksiköiden käsittelyt
 */
class TutkimusalueYksikkoController extends Controller
{
    // Vakiot
    const NAYTE_POISTETTU_LUETTELOSTA = 3;

    /**
     * Yksiköiden haku
     */
    public function index(Request $request) {
    	/*
         * Käyttöoikeus
         * Yksiköt menevät tutkimusalueen oikeuksien mukaan, koska ne listataan tutkimusalueen "sisällä"
         * Epäselvyyksien välttämisen takia parametrina voi tulla ark_tutkimus_id tai tutkimus_id.
         */
        $hasPermission = false;
        if($request->ark_tutkimus_id) {
            $hasPermission = Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_tutkimusalue.katselu', $request->ark_tutkimus_id);
        }
        if($request->tutkimus_id) {
            $hasPermission = Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_tutkimusalue.katselu', $request->tutkimus_id);
        }
        if(!$hasPermission) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
//         $validator = Validator::make($request->all(), [
//             'ark_tutkimusalue_id'	=> 'required|numeric|exists:'
//         ]);

//         if ($validator->fails()) {
//             MipJson::setGeoJsonFeature();
//             MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
//             foreach($validator->errors()->all() as $error) {
//                 MipJson::addMessage($error);
//             }
//             MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
//             return MipJson::getJson();
//         }

        try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 25;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "yksikon_numero";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $yksikot = TutkimusalueYksikko::getAll()->with( array(
                'tutkimusalue',
                'tutkimusalue.tutkimus',
                'tutkimusalue.tutkimus.nayteKokoelmalaji',
                'tyovaiheet',
                'yksikkoTyyppi',
                'yksikkoKaivaustapa',
                'yksikkoSeulontatapa',
                'paamaalaji',
                'paasekoitteet',
                'muutMaalajit',
                'luoja',
                'muokkaaja'
                    ));

            // Yksikön tunnuksen numeron uniikki-tarkistus
            if ($request->uniikki_numero && $request->yksikon_numero && $request->ark_tutkimus_id) {
                $yksikot->withTutkimus($request->ark_tutkimus_id, $request->yksikon_numero);
            }

            // Luettelointinumeron vaihdossa haetaan tutkimuksen tietty yksikkö
            if($request->ark_tutkimus_id && $request->yksikkotunnus_haku) {
                $yksikot->withTutkimuksenYksikko($request->ark_tutkimus_id, $request->yksikkotunnus_haku);
            }

            // Suodatus tutkimusalueen mukaan
            if($request->ark_tutkimusalue_id){
                $yksikot->withTutkimusalue($request->ark_tutkimusalue_id);
            }

            if($request->tutkimus_id){
                $yksikot->withTutkimusId($request->tutkimus_id);
            }

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($yksikot);

            // Yksikkötunnuksella suodatus
            if($request->yksikkotunnus) {
                $yksikot->withYksikkotunnus($request->yksikkotunnus);
            }

            // Kaivauksen tilalla suodatus
            if($request->kaivaus_valmis){
                $yksikot->withKaivausValmis($request->kaivaus_valmis);
            }

            // Rivimäärien rajoitus parametrien mukaan
            $yksikot->withLimit($rivi, $riveja);

            // Execute the query
            $yksikot = $yksikot->get();

            MipJson::initGeoJsonFeatureCollection(count($yksikot), $total_rows);

            foreach ($yksikot as $yksikko) {

                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $yksikko);

            }
            MipJson::addMessage(Lang::get('tutkimus.search_success'));

        } catch (Exception $e) {
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('tutkimus.search_failed'));
        }


        return MipJson::getJson();
    }

    /**
     * Haetaan yksikkö
     */
    public function show($id) {

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_yksikko.katselu', $id)) {
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

                // Hae yksikkö
                $yksikko = TutkimusalueYksikko::getSingle($id)->with( array(
                    'tutkimusalue',
                    'tutkimusalue.tutkimus',
                    'tyovaiheet',
                    'yksikkoTyyppi',
                    'yksikkoKaivaustapa',
                    'yksikkoSeulontatapa',
                    'loydot',
                    'loydot.materiaalikoodi',
                    'naytteet' => function($query) {
                    $query->where('ark_nayte_tila_id', '!=', self::NAYTE_POISTETTU_LUETTELOSTA);
                    },
                    'naytteet.naytekoodi',
                    'paamaalaji',
                    'paasekoitteet',
                    'muutMaalajit',
                    'luoja',
                    'muokkaaja'))->first();
                if(!$yksikko) {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('tutkimus.search_not_found'));
                    return MipJson::getJson();
                }

                // Löytölistat yksikölle
                self::muodostaLoytolistat($yksikko);

                // Näytteiden lista yksikölle
                self::muodostaNaytelista($yksikko);

                // US9906 vain muokkausoikeuksilla näkyy muistiinpanot
                if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_yksikko.muokkaus', $id)) {
                    // Käyttöliittymästä ei voi avata keskeneräistä yksikköä ilman muokkaus-oikeuksia
                    if(!$yksikko->kaivaus_valmis){
                        MipJson::setGeoJsonFeature();
                        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
                        MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
                        return MipJson::getJson();
                    }
                }

                // Muodostetaan propparit
                $properties = clone($yksikko);

                // Deserialisoidaan JSON string kannasta
                $properties->migraatiodata = json_decode($properties->migraatiodata, true);

                $suhteet = YksikkoStratigrafia::haeSuhteet($yksikko->id)->get();
                $properties->stratigrafia = $suhteet;

                MipJson::setGeoJsonFeature(null, $properties);

                MipJson::addMessage(Lang::get('tutkimus.search_success'));
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('tutkimus.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Yksiköiden katselusivulla näytettävät löytöjen listat
     */
    private function muodostaLoytolistat($yksikko){

        $ajoittamattomat_loydon_koodit = collect([]);
        $ajoitetut_loydon_koodit = collect([]);
        $poistetut_loydon_koodit = collect([]);
        foreach ($yksikko->loydot as $loyto) {

            // 5 = Poistettu löytöluettelosta
            if($loyto->loydon_tila_id == 5){
                $poistetut_loydon_koodit ->push($loyto->materiaalikoodi);
            }else{
                // Ajoitetuksi tulkitaan jos alkuvuosi on annettu
                if($loyto->alkuvuosi != null){
                    $ajoitetut_loydon_koodit ->push($loyto->materiaalikoodi);
                }else{
                    $ajoittamattomat_loydon_koodit->push($loyto->materiaalikoodi);
                }
            }
        }

        // Ryhmitellään materiaalikoodeittain
        $ajoittamattomatLoydot = $ajoittamattomat_loydon_koodit->groupBy(function ($item, $key) {
            return $item['koodi'] . ' ' .$item['nimi_fi'];
        });

        $ajoitetutLoydot = $ajoitetut_loydon_koodit->groupBy(function ($item, $key) {
            return $item['koodi'] . ' ' .$item['nimi_fi'];
        });

        $poistetutLoydot = $poistetut_loydon_koodit->groupBy(function ($item, $key) {
            return $item['koodi'] . ' ' .$item['nimi_fi'];
        });

        // Palautetaan omina listoinaan
        $yksikko->ajoittamattomatLoydot = $ajoittamattomatLoydot;
        $yksikko->ajoitetutLoydot = $ajoitetutLoydot;
        $yksikko->poistetutLoydot = $poistetutLoydot;
    }

    /**
     * Yksiköiden katselusivulla näytettävät näytteet
     */
    private function muodostaNaytelista($yksikko){

        $nayte_koodit = collect([]);
        foreach ($yksikko->naytteet as $nayte) {
            $nayte_koodit ->push($nayte->naytekoodi);
        }

        // Ryhmitellään naytekoodeittain
        $naytteet = $nayte_koodit->groupBy(function ($item, $key) {
            return $item['koodi'] . ' ' .$item['nimi_fi'];
        });
        // Palautetaan omina listoinaan
        $yksikko->naytelista = $naytteet;

    }

    /**
     * Tallenna yksikkö
     *
     */
    public function store(Request $request) {

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_yksikko.luonti', $request->input('properties.ark_tutkimus_id'))) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'yksikkotunnus'     => 'required|string',
            'yksikon_numero'    => 'required|integer'
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

        //Tsekataan, että yksikön_numero ei ole vielä käytössä samalla tutkimuksella
        $count = TutkimusalueYksikko::withTutkimus($request->properties['ark_tutkimus_id'], $request->properties['yksikon_numero'])
        ->count();

        if($count > 0) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('ark.number_already_in_use'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            try {

                $yksikko = new TutkimusalueYksikko($request->all()['properties']);

                // Valintalistojen id:t
                $yksikko->yksikko_tyyppi_id = $request->input('properties.yksikkotyyppi.id');
                $yksikko->yksikko_paamaalaji_id = $request->input('properties.paamaalaji.id');

                $yksikko->luoja = Auth::user()->id;
                $yksikko->save();

                // Välitaulujen tallennus
                YksikkoTyovaihe::paivita_yksikko_tyovaiheet($yksikko->id, $request->input('properties.tyovaiheet'));

            } catch(Exception $e) {
                DB::rollback();
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
                MipJson::addMessage(Lang::get('tutkimus.save_failed'));
            }

            // Onnistunut case
            DB::commit();

            // Hae luotu yksikkö
            $yksikko = TutkimusalueYksikko::getSingle($yksikko->id)->with( array(
                'tutkimusalue',
                'tutkimusalue.tutkimus',
                'luoja',
                'muokkaaja'))->first();

            MipJson::addMessage(Lang::get('tutkimus.save_success'));
            MipJson::setGeoJsonFeature(null, $yksikko);
            MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('tutkimus.save_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Yksikön päivitys
     */
    public function update(Request $request, $id) {

        /*
         * Käyttöoikeus
         */
    	if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_yksikko.muokkaus', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'yksikkotunnus'			=> 'required|string'
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
                $yksikko = TutkimusalueYksikko::find($id);

                if(!$yksikko){
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('tutkimus.search_not_found'));
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                }
                else {
                    DB::beginTransaction();
                    Utils::setDBUser();

                    try {
                        $yksikko->fill($request->all()['properties']);

                        // Valintalistojen id:t
                        $yksikko->yksikko_kaivaustapa_id = $request->input('properties.yksikko_kaivaustapa.id');
                        $yksikko->yksikko_seulontatapa_id = $request->input('properties.yksikko_seulontatapa.id');
                        $yksikko->yksikko_paamaalaji_id = $request->input('properties.paamaalaji.id');

                        // Välitaulujen tallennus
                        YksikkoTyovaihe::paivita_yksikko_tyovaiheet($yksikko->id, $request->input('properties.tyovaiheet'));
                        YksikkoPaasekoitteet::paivita_paasekoitteet($yksikko->id, $request->input('properties.paasekoitteet'));
                        YksikkoMuutMaalajit::paivita_muut_maalajit($yksikko->id, $request->input('properties.muut_maalajit'));

                        YksikkoStratigrafia::tallennaStratigrafia($yksikko->id, $request->input('properties.suhteet'));

                        // Muistiinpanokenttien arvot kopioidaan pohjaksi varsinaisiin kenttiin
                        if(!$request->input('kaivaus_valmis')){
                            self::kopioiMuistiinpanot($yksikko);
                        }

                        $author_field = TutkimusalueYksikko::UPDATED_BY;
                        $yksikko->$author_field = Auth::user()->id;

                        // Päivittämällä parentin aikaleimaa saadaan lapsitaulujen muutokset muutoshistoriaan helpommin
                        $yksikko->touch();
                        $yksikko->update();

                    } catch(Exception $e) {
                        DB::rollback();
                        throw $e;
                    }

                    // Päivitys onnistui
                    DB::commit();

                    MipJson::addMessage(Lang::get('tutkimus.save_success'));
                    MipJson::setGeoJsonFeature(null, $yksikko);
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
                MipJson::setMessages(array(Lang::get('tutkimus.update_failed'), $e->getMessage()));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Muistiinpanokenttien arvot kopioidaan pohjaksi varsinaisiin kenttiin, jos ne ovat tyhjät
     */
    private function kopioiMuistiinpanot($yksikko){
        if(empty($yksikko->kuvaus) && $yksikko->kuvaus_note){
            $yksikko->kuvaus = $yksikko->kuvaus_note;
        }
        if(empty($yksikko->yksikon_perusteet) && $yksikko->yksikon_perusteet_note){
            $yksikko->yksikon_perusteet = $yksikko->yksikon_perusteet_note;
        }
        if(empty($yksikko->stratigrafiset_suhteet) && $yksikko->stratigrafiset_suhteet_note){
            $yksikko->stratigrafiset_suhteet = $yksikko->stratigrafiset_suhteet_note;
        }
        if(empty($yksikko->rajapinnat) && $yksikko->rajapinnat_note){
            $yksikko->rajapinnat = $yksikko->rajapinnat_note;
        }
        if(empty($yksikko->tulkinta) && $yksikko->tulkinta_note){
            $yksikko->tulkinta = $yksikko->tulkinta_note;
        }
        if(empty($yksikko->ajoitus) && $yksikko->ajoitus_note){
            $yksikko->ajoitus = $yksikko->ajoitus_note;
        }
        if(empty($yksikko->ajoituksen_perusteet) && $yksikko->ajoituksen_perusteet_note){
            $yksikko->ajoituksen_perusteet = $yksikko->ajoituksen_perusteet_note;
        }
        if(empty($yksikko->lisatiedot) && $yksikko->lisatiedot_note){
            $yksikko->lisatiedot = $yksikko->lisatiedot_note;
        }
        if(empty($yksikko->kaivaustapa_lisatieto) && $yksikko->kaivaustapa_lisatieto_note){
            $yksikko->kaivaustapa_lisatieto = $yksikko->kaivaustapa_lisatieto_note;
        }
    }

    /**
     * Poista yksikkö. Asetetaan poistettu aikaleima ja poistaja, ei deletoida riviä.
     */
    public function destroy($id) {

        /*
         * Käyttöoikeus
         */
    	if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_yksikko.poisto', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }
        $yksikko = TutkimusalueYksikko::find($id);

        if(!$yksikko) {
            // return: not found
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('tutkimus.search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            // TODO: ennen yksikön poistoa on kaikki sen alla olevat oltava poistettu. Löydöt, näytteet, konservoinnit jne..

            $author_field = TutkimusalueYksikko::DELETED_BY;
            $when_field = TutkimusalueYksikko::DELETED_AT;
            $yksikko->$author_field = Auth::user()->id;
            $yksikko->$when_field = \Carbon\Carbon::now();

            $yksikko->save();

            DB::commit();

            MipJson::addMessage(Lang::get('tutkimus.delete_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $yksikko->id));

        } catch(Exception $e) {
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('tutkimus.delete_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Luo yksikölle seuraavan vapaan juoksevan numeron per tutkimus.
     */
    public function yksikkonumero($id) {

        try {
            if ($id) {
                $yksikon_numero = TutkimusalueYksikko::haeSuurinYksikkoNumero($id);

                if($yksikon_numero){
                    $yksikon_numero++;
                }

                MipJson::setGeoJsonFeature(null, $yksikon_numero);
                MipJson::addMessage(Lang::get('tutkimus.search_success'));
            }

        } catch (Exception $e) {
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('tutkimus.search_failed'));
        }

        return MipJson::getJson();
    }

}
