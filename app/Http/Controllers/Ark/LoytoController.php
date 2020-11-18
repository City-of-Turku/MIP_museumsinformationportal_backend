<?php

namespace App\Http\Controllers\Ark;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
use App\Ark\Loyto;
use App\Ark\LoytoMateriaalit;
use App\Ark\LoytoMerkinnat;
use App\Ark\ArkLoytotyyppiTarkenteet;
use App\Ark\LoytoAsiasanat;
use App\Ark\LoytoTapahtuma;
use App\Ark\LoytoTapahtumat;
use App\Ark\LoytoTilaTapahtuma;
use App\Ark\LoytoMateriaalikoodi;
use App\Ark\LoytoLuettelointinroHistoria;

/**
 * Löydön käsittelyt
 */
class LoytoController extends Controller
{
    // Löydön tila
    const POISTETTU_LUETTELOSTA = 5;

    /**
     * Löytöjen haku
     */
    public function index(Request $request) {
        /*
         * Käyttöoikeus
         */
//         if(!Kayttaja::hasPermission('arkeologia.ark_loyto.katselu')) {
//             MipJson::setGeoJsonFeature();
//             MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
//             MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
//             return MipJson::getJson();
//         }

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
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "luettelointinumero";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $loydot = Loyto::getAll()->with( array(
                'yksikko',
                'yksikko.tutkimusalue.tutkimus.loytoKokoelmalaji',
                'materiaalikoodi',
                'ensisijainenMateriaali',
                'materiaalit', // hakee välitaulun avulla muut löydön materiaalit
                'loytotyyppi',
                'loytotyyppiTarkenteet',
                'merkinnat',
                'loydonAsiasanat',
                'loydonTila',
                'luoja',
                'muokkaaja',
                'luettelointinrohistoria',
                'tutkimusalue.tutkimus',//IRTOLÖYTÖ tai tarkastus
                'sailytystila'
            ));

            // Löydöistä jätetään oletuksena pois luettelosta poistettu - tilaiset.
            // Jos tila/tilat on annettu suodatetaan sen mukaan
            if($request->loydon_tilat) {
                $loydot->withLoydonTilat($request->loydon_tilat);
            }else{
                $loydot->where('loydon_tila_id', '!=', self::POISTETTU_LUETTELOSTA);
            }

            // Suodatus tutkimusalueen yksikön mukaan
            if($request->ark_tutkimusalue_yksikko_id){
                $loydot->withTutkimusalueYksikko($request->ark_tutkimusalue_yksikko_id);
            }

            // Päänumeron mukaan
            if($request->loyto_paanumero) {
                $loydot->withPaanumero($request->loyto_paanumero);
            }

            /*
             *  Suodatus löydön ajoituksen mukaan.
             *  1 = ajoitetut, 2 = ajoittamattomat, 3 = kaikki
             */
            if($request->valittu_ajoitus){
                if($request->valittu_ajoitus != 3){
                    $loydot->withAjoitus($request->valittu_ajoitus);
                }
            }

            // Suodatus materiaalikoodien mukaan
            if($request->materiaalikoodit){
                $loydot->withMateriaalikoodit($request->materiaalikoodit);
            }

            // Luettelointinumeron mukaan
            if($request->luettelointinumero) {
                // Täsmähaku
                if($request->tarkka){
                    $loydot->withLuettelointinumero($request->luettelointinumero, true);
                }
                // Like haku
                else{
                    $loydot->withLuettelointinumero($request->luettelointinumero, false);
                }
            }

            // Suodatus ensisijaisten materiaalien mukaan
            if($request->ensisijaiset_materiaalit){
                $loydot->withEnsisijaisetMateriaalit($request->ensisijaiset_materiaalit);
            }

            // Suodatus muiden materiaalien mukaan
            if($request->materiaalit){
                $loydot->withMateriaalit($request->materiaalit);
            }

            // Suodatus tyypin mukaan
            if($request->loytotyypit){
                $loydot->withLoytotyypit($request->loytotyypit);
            }

            // Suodatus tyyppien tarkenteiden mukaan. (kenttä Esineen nimi UI:ssa)
            if($request->loytotyyppi_tarkenteet){
                $loydot->withLoytotyyppiTarkenteet($request->loytotyyppi_tarkenteet);
            }

            // Suodatus muiden merkinnän
            if($request->merkinnat){
                $loydot->withMerkinnat($request->merkinnat);
            }

            // Tulkinnan mukaan
            if($request->tulkinta) {
                $loydot->withTulkinta($request->tulkinta);
            }

            // Asiasanan mukaan
            if($request->loydon_asiasanat) {
                $loydot->withAsiasana($request->loydon_asiasanat);
            }

            // Tutkimuksen nimen mukaan
            if($request->tutkimuksen_nimi) {
                $loydot->withTutkimuksenNimi($request->tutkimuksen_nimi);
            }

            // Yksikkötunnuksen mukaan
            if($request->yksikkotunnus) {
                $loydot->withYksikkotunnus($request->yksikkotunnus);
            }

            //Tutkimusalue
            if($request->loyto_alue) {
                $loydot->withTutkimusalue($request->loyto_alue);
            }

            // Tutkimuksen lyhenteen mukaan
            if($request->tutkimusLyhenne) {
                $loydot->withTutkimusLyhenne($request->tutkimusLyhenne);
            }

            /*
             *   Vaatii konservointia mukaan
             *  1 = ei, 2 = kyllä, 3 = ehkä, 4 = kaikki
             */
            if($request->vaatii_konservointia){
                if($request->vaatii_konservointia != 4){
                    $loydot->withVaatiiKonservointia($request->vaatii_konservointia);
                }
            }

            // Kuvauksen mukaan
            if($request->kuvaus) {
                $loydot->withKuvaus($request->kuvaus);
            }

            // Lisätietojen mukaan
            if($request->lisatiedot) {
                $loydot->withLisatiedot($request->lisatiedot);
            }

            // Ajoituksen alkuvuoden ja päätösvuoden ja ajanlaskun mukaan. Haku aikavälillä.
            if($request->alkuvuosi && $request->alkuvuosi_ajanlasku && $request->paatosvuosi && $request->paatosvuosi_ajanlasku) {
                $loydot->withAjoitusAikajakso($request->alkuvuosi, $request->alkuvuosi_ajanlasku, $request->paatosvuosi, $request->paatosvuosi_ajanlasku);
            }else{

                // Ajoituksen alkuvuoden ja ajanlaskun mukaan
                if($request->alkuvuosi && $request->alkuvuosi_ajanlasku) {
                    $loydot->withAlkuvuosiAjanlasku($request->alkuvuosi, $request->alkuvuosi_ajanlasku);
                }

                // Ajoituksen päätösvuoden ja ajanlaskun mukaan
                if($request->paatosvuosi && $request->paatosvuosi_ajanlasku) {
                    $loydot->withPaatosvuosiAjanlasku($request->paatosvuosi, $request->paatosvuosi_ajanlasku);
                }
            }

            // Löytöpaikan tarkenteen mukaan
            if($request->loytopaikan_tarkenne) {
                $loydot->WithLoytopaikanTarkenne($request->loytopaikan_tarkenne);
            }

            //  Kenttänumeron / Vanhan Työnumeron mukaan
            if($request->kenttanumero_vanha_tyonumero) {
                $loydot->WithKenttanumeroVanhaTyonumero($request->kenttanumero_vanha_tyonumero);
            }


            if($request->loyto_id) {
                $loydot->withLoytoId($request->loyto_id);
            }

            if($request->ark_tutkimus_id) {
                $loydot->withTutkimusId($request->ark_tutkimus_id);
            }

            if($request->ark_irtoloytotutkimusalue_id) {
                $loydot->withIrtoloytotutkimusAlueId($request->ark_irtoloytotutkimusalue_id);
            }

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($loydot);

            // ID:t listaan ennen rivien rajoitusta
            $kori_id_lista = Utils::getIdLista($loydot);

            // Rivimäärien rajoitus parametrien mukaan
            $loydot->withLimit($rivi, $riveja);

            // Sorttaus
            $loydot->withOrderBy($jarjestys_kentta, $jarjestys_suunta);

            // suorita query
            $loydot = $loydot->get();

            MipJson::initGeoJsonFeatureCollection(count($loydot), $total_rows);
            foreach ($loydot as $loyto) {
                // löydön lisäys feature listaan
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $loyto);
            }

