<?php

namespace App\Http\Controllers\Julkinen;

use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Utils;
use App\Ark\Loyto;

class PublicLoytoController extends Controller
{

    /**
     * Löytöjen haku
     */
    public function index(Request $request) {

        try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 1000;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "luettelointinumero";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $loydot = Loyto::getAllPublicInformation()->with( array(
                'yksikko',
                'yksikko.tutkimusalue.tutkimus.loytoKokoelmalaji',
                'materiaalikoodi',
                'ensisijainenMateriaali',
                'materiaalit', // hakee välitaulun avulla muut löydön materiaalit
                'loytotyyppi',
                'loytotyyppiTarkenteet',
                'merkinnat',
                'loydonAsiasanat'
            ));

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

            MipJson::addMessage(Lang::get('loyto.search_success'));

        } catch (Exception $e) {
            Log::error($e);
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('loyto.search_failed'));
            MipJson::addMessage($e->getMessage());
        }

        return MipJson::getJson();
    }


    /**
     * Löydön haku
     */
    public function show($id) {

        if(!is_numeric($id)) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        }
        else {
            try {

                // Hae löytö
                $loyto = Loyto::getSinglePublicInformation($id)->with( array(
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
                    'tutkimusalue.tutkimus'//irtolöytö tai tarkastus
                    ))->first();
                if(!$loyto) {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('loyto.search_not_found'));
                    return MipJson::getJson();
                }

                //Hide values from loyto->tutkimusalue
                $loyto->tutkimusalue->makeHidden(['muistiinpanot', 'havainnot', 'yhteenveto']);

                //Set tutkimus.nimi to tutkimus_nimi
                $loyto->tutkimusalue->tutkimus_nimi = $loyto->tutkimusalue->tutkimus->nimi;

                //Hide tutkimusalue->tutkimus
                $loyto->tutkimusalue->makeHidden(['tutkimus']);

                // Muodostetaan propparit
                $properties = clone($loyto);

                MipJson::setGeoJsonFeature(null, $properties);

                MipJson::addMessage(Lang::get('loyto.search_success'));
            }
            catch(QueryException $e) {
                Log::error($e);
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('loyto.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }
}
