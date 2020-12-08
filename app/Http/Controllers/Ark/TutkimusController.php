<?php

namespace App\Http\Controllers\Ark;

use App\Ark\ArkKuva;
use App\Kayttaja;
use App\Utils;
use App\Ark\Kohde;
use App\Ark\KohdeTutkimus;
use App\Ark\Loyto;
use App\Ark\Nayte;
use App\Ark\Tutkimus;
use App\Ark\TutkimusKayttaja;
use App\Ark\TutkimusKiinteistoRakennus;
use App\Ark\TutkimusKuntaKyla;
use App\Ark\TutkimusMuutoshistoria;
use App\Ark\Tarkastus;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Tutkimusten käsittelyt
 */
class TutkimusController extends Controller
{
    /**
     * Tutkimusten haku
     *
     * @param Request $request
     */
    public function index(Request $request) {
        /*
         * Käyttöoikeustarkistukset Tutkimus.php luokassa
         */

        //Jos käyttäjän arkRooli on jätetty asettamatta, hänellä ei ole oikeutta
        //tutkimuksiin. Tämä pitäisi olla kaikilla oletuksena vähintään 'katselija'
    	if(Auth::user()->ark_rooli == '') {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

         try {
            // Hakuparametrit
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";
            $organisaatioHaku = (isset($request->organisaatio)) ? $request->organisaatio : null;

            //Jos käyttäjä on katselija, palauta ainoastaan
            //valmiit ja julkiset tutkimukset
            //JA ne joihin käyttäjä on liitetty
            $tutkimukset = Tutkimus::getAll();

            $tutkimukset->with( array(
                'tutkimuslaji'  => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
                    if ($jarjestys_kentta == "tyyppi") {
                        $query->orderBy("nimi_fi", $jarjestys_suunta);
                    }
                },
                'loytoKokoelmalaji',
                'raporttiKokoelmalaji',
                'karttaKokoelmalaji',
                'valokuvaKokoelmalaji',
                'nayteKokoelmalaji',
                'kiinteistotrakennukset',
                'tutkimuskayttajat'
            ));

            // Haku tutkimustyypillä
            if ($request->tutkimuslajit) {
                $tutkimukset = $tutkimukset->withTutkimuslajit($request->tutkimuslajit);
            }

            // Tutkimuksen lyhenteen mukaan
            if($request->tutkimusLyhenne) {
                $tutkimukset->where('tutkimuksen_lyhenne', 'ILIKE', $request->tutkimusLyhenne."%");
            }

            // Tutkimuksen nimen mukaan
            if($request->tutkimuksen_nimi) {
                $tutkimukset->where('ark_tutkimus.nimi', 'ILIKE', "%".$request->tutkimuksen_nimi."%");
            }

            // Löydön päänumeron mukaan
            if($request->loyto_paanumero) {
                $tutkimukset->where('loyto_paanumero', 'ILIKE', "%".$request->loyto_paanumero."%");
            }

            // Kenttätyön aikajaksolla
            if($request->kenttatyo_alkuvuosi && $request->kenttatyo_paatosvuosi) {
                $tutkimukset->withKenttatyoAikajakso($request->kenttatyo_alkuvuosi, $request->kenttatyo_paatosvuosi);
            }

            // Kenttätyön alkuvuodella
            if($request->kenttatyo_alkuvuosi && !$request->kenttatyo_paatosvuosi) {
                $tutkimukset->whereYear('ark_tutkimus.kenttatyo_alkupvm', '=', $request->kenttatyo_alkuvuosi);
            }

            // Kenttätyön päätösvuodella
            if(!$request->kenttatyo_alkuvuosi && $request->kenttatyo_paatosvuosi) {
                $tutkimukset->whereYear('ark_tutkimus.kenttatyo_loppupvm', '=', $request->kenttatyo_paatosvuosi);
            }

            // Kenttätyöjohtajan mukaan
            if($request->kenttatyojohtaja) {
                $tutkimukset->where('kenttatyojohtaja', 'ILIKE', "%".$request->kenttatyojohtaja."%");
            }

            // KL-koodi
            if($request->kl_koodi) {
                $tutkimukset->where('kl_koodi', '=', $request->kl_koodi);
            }

            // Tutkijan id
            if($request->tutkija) {
                $tutkimukset->withTutkija($request->tutkija);
            }

            // Näytteen päänumero
            if($request->nayte_paanumero) {
                $tutkimukset->where('nayte_paanumero', '=', $request->nayte_paanumero);
            }

            /*
             * Tutkimuksen tilat:
             * 1 = valmis
             * 2 = kesken
             * 3 = kaikki
             */
            if($request->tutkimus_valmis) {
                if($request->tutkimus_valmis == 1){
                    $tutkimukset->where('valmis', '=', 1);
                }else if($request->tutkimus_valmis == 2){
                    $tutkimukset->where('valmis', '=', 0);
                }
            }

            /*
             * Tutkimus julkinen:
             * 1 = kyllä
             * 2 = ei
             * 3 = kaikki
             */
            if($request->tutkimus_julkinen) {
                if($request->tutkimus_julkinen == 1){
                    $tutkimukset->where('julkinen', '=', 1);
                }else if($request->tutkimus_julkinen == 2){
                    $tutkimukset->where('julkinen', '=', 0);
                }
            }

            // Tutkimuksen nimen uniikki-tarkistus
            if ($request->tarkka && $request->nimi) {
                $tutkimukset = $tutkimukset->where('nimi', 'ILIKE', "$request->nimi");
            }

            // Tutkimuksen lyhenteen uniikki-tarkistus
            if ($request->tarkka && $request->tutkimuksen_lyhenne) {
                $tutkimukset = $tutkimukset->where('tutkimuksen_lyhenne', 'ILIKE', "$request->tutkimuksen_lyhenne");
            }

            // Päänumero oltava uniikki per kokoelmatunnus
            if ($request->paanumeroTyyppi && $request->paanumero && $request->kokoelmalaji) {
                if($request->paanumeroTyyppi == 'loyto'){
                    $tutkimukset = $tutkimukset->where('loyto_paanumero', '=', "$request->paanumero")
                                               ->where('ark_loyto_kokoelmalaji_id', '=', "$request->kokoelmalaji");
                }
                if($request->paanumeroTyyppi == 'nayte'){
                    $tutkimukset = $tutkimukset->where('nayte_paanumero', '=', "$request->paanumero")
                    ->where('ark_nayte_kokoelmalaji_id', '=', "$request->kokoelmalaji");
                }
                if($request->paanumeroTyyppi == 'digi'){
                    $tutkimukset = $tutkimukset->where('digikuva_paanumero', '=', "$request->paanumero")
                    ->where('ark_valokuva_kokoelmalaji_id', '=', "$request->kokoelmalaji");
                }
                if($request->paanumeroTyyppi == 'mustavalko'){
                    $tutkimukset = $tutkimukset->where('mustavalko_paanumero', '=', "$request->paanumero")
                    ->where('ark_valokuva_kokoelmalaji_id', '=', "$request->kokoelmalaji");
                }
                if($request->paanumeroTyyppi == 'dia'){
                    $tutkimukset = $tutkimukset->where('dia_paanumero', '=', "$request->paanumero")
                    ->where('ark_valokuva_kokoelmalaji_id', '=', "$request->kokoelmalaji");
                }
            }


            if($request->showTutkimusalueFeatures) {
                $tutkimukset = $tutkimukset->with('tutkimusalueet');
            }

            // Aluerajaus tarkoittaa bounding boxia
            if($request->aluerajaus) {
                $tutkimukset->withBoundingBox($request->aluerajaus);
            }
            // Polygonrajaus tarkoittaa vapaamuotoista piirrettyä geometriaa jonka mukaan rajataan
            if($request->polygonrajaus) {
                $tutkimukset->withPolygon($request->polygonrajaus);
            }

            $tutkimukset->withOrderBy($jarjestys_kentta, $jarjestys_suunta, $request->aluerajaus);

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($tutkimukset);

            // limit the results rows by given params
            $tutkimukset->withLimit($rivi, $riveja);

            // Execute the query
            // HUOM: ilman ark_tutkimus samannimiset kentät ylikirjoittuvat joinien mukana
            $tutkimukset = $tutkimukset->get('ark_tutkimus.*');

            // Tutkimukseen liitetyiltä käyttäjiltä kaivetaan organisaatiot.
            $i = 0;
            foreach ($tutkimukset as $tutkimus) {
                // Tutkimusten organisaatiot palautetaan erikseen hakuja varten
                $organisaatiot = [];
                foreach ($tutkimus->tutkimuskayttajat as $tk){
                    if($tk->organisaatio) {
                        // Jos sama organisaatio monella tutkimuksen käyttäjällä vain uniikit näytetään
                        if(!in_array($tk->organisaatio, $organisaatiot, true)){
                            array_push($organisaatiot, $tk->organisaatio);
                        }
                    }
                }

                $tutkimus->organisaatiot = $organisaatiot;

                // Organisaation mukainen suodatus
                if($organisaatioHaku){
                    if(!in_array($organisaatioHaku, $tutkimus->organisaatiot)){
                        // Poistetaan koko tutkimus, jos ei löydy
                        unset($tutkimukset[$i]);
                    }
                }
                $i++;
            }

            // Jos pyynnössä on mukana tutkimusalueiden näyttäminen, liitetään ne mukaan (geojson-muodossa)
            if($request->showTutkimusalueFeatures) {
                foreach ($tutkimukset as $t) {
                   $this->replaceTutkimusalueetWithFeatures($t, true);
                }
            }

            MipJson::initGeoJsonFeatureCollection(count($tutkimukset), $total_rows);

            foreach ($tutkimukset as $tutkimus) {
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $tutkimus);
            }
            MipJson::addMessage(Lang::get('tutkimus.search_success'));

         } catch (Exception $e) {
             Log::error($e);
             MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
             MipJson::addMessage(Lang::get('tutkimus.search_failed'));
         }

