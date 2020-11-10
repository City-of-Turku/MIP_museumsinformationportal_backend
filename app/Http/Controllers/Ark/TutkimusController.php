<?php

namespace App\Http\Controllers\Ark;

use App\Kayttaja;
use App\Utils;
use App\Ark\Kohde;
use App\Ark\KohdeTutkimus;
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
                'tutkimuskayttajat.kayttaja'
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
                $tutkimukset->where('nimi', 'ILIKE', "%".$request->tutkimuksen_nimi."%");
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

            $tutkimukset->withOrderBy($jarjestys_kentta, $jarjestys_suunta);

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($tutkimukset);

            // limit the results rows by given params
            $tutkimukset->withLimit($rivi, $riveja);

            // Execute the query
            $tutkimukset = $tutkimukset->get();

            // Tutkimukseen liitetyiltä käyttäjiltä kaivetaan organisaatiot.
            $i = 0;
            foreach ($tutkimukset as $tutkimus) {
                // Tutkimusten organisaatiot palautetaan erikseen hakuja varten
                $organisaatiot = [];
                foreach ($tutkimus->tutkimuskayttajat as $tk){
                    if($tk->kayttaja) {
                        // Jos sama organisaatio monella tutkimuksen käyttäjällä vain uniikit näytetään
                        if(!in_array($tk->kayttaja->organisaatio, $organisaatiot, true)){
                            array_push($organisaatiot, $tk->kayttaja->organisaatio);
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

            MipJson::initGeoJsonFeatureCollection(count($tutkimukset), $total_rows);

            foreach ($tutkimukset as $tutkimus) {
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $tutkimus);
            }
            MipJson::addMessage(Lang::get('tutkimus.search_success'));

        } catch (Exception $e) {
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
                        'kunnatkylat.kyla'))->first();
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
                    'tutkimuskayttajat.kayttaja',
                    'kunnatkylat.kunta',
                    'kunnatkylat.kyla',
                    'tarkastus.tarkastaja'
                ))->first();

                if(!$tutkimus) {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('tutkimus.search_not_found'));
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

                $tutkimusalueFeatures = array();
                foreach($properties->tutkimusalueet as $ta) {
                	if($ta->sijainti) {
                		$s = DB::select(DB::raw("select ST_AsText(ST_transform(ark_tutkimusalue.sijainti, ".Config::get('app.json_srid').")) from ark_tutkimusalue where ark_tutkimusalue.id = :id", ["id" => $ta->id]), [$ta->id]);
                		$splittedGeom = explode('(', $s[0]->st_astext);
                		$coordinates = $splittedGeom[2]; //Polygon muodossa koordinatit ovat 2. paikassa.

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
                		$geometry = array('type' => 'Polygon', 'coordinates' => $coordinates);
                		$feature  = array('type' => 'Feature', 'properties' => $ta, 'geometry' => $geometry);
                	} else {
                		$feature = array('type' => 'Feature', 'properties' => $ta, 'geometry' => null);
                	}

                	array_push($tutkimusalueFeatures, $feature);
                }
                //Poistetaan "plainit" vanhat tutkimusalueet ja lisätään uudet featuretyyppiset tutkimusalueet tähän paikkaan
                unset($properties->tutkimusalueet);
                $properties->tutkimusalueet=$tutkimusalueFeatures;

                MipJson::setGeoJsonFeature(null, $properties);

                MipJson::addMessage(Lang::get('tutkimus.search_success'));
            }
            catch(QueryException $e) {
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
}