            /*
             * Koritoiminnallisuus. Palautetaan löytöjen id:t listana.
             */
            //MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $kori);
            MipJson::setIdList($kori_id_lista);
            MipJson::addMessage(Lang::get('loyto.search_success'));

        } catch (Exception $e) {
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('loyto.search_failed'));
        }


        return MipJson::getJson();
    }

    /**
     * Löydön haku
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
        }
        else {
            try {

                // Hae löytö
                $loyto = Loyto::getSingle($id)->with( array(
                    'yksikko',
                    'yksikko.tutkimusalue',
                    'yksikko.tutkimusalue.tutkimus',
                    'materiaalikoodi',
                    'materiaalikoodi.ensisijaisetMateriaalit' => function($query) {
                        $query->orderBy('nimi_fi', 'ASC');
                    },
                    'ensisijainenMateriaali',
                    'materiaalit' => function($query) {
                        $query->orderBy('nimi_fi', 'ASC');
                    },
                    'loytotyyppi',
                    'loytotyyppiTarkenteet',
                    'merkinnat',
                    'loydonAsiasanat',
                    'loydonTila',
                    'tapahtumat.tapahtumaTyyppi',
                    'tapahtumat.luoja',
                    'tapahtumat.sailytystila',
                    'tekija',
                    'luoja',
                    'muokkaaja',
                    'luettelointinrohistoria' => function($query) {
                        $query->orderBy('luotu', 'DESC');
                    },
                    'luettelointinrohistoria.loytoTapahtuma',
                    'luettelointinrohistoria.luoja',
                    'tutkimusalue.tutkimus',//irtolöytö tai tarkastus
                    'sailytystila',
                    'kuntoraportit' => function($query) {
                        $query->orderBy('luotu', 'DESC');
                    }))->first();
                if(!$loyto) {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('loyto.search_not_found'));
                    return MipJson::getJson();
                }

                // Muodostetaan propparit
                $properties = clone($loyto);

                // Deserialisoidaan JSON string kannasta, poistetaan myös control characterit
                $properties->migraatiodata = preg_replace('/[[:cntrl:]]/', ' ', $properties->migraatiodata);
                $properties->migraatiodata = json_decode($properties->migraatiodata, true);

                MipJson::setGeoJsonFeature(null, $properties);

                MipJson::addMessage(Lang::get('loyto.search_success'));
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('loyto.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Löytöjen haku koriin
     */
    public function kori(Request $request) {

        if(!$request->kori_id_lista && !$request->tapahtuma_id){
            MipJson::addMessage(Lang::get('loyto.search_failed'));
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

            for($i = 0; $i<sizeof((array)$idLista); $i++) {
                $idLista[$i] = intval($idLista[$i]);
            }

            // Haetaan tapahtumista löytö idt:t
            if($request->tapahtuma_id && $request->luotu){
                $idLista = LoytoTapahtumat::haeKoriTapahtumat($request->tapahtuma_id, $request->luotu);
            }

            $loydot = Loyto::getAll()->with( array(
                'yksikko',
                'materiaalikoodi',
                'ensisijainenMateriaali',
                'materiaalit', // hakee välitaulun avulla muut löydön materiaalit
                'loytotyyppi',
                'loytotyyppiTarkenteet',
                'merkinnat',
                'loydonTila',
                'tapahtumat',
                'luoja',
                'muokkaaja',
                'luettelointinrohistoria' => function($query) {
                    $query->orderBy('luotu', 'DESC');
                },
                'luettelointinrohistoria.loytoTapahtuma',
                'luettelointinrohistoria.luoja'
            ));

            // Suodatus id listan mukaan
            $loydot->withLoytoIdLista($idLista);
            // Rivien määrän laskenta
            $total_rows = Utils::getCount($loydot);

            // ID:t listaan ennen rivien rajoitusta
            $kori_id_lista = Utils::getIdLista($loydot);

            // Rivimäärien rajoitus parametrien mukaan
            $loydot->withLimit($rivi, $riveja);

            // Sorttaus
            $loydot->withOrderBy($jarjestys_kentta, $jarjestys_suunta);

            // suorita query
            $loydot = $loydot->get();

            MipJson::initGeoJsonFeatureCollection(count($loydot), $total_rows);

            foreach ($loydot as $loyto) {
                //Tarkastetaan jokaiselle löydölle kuuluvat oikeudet
                $loyto->oikeudet = Kayttaja::getPermissionsByEntity('arkeologia', 'ark_loyto', $loyto->id);

                // löydön lisäys feature listaan
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $loyto);
            }

            /*
             * Koritoiminnallisuus. Palautetaan löytöjen id:t listana.
             */
            MipJson::setIdList($kori_id_lista);

            MipJson::addMessage(Lang::get('loyto.search_success'));
        }
        catch(QueryException $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('loyto.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Tallenna uusi löytö
     *
     */
    public function store(Request $request) {

        /*
         * Käyttöoikeus
         */
    	if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_loyto.luonti', $request->input('properties.ark_tutkimus_id'))) {
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
            //'ark_tutkimusalue_yksikko_id'	=> 'numeric',
            'materiaalikoodi.id'	        => 'numeric'
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

                $loyto = new Loyto($request->all()['properties']);

                // Juokseva alanumero per yksikkö ja materiaalikoodi
                //TAI jos ark_tutkimusalue_id on asetettu, tiedetään että löytö kuuluu suoraan tutkimusalueeseen
                //ja on siis irtolöytötyyppiseen tutkimukseen kuuluva löytö (=yksikköä ei ole)
                if($request->input('properties.ark_tutkimus_id') && $request->input('properties.ark_tutkimusalue_id')) {
                    $alanumero = $loyto::getAlanumero(null, null, $request->input('properties.ark_tutkimus_id'), $request->input('properties.ark_tutkimusalue_id'));
                } else {
                    $alanumero = $loyto::getAlanumero($request->input('properties.ark_tutkimusalue_yksikko_id'), $request->input('properties.materiaalikoodi.id'), null, null);
                }

                $loyto->alanumero = $alanumero;
                // Liitetään alanumero luettelointinumeron perään
                $loyto->luettelointinumero = $request->input('properties.luettelointinumero') . (string)$alanumero;

                // Valintalistojen id:t
                $loyto->ark_loyto_materiaalikoodi_id = $request->input('properties.materiaalikoodi.id');
                $loyto->ark_loyto_ensisijainen_materiaali_id = $request->input('properties.ensisijainen_materiaali.id');
                $loyto->ark_loyto_tyyppi_id = $request->input('properties.loytotyyppi.id');
                $loyto->loydon_tila_id = $request->input('properties.loydon_tila_id');
                $loyto->luoja = Auth::user()->id;
                $loyto->save();

                /*
                 * Tapahtumien päivitys.
                 * Luodaa luettelointinumeron lisäys-tapahtuma uuden löydön lisäämisessä.
                 */
                $tapahtuma = self::uusiTapahtuma($loyto);
                $tapahtuma->ark_loyto_tapahtuma_id = LoytoTapahtuma::LUETTELOITU;
                $tapahtuma->tapahtumapaivamaara = date('Y-m-d');
                $tapahtuma->luotu = \Carbon\Carbon::now();
                $tapahtuma->save();

            } catch(Exception $e) {
                app('log')->debug($e);
                DB::rollback();
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
                MipJson::addMessage(Lang::get('loyto.save_failed'));
//                return MipJson::getJson(); // Jääkö 1 transactio auki jos tässä returnataan?
            }

            // Onnistunut case
            DB::commit();

            // Haetaan tallennettu, jotta saadaan materiaalikoodi yms mukaan
            $loyto = Loyto::getSingle($loyto->id)->with( array(
                'yksikko',
                'yksikko.tutkimusalue',
                'yksikko.tutkimusalue.tutkimus',
                'materiaalikoodi',
                'materiaalikoodi.ensisijaisetMateriaalit' => function($query) {
                    $query->orderBy('nimi_fi', 'ASC');
                },
                'loydonTila',
                'tapahtumat.tapahtumaTyyppi',
                'tapahtumat.luoja',
                'tapahtumat.sailytystila',
                'luettelointinrohistoria' => function($query) {
                    $query->orderBy('luotu', 'ASC');
                },
                'luettelointinrohistoria.loytoTapahtuma',
                'luettelointinrohistoria.luoja',
                'tutkimusalue.tutkimus',
                'sailytystila'
            ))->first();

            MipJson::addMessage(Lang::get('loyto.save_success'));
            MipJson::setGeoJsonFeature(null, $loyto);
            MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('loyto.save_failed'),$e->getMessage()));
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
    	if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.muokkaus', $id)) {
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
            //'ark_tutkimusalue_yksikko_id'	=> 'numeric'
        ]);

        if ($validator->fails()) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            foreach($validator->errors()->all() as $error)
                MipJson::addMessage($error);
                MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        }
        else {
                $loyto = Loyto::find($id);

                if(!$loyto){
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('loyto.search_not_found'));
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                }
                else {
                    DB::beginTransaction();
                    Utils::setDBUser();

                        $loyto->fill($request->all()['properties']);

                        // Valintalistojen id:t

                        $loyto->ark_loyto_tyyppi_id = $request->input('properties.loytotyyppi.id');
                        $loyto->ark_loyto_ensisijainen_materiaali_id = $request->input('properties.ensisijainen_materiaali.id');
                        $loyto->loydon_tila_id = $request->input('properties.loydon_tila.id');
                        $loyto->vakituinen_sailytystila_id = $request->input('properties.sailytystila.id');

                        $loyto->ark_loyto_tyyppi_id = $request->input('properties.loytotyyppi.id');
                        $loyto->ark_loyto_ensisijainen_materiaali_id = $request->input('properties.ensisijainen_materiaali.id');
                        $loyto->loydon_tila_id = $request->input('properties.loydon_tila.id');

                        // Ei ylikirjoiteta tyhjää arvoa säilytystilatietoihin
                        if($request->input('properties.sailytystila.id') && strlen($request->input('properties.sailytystila.id')) > 0) {
                            $loyto->vakituinen_sailytystila_id = $request->input('properties.sailytystila.id');
                        }
                        if($request->input('properties.vakituinen_hyllypaikka') && strlen($request->input('properties.vakituinen_hyllypaikka'))>0) {
                            $loyto->vakituinen_hyllypaikka = $request->input('properties.vakituinen_hyllypaikka');
                        }
                        if($request->input('properties.tilapainen_sijainti') && strlen($request->input('properties.tilapainen_sijainti'))>0) {
                            $loyto->tilapainen_sijainti = $request->input('properties.tilapainen_sijainti');
                        }

                        // Monivalintojen päivitys
                        LoytoMateriaalit::paivita_muut_materiaalit($loyto->id, $request->input('properties.materiaalit'));
                        ArkLoytotyyppiTarkenteet::paivita_loydon_tarkenteet($loyto->id, $request->input('properties.loytotyyppi_tarkenteet'));
                        LoytoMerkinnat::paivita_merkinnat($loyto->id, $request->input('properties.merkinnat'));

                        // Asiasanojen päivitys. Kovakoodattu fi toistaiseksi
                        LoytoAsiasanat::paivita_asiasanat($loyto->id, $request->input('properties.asiasanat'), 'fi');

                        $author_field = Loyto::UPDATED_BY;
                        $loyto->$author_field = Auth::user()->id;

                        // Konservoinnin kuntoarvion tekijä
                        $loyto->tekija = $request->input('properties.tekija.id');


                        // Päivittämällä parentin aikaleimaa saadaan lapsitaulujen muutokset muutoshistoriaan helpommin
                        $loyto->touch();
                        $loyto->update();

                        // Luodaan löytö tapahtuma
                        if($request->input('properties.loytopaivamaara')){

                            self::luoLoytoTapahtuma($loyto, $request);
                        }

                        // Luodaan löydön tilan muutos tapahtuma
                        //TODO: Voidaanko käyttää frontilta välitettävää .properties._tilanmuutos -atribuuttia?
                        if($request->input('properties.tapahtumapaiva') && $request->input('properties.loydon_tila')){
                            // Tapahtumapäivästä muodostetaan aikaleima jota käytetään luonti-aikaleimana
                            $inputAikaleima = strtotime($request->input('properties.tapahtumapaiva'));
                            self::luoTilanMuutosTapahtuma($loyto, $inputAikaleima, $request);
                        }



                    // Päivitys onnistui
                    DB::commit();

                    // Hae löytö päivitetyin tiedoin
                    $loyto = Loyto::getSingle($id)->with( array(
                        'yksikko',
                        'yksikko.tutkimusalue',
                        'yksikko.tutkimusalue.tutkimus',
                        'materiaalikoodi',
                        'materiaalikoodi.ensisijaisetMateriaalit' => function($query) {
                            $query->orderBy('nimi_fi', 'ASC');
                        },
                        'ensisijainenMateriaali',
                        'materiaalit',
                        'loytotyyppi',
                        'loytotyyppiTarkenteet',
                        'merkinnat',
                        'loydonAsiasanat',
                        'loydonTila',
                        'tapahtumat.tapahtumaTyyppi',
                        'tapahtumat.luoja',
                        'tapahtumat.sailytystila',
                        'tekija',
                        'luoja',
                        'muokkaaja','luettelointinrohistoria' => function($query) {
                            $query->orderBy('luotu', 'DESC');
                        },
                        'luettelointinrohistoria.loytoTapahtuma',
                        'luettelointinrohistoria.luoja',
                        'tutkimusalue.tutkimus',//irtolöytö tai tarkastus
                        'sailytystila'))->first();

                        // Deserialisoidaan JSON string kannasta
                        $loyto->migraatiodata = json_decode($loyto->migraatiodata, true);

                    MipJson::addMessage(Lang::get('loyto.save_success'));
                    MipJson::setGeoJsonFeature(null, $loyto);
                    MipJson::setResponseStatus(Response::HTTP_OK);
                }

        }
        return MipJson::getJson();
    }

    /**
     * Korissa olevien löytöjen tilan muutos
     */
    public function koriTilamuutos(Request $request) {
        if(!$request->kori_id_lista && !$request->tila_id && !$request->tapahtumapaivamaara){
            MipJson::addMessage(Lang::get('loyto.status_change_failed'));
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            try {
                $loydot = Loyto::getAll();
                // Suodatus id listan mukaan
                $loydot->withLoytoIdLista($request->kori_id_lista);

                // suorita query
                $loydot = $loydot->get();

                // Tapahtumapäivästä muodostetaan aikaleima jota käytetään luonti-aikaleimana. Kaikille sama
                $inputAikaleima = \Carbon\Carbon::now()->toDateTimeString();//strtotime($request->input('properties.tapahtumapaiva'));

                foreach ($loydot as $loyto) {
                    /*
                     * Tarkastetaan, että löytöön on muokkausoikeus
                     * Jossei ole niin peruutetaan koko transaktio
                     * Tätä ei pitäisi normaalisti tapahtua, koska nämä filtteröidään jo uissa myös
                     */
                    $permissions = Kayttaja::getPermissionsByEntity('arkeologia', 'ark_loyto', $loyto->id);

                    if($permissions['muokkaus'] == false) {
                        DB::rollback();
                        MipJson::setGeoJsonFeature();
                        //Palautetaan virheilmoituksessa hieman tietoa mikä on probleema
                        MipJson::addMessage(Lang::get('loyto.no_modify_permission') . " - " . $loyto->luettelointinumero . " " . $loyto->yksikkotunnus);
                        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
                        return MipJson::getJson();
                    }
                    // Päivitetään löydön tila
                    $loyto->loydon_tila_id = $request->input('properties.loydon_tila.id');

                    //Päivitetään tarvittaessa löydön tapahtumaan liittyvät attribuutit,
                    //jotka voivat tilamuutoksen myötä muuttua:
                    //vakituinen sijainti, hyllypaikka, tilapäinen sijainti
                    if($request->input('properties.tilapainen_sijainti')) {
                        $loyto->tilapainen_sijainti = $request->input('properties.tilapainen_sijainti');
                    }
                    if($request->input('properties.sailytystila.id')) {
                        $loyto->vakituinen_sailytystila_id = $request->input('properties.sailytystila.id');
                    }
                    if($request->input('properties.vakituinen_hyllypaikka')) {
                        $loyto->vakituinen_hyllypaikka = $request->input('properties.vakituinen_hyllypaikka');
                    }

                    $author_field = Loyto::UPDATED_BY;
                    $loyto->$author_field = Auth::user()->id;
                    $loyto->update();

                    // Luodaan löydön tilan muutos tapahtuma
                    if($request->input('properties.loydon_tila.id') && $request->input('properties.tapahtumapaiva')){
                        self::luoTilanMuutosTapahtuma($loyto, $inputAikaleima, $request);
                    }
                }

                // Päivitys onnistui
                DB::commit();

                MipJson::addMessage(Lang::get('loyto.status_change_success'));
            }
            catch(QueryException $e) {
                DB::rollback();
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('loyto.status_change_failed'));
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
     * Hakee materiaalikoodin ja sen ensisijaiset materiaalit
     */
    public function haeEnsisijaisetMateriaalit($id) {

        try {

            $koodi = LoytoMateriaalikoodi::getSingle($id)->with( array(
                'ensisijaisetMateriaalit' => function($query) {
                    $query->orderBy('nimi_fi', 'ASC');
                }
            ))->first();

            // Muodostetaan propparit
            $properties = clone($koodi);

            MipJson::setGeoJsonFeature(null, $properties);

            MipJson::addMessage('Ensisijaisten haku onnistui');

        } catch (Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('loyto.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Luettelointinumeron vaihto. Luettelointinumeron yksikkö ja materiaalikoodi voidaan vaihtaa.
     * Uusi alanumero generoidaan aina.
     *
     * Säännöt:
     * Mikäli tutkimuksen löytöjen kokoelmatunnus on TMK, luettelointinumero muodostetaan kolmesta kentästä:
     * kokoelmatunnus + löytöjen päänumero (tutkimukselta)
     * materiaalikoodi + yksikkötunnus ilman yksikkötyypin kirjainta
     * juokseva alanumero (per materiaali + yksikkö)
     *
     */
    public function vaihdaLuettelointinumero(Request $request, $id) {

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.muokkaus', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'luettelointinumero'			=> 'required|string'
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
        $loyto = Loyto::find($id);

        if(!$loyto){
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('loyto.search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }
        DB::beginTransaction();
        Utils::setDBUser();

        try {
            $vanhaLuettelointiNro = $loyto->luettelointinumero;

            $loyto->fill($request->all()['properties']);

            // Pitää olla Turun museokeskus, jotta voidaan vaihtaa luettelointinumeroita
            $vaihto = false;

            $alkuosa = substr( $request->input('properties.luettelointinumero'), 0, 3 );
            if($alkuosa == 'TMK'){
                $vaihto = true;
            }

            // Muodostetaan TMK luettelointinumero§
            if($vaihto){
                // Kaivaustutkimuksen löytö esim: TMK12345:KA271:1
                if($request->input('properties.yksikko')){

                    // Juokseva alanumero per yksikkö ja materiaalikoodi
                    $alanumero = $loyto::getAlanumero($request->input('properties.ark_tutkimusalue_yksikko_id'), $request->input('properties.materiaalikoodi.id'), null, null);
                    $loyto->alanumero = $alanumero;

                    $loyto->luettelointinumero = $alkuosa . $loyto->yksikko->tutkimusalue->tutkimus->loyto_paanumero . ':'
                        . $request->input('properties.materiaalikoodi.koodi')
                        . $request->input('properties.yksikko.yksikon_numero') . ':'
                        . (string)$alanumero;

                } else if(!$request->input('properties.yksikko')){
                    // Irtolöytö tai tarkastustutkimus esim: TMK222:1 (alkuosa + löydön päänumero + alanumero)
                    if($request->input('properties.tutkimusalue.ark_tutkimus_id') && $request->input('properties.ark_tutkimusalue_id')) {
                        $alanumero = $loyto::getAlanumero(null, null, $request->input('properties.tutkimusalue.ark_tutkimus_id'), $request->input('properties.ark_tutkimusalue_id'));
                        $loyto->alanumero = $alanumero;

                        $loyto->luettelointinumero = $alkuosa . $request->input('properties.tutkimusalue.tutkimus.loyto_paanumero'). ':' . (string)$alanumero;
                    }
                }

                $author_field = Loyto::UPDATED_BY;
                $loyto->$author_field = Auth::user()->id;

                $loyto->update();

                // Luodaan luettelointinumeron muutos tapahtuma
                $tapahtuma = self::luoLuettelointinumeroTapahtuma($loyto, $request);

                self::luoLuettelointinumeroHistoria($loyto, $tapahtuma, $vanhaLuettelointiNro);

                // Päivitys onnistui
                DB::commit();

                // Hae löytö päivitetyin tiedoin
                $loyto = Loyto::getSingle($id)->with( array(
                    'yksikko',
                    'yksikko.tutkimusalue',
                    'yksikko.tutkimusalue.tutkimus',
                    'materiaalikoodi',
                    'materiaalikoodi.ensisijaisetMateriaalit' => function($query) {
                    $query->orderBy('nimi_fi', 'ASC');
                    },
                    'ensisijainenMateriaali',
                    'materiaalit' => function($query) {
                    $query->orderBy('nimi_fi', 'ASC');
                    },
                    'loytotyyppi',
                    'loytotyyppiTarkenteet',
                    'merkinnat',
                    'loydonAsiasanat',
                    'loydonTila',
                    'tapahtumat.tapahtumaTyyppi',
                    'tapahtumat.luoja',
                    'tapahtumat.sailytystila',
                    'tekija',
                    'luoja',
                    'muokkaaja',
                    'luettelointinrohistoria' => function($query) {
                    $query->orderBy('luotu', 'DESC');
                    },
                    'luettelointinrohistoria.loytoTapahtuma',
                    'luettelointinrohistoria.luoja',
                    'tutkimusalue.tutkimus',//irtolöytö tai tarkastus
                    'sailytystila'))->first();

                MipJson::addMessage(Lang::get('loyto.list_number_changed'));
                MipJson::setGeoJsonFeature(null, $loyto);
                MipJson::setResponseStatus(Response::HTTP_OK);

            } else {
                DB::rollback();
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('loyto.list_number_failed_no_tmk'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);

            }
        } catch(QueryException $qe) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage($qe->getMessage());
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
    }

    /**
     * Löytöpäivämäärän tapahtuma.
     * Luodaan uusi tai päivitetään jos muutettu.
     */
    private function luoLoytoTapahtuma($loyto, $request){

        $inputPvm = $request->input('properties.loytopaivamaara');
        $tapahtuma = LoytoTapahtumat::haeTapahtuma($loyto->id, LoytoTapahtuma::LOYDETTY);

        // Löytöpäivää muutettu
        if($tapahtuma && $inputPvm != $tapahtuma->tapahtumapaivamaara){
            $tapahtuma->tapahtumapaivamaara = $inputPvm;
            $tapahtuma->muokkaaja = Auth::user()->id;
            $tapahtuma->muokattu = \Carbon\Carbon::now();
            $tapahtuma->update();
        }else if(!$tapahtuma){
            // Löytöpäivä annettu ensimmäisen kerran
            $uusiTapahtuma = self::uusiTapahtuma($loyto);
            $uusiTapahtuma->ark_loyto_tapahtuma_id = LoytoTapahtuma::LOYDETTY;
            $uusiTapahtuma->tapahtumapaivamaara = $inputPvm;
            $uusiTapahtuma->luotu = \Carbon\Carbon::now();
            $uusiTapahtuma->save();
        }
    }

    private function luoLuettelointinumeroHistoria($loyto, $tapahtuma, $vanhaLuettelointiNro) {
        $lnroHistoria = new LoytoLuettelointinroHistoria();
        $lnroHistoria->ark_loyto_id = $loyto->id;
        $lnroHistoria->ark_loyto_tapahtumat_id = $tapahtuma->id;
        $lnroHistoria->luettelointinumero_vanha = $vanhaLuettelointiNro;
        $lnroHistoria->luettelointinumero_uusi = $loyto->luettelointinumero;
        $lnroHistoria->luoja = Auth::user()->id;
        $lnroHistoria->save();
    }

    /**
     * Löydön tila muutettu. Tila id ja tapahtuma id yhdistetään välitaululla LoytoTilaTapahtuma.
     */
    private function luoTilanMuutosTapahtuma($loyto, $inputAikaleima, $request){

        $luotu = \Carbon\Carbon::now()->toDateTimeString();//\Carbon\Carbon::createFromTimestamp($inputAikaleima);

        // Haetaan löydön tilalle tapahtuma id
        $tilaTapahtuma = LoytoTilaTapahtuma::haeLoydonTilaIdMukaan($request->input('properties.loydon_tila.id'));

        $uusiTapahtuma = self::uusiTapahtuma($loyto);

        $uusiTapahtuma->ark_loyto_tapahtuma_id = $tilaTapahtuma->ark_loyto_tapahtuma_id;

        //Tallennetaan aina jos on
        $uusiTapahtuma->tapahtumapaivamaara = $request->input('properties.tapahtumapaiva');
        $uusiTapahtuma->kuvaus = $request->input('properties.tilan_kuvaus');
        $uusiTapahtuma->luotu = $luotu;

        //Tallennetaan Lainassa, Näyttelyssä -tapahtumalle
        if($uusiTapahtuma->ark_loyto_tapahtuma_id == 8 || $uusiTapahtuma->ark_loyto_tapahtuma_id == 11) {
            $uusiTapahtuma->loppupvm = $request->input('properties.loppupvm');
        }

        //Tallennetaan Lainassa -tapahtumalle
        if($uusiTapahtuma->ark_loyto_tapahtuma_id == 8) {
            $uusiTapahtuma->lainaaja = $request->input('properties.lainaaja');
        }

        //Tallennetaan Näyttelyssä, Konservoinnissa -tapahtumalle
        if($tilaTapahtuma->ark_loyto_tapahtuma_id == 2 || $tilaTapahtuma->ark_loyto_tapahtuma_id == 11) {
            $uusiTapahtuma->tilapainen_sijainti = $request->input('properties.tilapainen_sijainti');
        }

        //Tallennetaan Kokoelmassa -tapahtumalle
        if($uusiTapahtuma->ark_loyto_tapahtuma_id == 9) {
            $uusiTapahtuma->vakituinen_sailytystila_id = $request->input('properties.sailytystila.id');
            $uusiTapahtuma->vakituinen_hyllypaikka = $request->input('properties.vakituinen_hyllypaikka');
        }

        // Log::debug("Uusi tapahtuma:");
        // Log::debug($uusiTapahtuma);

        $uusiTapahtuma->save();
    }

    /**
     * Luettelointinumeron vaihdon tapahtuma.
     */
    private function luoLuettelointinumeroTapahtuma($loyto, $request){

        $uusiTapahtuma = self::uusiTapahtuma($loyto);
        $uusiTapahtuma->ark_loyto_tapahtuma_id = LoytoTapahtuma::VAIHDETTU_LUETTELOINTINUMERO;
        $uusiTapahtuma->kuvaus = $request->input('properties.luettelointinumero_kuvaus');
        $uusiTapahtuma->tapahtumapaivamaara = \Carbon\Carbon::now();
        $uusiTapahtuma->luotu = \Carbon\Carbon::now();
        $uusiTapahtuma->save();
        return $uusiTapahtuma;
    }

    /*
     * Uuden tapahtuman alustus
     */
    private function uusiTapahtuma($loyto){
        $tapahtuma = new LoytoTapahtumat();
        $tapahtuma->ark_loyto_id = $loyto->id;
        $tapahtuma->luoja = Auth::user()->id;

        return $tapahtuma;
    }

    /**
     * Poista löyto. Asetetaan poistettu aikaleima ja poistaja, ei deletoida riviä.
     */
    public function destroy($id) {

        /*
         * Käyttöoikeus
         */
    	if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.poisto', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }
        $loyto = Loyto::find($id);

        if(!$loyto) {
            // return: not found
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('loyto.search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            // TODO: ennen $loyto poistoa on kaikki sen alla olevat oltava poistettu. konservoinnit jne..

            $author_field = Loyto::DELETED_BY;
            $when_field = Loyto::DELETED_AT;
            $loyto->$author_field = Auth::user()->id;
            $loyto->$when_field = \Carbon\Carbon::now();

            $loyto->save();

            DB::commit();

            MipJson::addMessage(Lang::get('loyto.delete_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $loyto->id));

        } catch(Exception $e) {
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('loyto.delete_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    public function kuntoraportit($id) {
        /*
         * Käyttöoikeus
         */
    	if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.muokkaus', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        try {

            // Hae löytö
            $loyto = Loyto::getSingle($id)->with( array(
                'kuntoraportit' => function($query) {
                    $query->orderBy('luotu', 'DESC');
                }))->first();
            $kuntoraportit = $loyto->kuntoraportit;
            if(!$loyto) {
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                MipJson::addMessage(Lang::get('loyto.search_not_found'));
                return MipJson::getJson();
            }
            MipJson::initGeoJsonFeatureCollection(count($kuntoraportit));
            foreach ($kuntoraportit as $kr) {
                // löydön lisäys feature listaan
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $kr);
            }

            MipJson::addMessage(Lang::get('loyto.search_success'));
        }
        catch(QueryException $e) {
            Log::debug($e);
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('loyto.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
    }
}
