<?php

namespace App\Http\Controllers\Ark;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Utils;
use App\Kayttaja;
use App\Ark\Nayte;
use App\Ark\Naytekoodi;
use App\Ark\NayteTapahtuma;
use App\Ark\NayteTapahtumat;
use App\Ark\NayteTilaTapahtuma;

/**
 * Näytteen controller
 */
class NayteController extends Controller
{

    // Näytteen tila
    const POISTETTU_LUETTELOSTA = 3;

    /**
     * Näytteiden haku
     */
    public function index(Request $request) {

        // Käyttöikeudeet tarkistetaan Nayte luokassa

        try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 25;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "luettelointinumero";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $naytteet = Nayte::getAll()->with( array(
                'yksikko',
                'yksikko.tutkimusalue',
                'yksikko.tutkimusalue.tutkimus',
                'naytekoodi',
                'naytekoodi.naytetyypit' => function($query) {
                    $query->orderBy('nimi_fi', 'ASC');
                },
                'naytetyyppi',
                'talteenottotapa',
                'tila',
                'tapahtumat.tapahtumaTyyppi',
                'tapahtumat.luoja',
                'luoja',
                'muokkaaja',
                'tutkimusalue.tutkimus' //IRTOLÖYTÖ
            ));

            // Luettelointinumeron uniikki-tarkistus
            if ($request->uniikki_numero && $request->luettelointinumero) {
                $naytteet->withUniikkiLuettelointinumero($request->luettelointinumero);
            }

            // Näytteistä jätetään oletuksena pois luettelosta poistettu - tilaiset.
            // Jos tila/tilat on annettu suodatetaan sen mukaan
            if($request->naytteen_tilat) {
                $naytteet->withNaytteenTilat($request->naytteen_tilat);
            }else{
                $naytteet->where('ark_nayte_tila_id', '!=', self::POISTETTU_LUETTELOSTA);
            }

            // Suodatus tutkimusalueen yksikön mukaan
            if($request->ark_tutkimusalue_yksikko_id){
                $naytteet->withTutkimusalueYksikko($request->ark_tutkimusalue_yksikko_id);
            }

            // Päänumeron mukaan
            if($request->nayte_paanumero) {
                $naytteet->withPaanumero($request->nayte_paanumero);
            }

            // Suodatus naytekoodien mukaan
            if($request->naytekoodit){
                $naytteet->withNaytekoodit($request->naytekoodit);
            }

            // Luettelointinumeron mukaan
            if($request->luettelointinumero) {
                // Täsmähaku
                if($request->tarkka){
                    $naytteet->withLuettelointinumero($request->luettelointinumero, true);
                }
                // Like haku
                else{
                    $naytteet->withLuettelointinumero($request->luettelointinumero, false);
                }
            }

            // Suodatus tyypin mukaan
            if($request->naytetyypit){
                $naytteet->withNaytetyypit($request->naytetyypit);
            }

            // Tutkimuksen nimen mukaan
            if($request->tutkimuksen_nimi) {
                $naytteet->withTutkimuksenNimi($request->tutkimuksen_nimi);
            }

            if($request->ark_tutkimus_id) {
                $naytteet->withTutkimusId($request->ark_tutkimus_id);
            }

            // Yksikkötunnuksen mukaan
            if($request->yksikkotunnus) {
                $naytteet->withYksikkotunnus($request->yksikkotunnus);
            }

            // Tutkimuksen lyhenteen mukaan
            if($request->tutkimusLyhenne) {
                $naytteet->withTutkimusLyhenne($request->tutkimusLyhenne);
            }

            /* Näytettä jäljellä
             *  1 = ei, 2 = kyllä, 3 = kaikki
             */
            if($request->naytetta_jaljella){
                if($request->naytetta_jaljella != 3){
                    $naytteet->withNaytettaJaljella($request->naytetta_jaljella);
                }
            }

            // Kuvauksen mukaan
            if($request->kuvaus) {
                $naytteet->withKuvaus($request->kuvaus);
            }

            // Lisätietojen mukaan
            if($request->lisatiedot) {
                $naytteet->withLisatiedot($request->lisatiedot);
            }

            // Luokan mukaan, jos muu kuin 5 eli kaikki
            if($request->luokka && $request->luokka != 5){
                $naytteet->withLuokka($request->luokka);
            }

            if($request->ark_irtoloytotutkimusalue_id) {
                $naytteet->withIrtoloytotutkimusAlueId($request->ark_irtoloytotutkimusalue_id);
            }

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($naytteet);

            // ID:t listaan ennen rivien rajoitusta
            $kori_id_lista = Utils::getIdLista($naytteet);

            // Rivimäärien rajoitus parametrien mukaan
            $naytteet->withLimit($rivi, $riveja);

            // Sorttaus
            $naytteet->withOrderBy($jarjestys_kentta, $jarjestys_suunta);

            // suorita query
            $naytteet = $naytteet->get();

            MipJson::initGeoJsonFeatureCollection(count($naytteet), $total_rows);
            foreach ($naytteet as $nayte) {
                // näytteen lisäys feature listaan
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $nayte);
            }