        return MipJson::getJson();
    }

    /**
     * Haetaan tutkimus
     */
    public function show($id) {

    	if(!is_numeric($id)) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    		return MipJson::getJson();
    	}

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_tutkimus.katselu', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

            try {
                // Hae välitaulusta mahdollinen kohteen id
                $kohdeTutkimus = KohdeTutkimus::select('ark_kohde_tutkimus.*')->where('ark_tutkimus_id', '=', $id)->first();

                // Hae kohde
                if( !empty($kohdeTutkimus) ){
                    $kohde = Kohde::getSingle($kohdeTutkimus->ark_kohde_id)->with(array (
                        'kunnatkylat.kunta',
                        'kunnatkylat.kyla',
                        'kiinteistotrakennukset'))->first();
                }

                // Hae tutkimus
                $tutkimus = Tutkimus::getSingle($id)->with( array(
                    'tutkimuslaji',
                    'loytoKokoelmalaji',
                    'raporttiKokoelmalaji',
                    'karttaKokoelmalaji',
                    'valokuvaKokoelmalaji',
                    'nayteKokoelmalaji',
                    'tutkimusalueet' => function($query) {
                        $query->orderBy('nimi', 'ASC');
                    },
                    'kiinteistotrakennukset',
                    'kiinteistotrakennukset.osoitteet',
                    'luoja',
                    'muokkaaja',
                    'tutkimuskayttajat',
                    'kunnatkylat.kunta',
                    'kunnatkylat.kyla',
                    'tarkastus.tarkastaja',
                    'inventointiKohteet.sijainnit',
                    'inventointiKohteet.laji',
                    'inventointiKohteet.tyypit.tyyppi'
                ))->first();

                if(!$tutkimus) {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('tutkimus.search_not_found'));
                }

                // Inventointi-tutkimukselle palautetaan tutkimuskäyttäjälle asetettu organisaatio sekä inventoinnin kohteet.
                if($tutkimus && $tutkimus->ark_tutkimuslaji_id == 5) {
                    foreach ($tutkimus->tutkimuskayttajat as $tk){
                        $tkayttaja = TutkimusKayttaja::getSingleByTutkimusIdAndUserId($tutkimus->id, $tk->id)->first();
                        if(!empty($tkayttaja->organisaatio)){
                            $tk->inv_tutkija_organisaatio = $tkayttaja->organisaatio;
                        }
                    }

                    // Palautetaan vain tarvittavat kohteen tiedot
                    $invKohteet = array();
                    foreach ($tutkimus->inventointiKohteet as $invKohde){
                        //Log::debug(print_r($invKohde->nimi, true));
                        // Täsmennetään palautettavia tietoja vähän
                        $lajiNimi = $invKohde->laji->nimi_fi;
                        unset($invKohde->laji);
                        $invKohde->laji = $lajiNimi;

                        if(count($invKohde->tyypit) == 0){
                            $invKohde->tyyppi = 'Ei määritelty';
                        } else {
                            $invKohde->tyyppi = $invKohde->tyypit[0]->tyyppi->nimi_fi;
                        }

                        // Haetaan mukaan inventoinnin tehnyt käyttäjä
                        if($invKohde->pivot && $invKohde->pivot->inventoija_id) {
                            $inventoija = Kayttaja::where('id', '=', $invKohde->pivot->inventoija_id)->first();
                            $inventoijaNimi = '';
                            if($inventoija && $inventoija->etunimi && $inventoija->sukunimi) {
                                $inventoijaNimi = $inventoija->sukunimi . " " . $inventoija->etunimi;
                            }
                            $invKohde->pivot->inventoija = $inventoijaNimi;
                        }

                        // Tehdään UI yhdenmukainen properties formaatti
                        $kohdeReply = array(
                            'properties' => $invKohde
                        );
                        $invKohteet[] = $kohdeReply;
                    }
                    if(!empty($invKohteet)){
                        unset($tutkimus->inventointiKohteet);
                        $tutkimus->inventointiKohteet = $invKohteet;
                    }
                }

                // Muodostetaan propparit
                $properties = clone($tutkimus);

                // Lisätään kohteelta palautettavat tiedot
                if( !empty($kohde) ){
                    $properties->setAttribute('kohde' ,$kohde);


                    if( !empty($kohde->kunnatkylat[0]->kunta) ){
                        $properties->setAttribute('kohde_kunta' ,$kohde->kunnatkylat[0]->kunta);
                    }

                    if( !empty($kohde->kunnatkylat[0]->kyla) ){
                        $properties->setAttribute('kohde_kyla' ,$kohde->kunnatkylat[0]->kyla);
                    }

                    //TODO katuosoite, nro ja postinumero.

                }

                //Lisätään kuvat
                //$properties->kuvat = $tutkimus->kuvat($tutkimus->id);

                /*
                 * Tehdään tutkimusalueesta feature, jotta se voidaan näyttää suoraan kartalla ilman frontissa tehtäviä vemputuksia.
                 * Tutkimusalueita ei yleensä ole monia, joten ei aiheuta ongelmia suorituskyvyllisesti.
                 * TODO: Siirretään parempaan paikkaan kun on aikaa.
                 */
                $this->replaceTutkimusalueetWithFeatures($properties);

                MipJson::setGeoJsonFeature(null, $properties);

                MipJson::addMessage(Lang::get('tutkimus.search_success'));
            }
             catch(QueryException $e) {
                 Log::error($e);
                 MipJson::setGeoJsonFeature();
                 MipJson::addMessage(Lang::get('tutkimus.search_failed'));
                 MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
             }

        return MipJson::getJson();
    }

    /**
     * Tallenna tutkimus
     *
     */
    public function store(Request $request) {

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_tutkimus.luonti')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'tutkimuslaji.id'	    => 'required|numeric',
            'nimi'					=> 'required|string',
            'postinumero'		    => 'numeric',
            'rahoittaja'		    => 'string|max:1000',
            'tiivistelma'		    => 'string|max:1000'
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

                $tutkimus = new Tutkimus($request->all()['properties']);

                // Valintalistojen id:t
                $tutkimus->ark_tutkimuslaji_id = $request->input('properties.tutkimuslaji.id');
                $tutkimus->ark_loyto_kokoelmalaji_id = $request->input('properties.loyto_kokoelmalaji.id');
                $tutkimus->ark_raportti_kokoelmalaji_id = $request->input('properties.raportti_kokoelmalaji.id');
                $tutkimus->ark_kartta_kokoelmalaji_id = $request->input('properties.kartta_kokoelmalaji.id');
                $tutkimus->ark_valokuva_kokoelmalaji_id = $request->input('properties.valokuva_kokoelmalaji.id');
                $tutkimus->ark_nayte_kokoelmalaji_id = $request->input('properties.nayte_kokoelmalaji.id');

                $tutkimus->luoja = Auth::user()->id;
                $tutkimus->save();

                /*
                 * Kohteeseen ollaan liittämässä tutkimusta, jos kohde_id tai itse kohde löytyy.
                 * Lisätään välitauluun id tiedot
                 */
                $kohde_id = null;

                if($request->input('properties.kohde_id')){
                    $kohde_id = $request->input('properties.kohde_id');
                }elseif($request->input('properties.kohde.properties.id')){
                    $kohde_id = $request->input('properties.kohde.properties.id');
                }

                if(!is_null($kohde_id)){

                    $kohdeTutkimus = new KohdeTutkimus(['ark_kohde_id' => $kohde_id, 'ark_tutkimus_id' => $tutkimus->id]);
                    $kohdeTutkimus->save();
                }
                // Kiinteistöjen tiedot mm. osoitteet
                TutkimusKiinteistoRakennus::paivita_tutkimus_kiinteistorakennustiedot($tutkimus->id, $request->input('properties.kiinteistotrakennukset'));
                TutkimusKuntaKyla::paivita_tutkimus_kunnatkylat($tutkimus->id, $request->input('properties.kunnatkylat'));

                /*
                 * Jos kyseessä on tarkastustutkimus, tallennetaan tarkastuksen kentät.
                 */
                $tark = $request->input('properties.tarkastus');

                 if($tark){
                    $tarkastus = new Tarkastus($tark);
                    $tarkastus->ark_tutkimus_id = $tutkimus->id;
                    $tarkastus->tarkastaja = $request->input('properties.tarkastus.tarkastaja.id');
                    $tarkastus->luoja = Auth::user()->id;
                    $tarkastus->save();
                }

                // Onnistunut case
                DB::commit();

                MipJson::addMessage(Lang::get('tutkimus.save_success'));
                MipJson::setGeoJsonFeature(null, array("id" => $tutkimus->id));
                MipJson::setResponseStatus(Response::HTTP_OK);

            } catch(Exception $e) {
                DB::rollback();
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
                MipJson::addMessage(Lang::get('tutkimus.save_failed'));
            }

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('tutkimus.save_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Tutkimuksen päivitys
     */
    public function update(Request $request, $id) {

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_tutkimus.muokkaus')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'tutkimuslaji.id'	    => 'required|numeric',
            'nimi'					=> 'required|string',
            'postinumero'		    => 'numeric',
            'rahoittaja'		    => 'nullable|string|max:1000',
            'tiivistelma'		    => 'nullable|string|max:1000'
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
                $tutkimus = Tutkimus::find($id);

                if(!$tutkimus){
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('tutkimus.search_not_found'));
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                }
                else {

                    // Otetaan talteen alkuperäinen valmis ja julkinen arvot ja jos nämä
                    // ovat muuttuneet, päivitetään myös löytöjen aikaleimat
                    $origJulkinen = $tutkimus->julkinen;
                    $origValmis = $tutkimus->valmis;

                    DB::beginTransaction();
                    Utils::setDBUser();

                    try {
                        $tutkimus->fill($request->all()['properties']);

                        // Valintalistojen id:t
                        $tutkimus->ark_tutkimuslaji_id = $request->input('properties.tutkimuslaji.id');
                        $tutkimus->ark_loyto_kokoelmalaji_id = $request->input('properties.loyto_kokoelmalaji.id');
                        $tutkimus->ark_raportti_kokoelmalaji_id = $request->input('properties.raportti_kokoelmalaji.id');
                        $tutkimus->ark_kartta_kokoelmalaji_id = $request->input('properties.kartta_kokoelmalaji.id');
                        $tutkimus->ark_valokuva_kokoelmalaji_id = $request->input('properties.valokuva_kokoelmalaji.id');
                        $tutkimus->ark_nayte_kokoelmalaji_id = $request->input('properties.nayte_kokoelmalaji.id');

                        // Kiinteistöjen tiedot mm. osoitteet
                        TutkimusKiinteistoRakennus::paivita_tutkimus_kiinteistorakennustiedot($tutkimus->id, $request->input('properties.kiinteistotrakennukset'));
                        TutkimusKuntaKyla::paivita_tutkimus_kunnatkylat($tutkimus->id, $request->input('properties.kunnatkylat'));

                        /*
                         * Tutkimuksen kohteen linkitys
                         * Lisätään/poistetaan välitaulun id tiedot
                         */
                        $kohde_id = null;
                        if($request->input('properties.kohde.id')){
                            $kohde_id = $request->input('properties.kohde.id');
                        }elseif($request->input('properties.kohde.properties.id')){
                            $kohde_id = $request->input('properties.kohde.properties.id');
                        }

                        KohdeTutkimus::paivita_kohde_tutkimus($tutkimus->id, $kohde_id);

                        $author_field = Tutkimus::UPDATED_BY;
                        $tutkimus->$author_field = Auth::user()->id;

                        // Päivittämällä parentin aikaleimaa saadaan lapsitaulujen muutokset muutoshistoriaan helpommin
                        $tutkimus->touch();
                        $tutkimus->update();

                        /*
                         * Jos kyseessä on tarkastustutkimus, tallennetaan tarkastuksen kentät.
                         */
                        if($tutkimus->tarkastus){
                            $tarkastus = $tutkimus->tarkastus;

                            $tarkInput = $request->input('properties.tarkastus');

                            $tarkastus->fill($tarkInput);
                            $tarkastus->tarkastaja = $request->input('properties.tarkastus.tarkastaja.id');
                            $tarkastus->$author_field = Auth::user()->id;
                            $tarkastus->update();
                        }

                        // Päivitetään löytöjen aikaleima, jotta esimerkiksi Finnan haravointi huomaa muuttuneet
                        if($origJulkinen != $tutkimus->julkinen || $origValmis != $tutkimus->valmis) {
                            $loydot = Loyto::getAll()->withTutkimusId($tutkimus->id);
                            $loydot->update(['muokattu' => \Carbon\Carbon::now(), 'muokkaaja' => -1]);
                        }
                    } catch(Exception $e) {
                        DB::rollback();
                        throw $e;
                    }

                    // Päivitys onnistui
                    DB::commit();

                    MipJson::addMessage(Lang::get('tutkimus.save_success'));
                    MipJson::setGeoJsonFeature(null, array("id" => $tutkimus->id));
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
     * Poista tutkimus. Asetetaan poistettu aikaleima ja poistaja, ei deletoida riviä.
     */
    public function destroy($id) {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_tutkimus.poisto')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }
        $tutkimus = Tutkimus::find($id);

        if(!$tutkimus) {
            // return: not found
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('tutkimus.search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            $author_field = Tutkimus::DELETED_BY;
            $when_field = Tutkimus::DELETED_AT;
            $tutkimus->$author_field = Auth::user()->id;
            $tutkimus->$when_field = \Carbon\Carbon::now();

            $tutkimus->save();


            DB::commit();

            MipJson::addMessage(Lang::get('tutkimus.delete_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $tutkimus->id));

        } catch(Exception $e) {
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('tutkimus.delete_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    public function historia($id) {

        /*
         * Käyttöoikeus
         * TODO: Millaiset oikeudet muutoshistorian katseluun halutaan?
         * Nyt muutoshistorian näkevät kaikki jotka ovat tutkimuksen käyttäjiä SEKÄ kun tutkimus on valmis, muutoshistorian näkevät kaikki.
         * Muutetaan mahdollisesti niin, että muutoshistorian näkee tutkimuksen valmistuttua ainoastaan tutkimukseen kuuluvat käyttäjät(?) ja tutkijat ja pääkäyttäjät?
         */
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_tutkimus.katselu', $id)) {
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

        return TutkimusMuutoshistoria::getById($id);

    }

    public function muokkaaKayttajia($id, Request $request) {
        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_tutkimus.muokkaus')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            try {
                $tutkimus = Tutkimus::getSingle($id)->first();

                //Poistetaan poistettavat käyttäjät
                for($i = 0; $i<sizeof($request->input('poistettavat')); $i++) {
                    $tk = TutkimusKayttaja::getSingleByTutkimusIdAndUserId($tutkimus->id, $request->input('poistettavat')[$i])->first();
                    $author_field = TutkimusKayttaja::DELETED_BY;
                    $when_field = TutkimusKayttaja::DELETED_AT;
                    $tk->$author_field = Auth::user()->id;
                    $tk->$when_field = \Carbon\Carbon::now();

                    $tk->save();
                }

                //Lisätään lisättävät käyttäjät
                for($i = 0; $i<sizeof($request->input('lisattavat')); $i++) {
                    $tk = new TutkimusKayttaja();
                    $tk->kayttaja_id = $request->input('lisattavat')[$i]['id'];
                    $tk->ark_tutkimus_id = $id;

                    // Inventointi-tutkimuksella annetaan erikseen organisaatio
                    if($tutkimus->ark_tutkimuslaji_id == 5) {
                        $tk->organisaatio = $request->input('lisattavat')[$i]['inv_tutkija_organisaatio'];
                    }

                    $tk->luoja = Auth::user()->id;
                    $tk->save();
                }
            } catch(Exception $e) {
                DB::rollback();
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
                MipJson::addMessage(Lang::get('tutkimus.save_failed'));
            }

            // Onnistunut case
            DB::commit();

            MipJson::addMessage(Lang::get('tutkimus.save_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $id));
            MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('tutkimus.save_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }


    /*
     * Hakee käyttäjän aktiiviset inventointitutkimukset. ark_tutkimuslaji_id = 5
     * Aktiivinen: tutkimusaika on tällä hetkellä, valmis = false
     */
    public function getAktiivisetInventointitutkimukset() {
        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_kohde.katselu')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $kayttaja = Auth::user();

        // Haetaan tutkimukset joissa käyttäjä on liitettynä
        $tutkimukset = Tutkimus::getAktiivisetInventointitutkimukset($kayttaja->id);

        //Set the amount of selected rows
        $total_rows = count($tutkimukset);
        MipJson::initGeoJsonFeatureCollection($total_rows, $total_rows);

        foreach ($tutkimukset as $tutkimus) {
            MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $tutkimus);
        }

        return MipJson::getJson ();
    }

    /*
     * Hakee tutkimukseen liittyvien löytöjen ja näytteiden lukumäärät
     * ja digikuvien ensimmäisen ja viimeisen luettelointinumeron.
     * Tiloja Poistettu löytöluettelosta ja poistettu kokoelmasta ei huomioida.
     */
    public function lukumaarat($id) {
        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_tutkimus.katselu', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }
        try {
            $tutkimus = Tutkimus::getSingle($id)->select('id', 'nimi')->first();

            $loydotCount = Loyto::getAll()->where('loydon_tila_id', '!=', 9)
                ->where('loydon_tila_id', '!=', 5)->withTutkimusId($id)->count();

            $naytteetCount = Nayte::getAll()->where('ark_nayte_tila_id', '!=', 3)
                ->where('ark_nayte_tila_id', '!=', 7)->withTutkimusId($id)->count();

            $digikuvatAlku = ArkKuva::getAll()->withTutkimusId($id)->whereNotNull('luettelointinumero')->orderBy('ark_kuva.id', 'asc')->select('ark_kuva.id', 'luettelointinumero')->first();
            $digikuvatLoppu = ArkKuva::getAll()->withTutkimusId($id)->whereNotNull('luettelointinumero')->orderBy('ark_kuva.id', 'desc')->select('ark_kuva.id', 'luettelointinumero')->first();

            $tutkimus->loydotCount = $loydotCount;
            $tutkimus->naytteetCount = $naytteetCount;
            $tutkimus->digikuvatAlku = $digikuvatAlku->luettelointinumero;
            $tutkimus->digikuvatLoppu = $digikuvatLoppu->luettelointinumero;

            // Muodostetaan propparit
            $properties = clone($tutkimus);

            MipJson::setGeoJsonFeature(null, $properties);
            MipJson::addMessage(Lang::get('tutkimus.search_success'));

        } catch(Exception $e) {
            Log::debug($e);
            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('tutkimus.search_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson ();
    }

    private function replaceTutkimusalueetWithFeatures($properties, $showOnlyWithCoordinates=false) {
        $tutkimusalueFeatures = array();
        foreach($properties->tutkimusalueet as $ta) {
            // Joko piste-sijanti tai alue
            if($ta->sijainti || $ta->sijainti_piste) {
                $s = DB::select(DB::raw("select ST_AsText(ST_transform(ark_tutkimusalue.sijainti_piste, ".Config::get('app.json_srid').")) from ark_tutkimusalue where ark_tutkimusalue.id = :id", ["id" => $ta->id]), [$ta->id]);
                $splittedGeom = explode('(', $s[0]->st_astext);

                $sAlue = DB::select(DB::raw("select ST_AsText(ST_transform(ark_tutkimusalue.sijainti, ".Config::get('app.json_srid').")) from ark_tutkimusalue where ark_tutkimusalue.id = :id", ["id" => $ta->id]), [$ta->id]);
                $splittedArea = explode('(', $sAlue[0]->st_astext);

                if('POINT' == $splittedGeom[0]){
                    $coordinates = $splittedGeom[1]; // Point positio

                    //Poistetaan lopusta ) merkki
                    if(substr($coordinates, -1) == ')') {
                        $coordinates = rtrim($coordinates, ')');
                    }
                    //Erotellaan koordinaattiparit arrayksi
                    $coordinates = explode(',', $coordinates);
                    $pairs = [];
                    foreach($coordinates as $coord) {
                        $coord = explode(' ', $coord);
                        array_push($pairs, (float)$coord[0]);
                        array_push($pairs, (float)$coord[1]);
                    }

                    $coordinates = $pairs;
                    // Asetetaan tutkimuksen vaadittavat tiedot kartalla näyttämistä varten
                    $ta->tutkimus = array('nimi' => $properties->nimi, 'tyyppi' => $properties->tutkimuslaji);
                    $geometry = array('type' => 'Point', 'coordinates' => $coordinates);
                } else {
                    if(empty($splittedArea[2])){
                        Log::error('Virheellinen polygon, ta id: ' . $ta->id);
                        $coordinates = null;
                        $geometry = null;
                    } else{
                        $coordinates = $splittedArea[2]; //Polygon muodossa koordinatit ovat 2. paikassa.

                        //Poistetaan lopusta ) merkki
                        if(substr($coordinates, -1) == ')') {
                            $coordinates = rtrim($coordinates, ')');
                        }

                        //Erotellaan koordinaattiparit arrayksi
                        $coordinates = explode(',', $coordinates);
                        $pairs = [];

                        //Jokaiselle parille muutetaan koordinaatit arrayksi ja muutetaan string numeroksi
                        foreach($coordinates as $coord) {
                            $coord = explode(' ', $coord);
                            $coord[0] = (float)$coord[0];
                            $coord[1] = (float)$coord[1];

                            array_push($pairs, $coord);
                        }

                        $coordinates = [$pairs];

                        // Asetetaan tutkimuksen vaadittavat tiedot kartalla näyttämistä varten
                        $ta->tutkimus = array('nimi' => $properties->nimi, 'tyyppi' => $properties->tutkimuslaji);
                        $geometry = array('type' => 'Polygon', 'coordinates' => $coordinates);
                    }
                }

                $feature  = array('type' => 'Feature', 'properties' => $ta, 'geometry' => $geometry);

                array_push($tutkimusalueFeatures, $feature);
            } else {
                if($showOnlyWithCoordinates == false) {
                    $feature = array('type' => 'Feature', 'properties' => $ta, 'geometry' => null);
                    array_push($tutkimusalueFeatures, $feature);
                }
            }
        }
        //Poistetaan "plainit" vanhat tutkimusalueet ja lisätään uudet featuretyyppiset tutkimusalueet tähän paikkaan
        unset($properties->tutkimusalueet);
        $properties->tutkimusalueet=$tutkimusalueFeatures;
    }
}
