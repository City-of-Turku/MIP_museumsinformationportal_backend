<?php

namespace App\Http\Controllers\Julkinen;

use App\Utils;
use App\Ark\Tutkimusalue;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Exception;

class PublicTutkimusalueController extends Controller
{
    /**
     * Tutkimusalueiden haku
     *
     * @param Request $request
     */
    public function index(Request $request) {


        try {
            // Hakuparametrit
            //$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            //$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";
            $tutkimusalueet = Tutkimusalue::getAllPublicInformation();

            if($request->input("ark_tutkimus_id")) {
                $tutkimusalueet->withTutkimus($request->input("ark_tutkimus_id"));
            }
            $tutkimusalueet->withOrderBy($tutkimusalueet, $jarjestys_kentta, $jarjestys_suunta);

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($tutkimusalueet);

            // limit the results rows by given params
            //$tutkimukset->withLimit($rivi, $riveja);

            // Tutkimusalueen nimen uniikki-tarkistus, käytetään myös tutkimusalueen löytymisen tarkistukseen luettelointinumeron vaihdossa.
            if ($request->uniikki_nimi && $request->tutkimusalue_nimi && $request->ark_tutkimus_id) {
                $tutkimusalueet->withTutkimus($request->ark_tutkimus_id);
                $tutkimusalueet = $tutkimusalueet->where('nimi', 'ILIKE', "$request->tutkimusalue_nimi");
            }

            // Execute the query
            $tutkimusalueet = $tutkimusalueet->get();


            MipJson::initGeoJsonFeatureCollection(count($tutkimusalueet), $total_rows);

            foreach ($tutkimusalueet as $ta) {

                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $ta);

            }
            MipJson::addMessage(Lang::get('tutkimusalue.search_success'));

        } catch (Exception $e) {
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('tutkimusalue.search_failed'));
        }


        return MipJson::getJson();
    }



    /**
     * Haetaan tutkimusalue
     */
    public function show($id) {

        if(!is_numeric($id)) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        }
        else {
            try {

                // Hae tutkimusalue
                $tutkimusalue = Tutkimusalue::getSinglePublicInformation($id)->first();
                if(!$tutkimusalue) {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('tutkimusalue.search_not_found'));
                    return MipJson::getJson();
                }

                // Muodostetaan propparit
                $properties = clone($tutkimusalue);
                unset($properties['sijainti']);
                unset($properties['sijainti_piste']);

                if(!is_null($tutkimusalue->sijainti)){
                    MipJson::setGeoJsonFeature(json_decode($tutkimusalue->sijainti), $properties);
                }

                if(!is_null($tutkimusalue->sijainti_piste)){
                    MipJson::setGeoJsonFeature(json_decode($tutkimusalue->sijainti_piste), $properties);
                }

                if(is_null($tutkimusalue->sijainti) && is_null($tutkimusalue->sijainti_piste)){
                    MipJson::setGeoJsonFeature(null, $properties);
                }

                MipJson::addMessage(Lang::get('tutkimusalue.search_success'));
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('tutkimusalue.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }


}