            /*
             * Koritoiminnallisuus. Palautetaan näytteiden id:t listana.
             */
            MipJson::setIdList($kori_id_lista);
            MipJson::addMessage(Lang::get('nayte.search_success'));

        } catch (Exception $e) {
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('nayte.search_failed'));
        }

        return MipJson::getJson();
    }

    /**
     * Näytteen haku
     */
    public function show($id) {

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_nayte.katselu', $id)) {
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
        try {

            // Hae näyte
            $nayte = Nayte::getSingle($id)->with( array(
                'yksikko',
                'yksikko.tutkimusalue',
                'yksikko.tutkimusalue.tutkimus',
                'yksikko.tutkimusalue.tutkimus.nayteKokoelmalaji',
                'naytekoodi',
                'naytekoodi.naytetyypit' => function($query) {
                $query->orderBy('nimi_fi', 'ASC');
                },
                'naytetyyppi',
                'talteenottotapa',
                'tila',
                'tekija',
                'luoja',
                'tapahtumat.tapahtumaTyyppi',
                'tapahtumat.luoja',
                'tapahtumat.sailytystila',
                'tutkimusalue.tutkimus.nayteKokoelmalaji', //IRTOLÖYTÖ
                'sailytystila'
                 ))->first();

            if(!$nayte) {
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                MipJson::addMessage(Lang::get('nayte.search_not_found'));
                return MipJson::getJson();
            }

            // Muodostetaan propparit
            $properties = clone($nayte);

            // Deserialisoidaan JSON string kannasta
            $properties->migraatiodata = json_decode($properties->migraatiodata, true);

            MipJson::setGeoJsonFeature(null, $properties);

            MipJson::addMessage(Lang::get('nayte.search_success'));
        }
        catch(QueryException $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('nayte.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
    }

    /**
     * Näytteiden haku koriin
     */
    public function kori(Request $request) {

        if(!$request->kori_id_lista && !$request->tapahtuma_id){
            MipJson::addMessage(Lang::get('nayte.search_failed'));
            return MipJson::getJson();
        }

        try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 25;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "luettelointinumero";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            // Käytetään annettua id listaa tai haetaan tapahtumilta id:t
            $idLista = array();

            $idLista = $request->kori_id_lista;

            for($i = 0; $i<sizeof($idLista); $i++) {
                $idLista[$i] = intval($idLista[$i]);
            }

            // Haetaan tapahtumista löytö idt:t
            if($request->tapahtuma_id && $request->luotu){
                $idLista = NayteTapahtumat::haeKoriTapahtumat($request->tapahtuma_id, $request->luotu);
            }

            $naytteet = Nayte::getAll()->with( array(
                'yksikko',
                'yksikko.tutkimusalue',
                'yksikko.tutkimusalue.tutkimus',
                'naytekoodi',
                'naytekoodi.naytetyypit' => function($query) {
                $query->orderBy('nimi_fi', 'ASC');
                },
                'naytetyyppi',
                'talteenottotapa',
                'tila',
                'tapahtumat',
                'luoja',
                'muokkaaja'
                    ));

            // Suodatus id listan mukaan
            $naytteet->withNayteIdLista($idLista);

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($naytteet);

            // ID:t listaan ennen rivien rajoitusta
            $kori_id_lista = Utils::getIdLista($naytteet);

            // Rivimäärien rajoitus parametrien mukaan
            $naytteet->withLimit($rivi, $riveja);

            // Sorttaus
            $naytteet->withOrderBy($jarjestys_kentta, $jarjestys_suunta);

            // suorita query
            $naytteet = $naytteet->get();

            MipJson::initGeoJsonFeatureCollection(count($naytteet), $total_rows);

            foreach ($naytteet as $nayte) {
                //Tarkastetaan jokaiselle löydölle kuuluvat oikeudet
                $nayte->oikeudet = Kayttaja::getPermissionsByEntity('arkeologia', 'ark_nayte', $nayte->id);

                // näytteen lisäys feature listaan
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $nayte);
            }

            /*
             * Koritoiminnallisuus. Palautetaan näytteiden id:t listana.
             */
            MipJson::setIdList($kori_id_lista);

            MipJson::addMessage(Lang::get('nayte.search_success'));
        }
        catch(QueryException $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('nayte.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Tallenna uusi näyte
     *
     */
    public function store(Request $request) {

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_nayte.luonti', $request->input('properties.ark_tutkimus_id'))) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'luettelointinumero'			=> 'required|string',
            'ark_tutkimusalue_yksikko_id'	=> 'nullable|numeric',
            'naytekoodi.id'	                => 'required|numeric'
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

            try {

                $nayte = new Nayte($request->all()['properties']);

                // Valintalistojen id:t
                $nayte->ark_naytekoodi_id = $request->input('properties.naytekoodi.id');
                $nayte->ark_naytetyyppi_id = $request->input('properties.naytetyyppi.id');
                $nayte->ark_talteenottotapa_id = $request->input('properties.talteenottotapa.id');
                $nayte->ark_nayte_tila_id = $request->input('properties.tila.id');

                $nayte->luoja = Auth::user()->id;
                $nayte->save();

                /*
                 * Tapahtumien päivitys.
                 * Luodaa luettelointinumeron lisäys-tapahtuma uuden näytteen lisäämisessä.
                 */
                $tapahtuma = self::uusiTapahtuma($nayte);
                $tapahtuma->ark_nayte_tapahtuma_id = NayteTapahtuma::LUETTELOITU;
                $tapahtuma->tapahtumapaivamaara = date('Y-m-d');
                $tapahtuma->luotu = \Carbon\Carbon::now();
                $tapahtuma->save();

            } catch(Exception $e) {
                DB::rollback();
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
                MipJson::addMessage(Lang::get('sample.save_failed'));
            }

            // Onnistunut case
            DB::commit();

            // Haetaan tallennettu, jotta saadaan relaatiot mukaan
            $nayte = Nayte::getSingle($nayte->id)->with( array(
                'yksikko',
                'yksikko.tutkimusalue',
                'yksikko.tutkimusalue.tutkimus',
                'naytekoodi',
                'naytekoodi.naytetyypit' => function($query) {
                    $query->orderBy('nimi_fi', 'ASC');
                },
                'naytetyyppi',
                'talteenottotapa',
                'tila',
                'luoja',
                'tapahtumat.tapahtumaTyyppi',
                'tapahtumat.luoja',
                'tapahtumat.sailytystila',
                'tutkimusalue.tutkimus.nayteKokoelmalaji', //IRTOLÖYTÖ
                'sailytystila'
                    ))->first();

            MipJson::addMessage(Lang::get('nayte.save_success'));
            MipJson::setGeoJsonFeature(null, $nayte);
            MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('nayte.save_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Hakee näytekoodin ja sen näytetyypit
     */
    public function haeNaytetyypit($id) {

        try {

            $koodi = Naytekoodi::getSingle($id)->with( array(
                'naytetyypit' => function($query) {
                    $query->orderBy('nimi_fi', 'ASC');
                }
                ))->first();

                // Muodostetaan propparit
                $properties = clone($koodi);

                MipJson::setGeoJsonFeature(null, $properties);

                MipJson::addMessage(Lang::get('nayte.search_success'));

        } catch (Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('nayte.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Näytteen päivitys
     */
    public function update(Request $request, $id) {

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_nayte.muokkaus', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'luettelointinumero'			=> 'required|string',
            'ark_tutkimusalue_yksikko_id'	=> 'nullable|numeric'
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
                $nayte = Nayte::find($id);

                if(!$nayte){
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('nayte.search_not_found'));
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                }
                else {
                    DB::beginTransaction();
                    Utils::setDBUser();

                    try {
                        $nayte->fill($request->all()['properties']);

                        // Valintalistojen id:t
                        $nayte->ark_naytekoodi_id = $request->input('properties.naytekoodi.id');
                        $nayte->ark_naytetyyppi_id = $request->input('properties.naytetyyppi.id');
                        $nayte->ark_talteenottotapa_id = $request->input('properties.talteenottotapa.id');
                        $nayte->ark_nayte_tila_id = $request->input('properties.tila.id');
                        $nayte->vakituinen_sailytystila_id = $request->input('properties.sailytystila.id');

                        $author_field = Nayte::UPDATED_BY;
                        $nayte->$author_field = Auth::user()->id;

                        // Konservoinnin kuntoarvion tekijä
                        $nayte->tekija = $request->input('properties.tekija.id');

                        // Päivittämällä parentin aikaleimaa saadaan lapsitaulujen muutokset muutoshistoriaan helpommin
                        $nayte->touch();
                        $nayte->update();

                        // Luodaan näytteen tilan muutos tapahtuma
                        //TODO: Voidaanko käyttää frontilta välitettävää .properties._tilanmuutos -atribuuttia?
                        if($request->input('properties.tapahtumapaiva') && $request->input('properties.tila')){
                            // Tapahtumapäivästä muodostetaan aikaleima jota käytetään luonti-aikaleimana
                            $inputAikaleima = strtotime($request->input('properties.tapahtumapaiva'));
                            self::luoTilanMuutosTapahtuma($nayte, $inputAikaleima, $request);
                        }

                    } catch(Exception $e) {
                        DB::rollback();
                        throw $e;
                    }

                    // Päivitys onnistui
                    DB::commit();

                    // Hae näyte päivitetyin tiedoin
                    $nayte = Nayte::getSingle($nayte->id)->with( array(
                        'yksikko',
                        'yksikko.tutkimusalue',
                        'yksikko.tutkimusalue.tutkimus',
                        'naytekoodi',
                        'naytekoodi.naytetyypit' => function($query) {
                        $query->orderBy('nimi_fi', 'ASC');
                        },
                        'naytetyyppi',
                        'talteenottotapa',
                        'tila',
                        'tapahtumat.tapahtumaTyyppi',
                        'tapahtumat.luoja',
                        'tapahtumat.sailytystila',
                        'tekija',
                        'luoja',
                        'muokkaaja',
                        'tutkimusalue.tutkimus.nayteKokoelmalaji', //IRTOLÖYTÖ
                        'sailytystila'
                            ))->first();

                    // Deserialisoidaan JSON string kannasta
                    $nayte->migraatiodata = json_decode($nayte->migraatiodata, true);

                    MipJson::addMessage(Lang::get('nayte.save_success'));
                    MipJson::setGeoJsonFeature(null, $nayte);
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
                MipJson::setMessages(array(Lang::get('nayte.update_failed'), $e->getMessage()));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Korissa olevien näytteiden tilan muutos
     */
    public function koriTilamuutos(Request $request) {

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'tila'			    => 'required',
            'tapahtumapaiva'    => 'required'
        ]);

        if ($validator->fails()) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            foreach($validator->errors()->all() as $error)
                MipJson::addMessage($error);
                MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
                return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            try {
                $naytteet = Nayte::getAll();
                // Suodatus id listan mukaan
                $naytteet->withNayteIdLista($request->kori_id_lista);

                // suorita query
                $naytteet = $naytteet->get();

                // Tapahtumapäivästä muodostetaan aikaleima jota käytetään luonti-aikaleimana. Kaikille sama
                $inputAikaleima = \Carbon\Carbon::now()->toDateTimeString();//strtotime($request->input('properties.tapahtumapaiva'));

                foreach ($naytteet as $nayte) {
                    /*
                     * Tarkastetaan, että näytteeseen on muokkausoikeus
                     * Jos ei ole niin peruutetaan koko transaktio
                     * Tätä ei pitäisi normaalisti tapahtua, koska nämä filtteröidään jo uissa myös
                     */
                    $permissions = Kayttaja::getPermissionsByEntity('arkeologia', 'ark_nayte', $nayte->id);

                    if($permissions['muokkaus'] == false) {
                        DB::rollback();
                        MipJson::setGeoJsonFeature();
                        //Palautetaan virheilmoituksessa hieman tietoa mikä on probleema
                        MipJson::addMessage(Lang::get('nayte.no_modify_permission') . " - " . $nayte->luettelointinumero . " " . $nayte->yksikkotunnus);
                        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
                        return MipJson::getJson();
                    }
                    // Päivitetään löydön tila
                    $nayte->ark_nayte_tila_id = $request->input('properties.tila.id');

                    //Päivitetään tarvittaessa näytteen tapahtumaan liittyvät attribuutit,
                    //jotka voivat tilamuutoksen myötä muuttua:
                    //vakituinen sijainti, hyllypaikka, tilapäinen sijainti
                    if($request->input('properties.tilapainen_sijainti')) {
                        $nayte->tilapainen_sijainti = $request->input('properties.tilapainen_sijainti');
                    }
                    if($request->input('properties.sailytystila.id')) {
                        $nayte->vakituinen_sailytystila_id = $request->input('properties.sailytystila.id');
                    }
                    if($request->input('properties.vakituinen_hyllypaikka')) {
                        $nayte->vakituinen_hyllypaikka = $request->input('properties.vakituinen_hyllypaikka');
                    }

                    $author_field = Nayte::UPDATED_BY;
                    $nayte->$author_field = Auth::user()->id;
                    $nayte->update();

                    // Luodaan näytteen tilan muutos tapahtuma
                    if($request->input('properties.tila.id') && $request->input('properties.tapahtumapaiva')){
                        self::luoTilanMuutosTapahtuma($nayte, $inputAikaleima, $request);
                    }
                }

                // Päivitys onnistui
                DB::commit();

                MipJson::addMessage(Lang::get('nayte.status_change_success'));
            }
            catch(QueryException $e) {
                DB::rollback();
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('nayte.status_change_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch(Exception $e) {
            DB::rollback();
            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('loyto.status_change_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

       return MipJson::getJson();
    }

    /**
     * Hakee seuraavan vapaan juoksevan näytteen numeron per tutkimus. (ark_nayte.alanumero)
     */
    public function nayteAlanumero(Request $request) {

        if(!is_numeric($request->tutkimus_id) || !is_numeric($request->naytekoodi_id)) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        } else{
            try {
                $alanumero = Nayte::haeAlanumero($request->tutkimus_id, $request->naytekoodi_id);

                if($alanumero){
                    $alanumero++;
                }else{
                    $alanumero = 1; // ensimmäinen
                }

                MipJson::setGeoJsonFeature(null, $alanumero);
                MipJson::addMessage(Lang::get('nayte.search_success'));

            } catch (Exception $e) {
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
                MipJson::addMessage(Lang::get('nayte.search_failed'));
            }
        }
        return MipJson::getJson();
    }

    /*
     * Uuden tapahtuman alustus
     */
    private function uusiTapahtuma($nayte){
        $tapahtuma = new NayteTapahtumat();
        $tapahtuma->ark_nayte_id = $nayte->id;
        $tapahtuma->luoja = Auth::user()->id;

        return $tapahtuma;
    }

    /**
     * Näytteen tila muutettu. Tila id ja tapahtuma id yhdistetään välitaululla NayteTilaTapahtuma.
     */
    private function luoTilanMuutosTapahtuma($nayte, $inputAikaleima, $request){

        $luotu = \Carbon\Carbon::now()->toDateTimeString();//\Carbon\Carbon::createFromTimestamp($inputAikaleima);

        // Haetaan näytteen tilalle tapahtuma id
        $tilaTapahtuma = NayteTilaTapahtuma::haeNaytteenTilaIdMukaan($request->input('properties.tila.id'));

        $uusiTapahtuma = self::uusiTapahtuma($nayte);
        $uusiTapahtuma->ark_nayte_tapahtuma_id = $tilaTapahtuma->ark_nayte_tapahtuma_id;

        //Tallennetaan aina jos on
        $uusiTapahtuma->tapahtumapaivamaara = $request->input('properties.tapahtumapaiva');
        $uusiTapahtuma->kuvaus = $request->input('properties.tilan_kuvaus');
        $uusiTapahtuma->luotu = $luotu;

        //Tallennetaan Lainassa, Näyttelyssä -tapahtumalle
        if($uusiTapahtuma->ark_nayte_tapahtuma_id == 5 || $uusiTapahtuma->ark_nayte_tapahtuma_id == 8) {
            $uusiTapahtuma->loppupvm = $request->input('properties.loppupvm');
        }

        //Tallennetaan Lainassa -tapahtumalle
        if($uusiTapahtuma->ark_nayte_tapahtuma_id == 5) {
            $uusiTapahtuma->lainaaja = $request->input('properties.lainaaja');
        }

        //Tallennetaan Näyttelyssä, Analyysissä -tapahtumalle
        //TODO: Halutaanko tätä Analyysissä tapahtumalle? Sitä ei ole speksattu,
        //mutta se voisi olla looginen. Analyysin id 1
        if($tilaTapahtuma->ark_nayte_tapahtuma_id == 8) {
            $uusiTapahtuma->tilapainen_sijainti = $request->input('properties.tilapainen_sijainti');
        }

        //Tallennetaan Kokoelmassa -tapahtumalle
        if($uusiTapahtuma->ark_nayte_tapahtuma_id == 6) {
            $uusiTapahtuma->vakituinen_sailytystila_id = $request->input('properties.sailytystila.id');
            $uusiTapahtuma->vakituinen_hyllypaikka = $request->input('properties.vakituinen_hyllypaikka');
        }

        $uusiTapahtuma->save();
    }
}
