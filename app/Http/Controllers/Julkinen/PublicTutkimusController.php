<?php

namespace App\Http\Controllers\Julkinen;

use App\Utils;
use App\Ark\Kohde;
use App\Ark\KohdeTutkimus;
use App\Ark\Tutkimus;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Exception;
use Illuminate\Support\Facades\Log;

class PublicTutkimusController extends Controller
{
    /**
     * Tutkimusten haku
     *
     * @param Request $request
     */
    public function index(Request $request) {

         try {
            // Hakuparametrit
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $tutkimukset = Tutkimus::getAllPublicInformation();

            $tutkimukset->with( array(
                'tutkimuslaji'  => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
                    if ($jarjestys_kentta == "tyyppi") {
                        $query->orderBy("nimi_fi", $jarjestys_suunta);
                    }
                }
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

            MipJson::initGeoJsonFeatureCollection(count($tutkimukset), $total_rows);

            foreach ($tutkimukset as $tutkimus) {
                //Hide values from tutkimus->tutkimuslaji
                $tutkimus->tutkimuslaji->makeHidden(['luotu', 'luoja', 'muokattu', 'muokkaaja', 'poistettu', 'poistaja']);
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
                $tutkimus = Tutkimus::getSinglePublicInformation($id)->with( array(
                    'tutkimuslaji',
                    'tutkimusalueet' => function($query) {
                        $query->orderBy('nimi', 'ASC');
                    }
                ))->first();

                if(!$tutkimus) {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('tutkimus.search_not_found'));
                    return MipJson::getJson();
                }

                //Hide values from tutkimus->tutkimuslaji
                $tutkimus->tutkimuslaji->makeHidden(['luotu', 'luoja', 'muokattu', 'muokkaaja', 'poistettu', 'poistaja']);

                //Hide values from tutkimus->tutkimusalueet
                foreach($tutkimus->tutkimusalueet as $ta) {
                    $ta->makeHidden(['luotu', 'luoja', 'muokattu', 'muokkaaja', 'poistettu', 'poistaja', 'muistiinpanot', 'havainnot', 'yhteenveto']);
                }


                // Muodostetaan propparit
                $properties = clone($tutkimus);

                // Lisätään kohteelta palautettavat tiedot
                if( !empty($kohde) ){
                    $properties->setAttribute('kohde_id' ,$kohde->id);

                    if( !empty($kohde->kunnatkylat[0]->kunta) ){
                        $properties->setAttribute('kohde_kunta' ,$kohde->kunnatkylat[0]->kunta);
                        //Hide values from tutkimus->kohde_kunta
                        $properties->kohde_kunta->makeHidden(['luotu', 'luoja', 'muokattu', 'muokkaaja', 'poistettu', 'poistaja']);
                    }

                    if( !empty($kohde->kunnatkylat[0]->kyla) ){
                        $properties->setAttribute('kohde_kyla' ,$kohde->kunnatkylat[0]->kyla);
                        //Hide values from tutkimus->kohde_kyla
                        $properties->kohde_kyla->makeHidden(['luotu', 'luoja', 'muokattu', 'muokkaaja', 'poistettu', 'poistaja']);
                    }


                }


                /*
                 * Tehdään tutkimusalueesta feature, jotta se voidaan näyttää suoraan kartalla ilman frontissa tehtäviä vemputuksia.
                 * Tutkimusalueita ei yleensä ole monia, joten ei aiheuta ongelmia suorituskyvyllisesti.
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
