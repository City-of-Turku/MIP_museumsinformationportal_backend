<?php

namespace App\Http\Controllers\Ark;

use App\Kayttaja;
use App\Utils;
use App\Ark\TutkimusInvKohteet;
use App\Ark\Tutkimusalue;
use App\Http\Controllers\Controller;
use App\Library\Gis\MipGis;
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
 * Tutkimusalueen käsittelyt
 */
class TutkimusalueController extends Controller
{
    /**
     * Tutkimusalueiden haku
     *
     * @param Request $request
     */
    public function index(Request $request) {

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_tutkimusalue.katselu', $request->input('ark_tutkimus_id'))) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }
        /*
         * Validointi
         */
//         $validator = Validator::make($request->all(), [
//             'ark_tutkimuslaji_id'	=> 'required|numeric|exists:',
//             'nimi'					=> 'required|string'
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
            // Hakuparametrit
            //$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            //$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";
            $tutkimusalueet = Tutkimusalue::getAll()->with(
                'luoja',
                'muokkaaja',
            	'tutkimus'
            );

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

        /*
         * Käyttöoikeus
         */
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_tutkimusalue.katselu', $id)) {
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

                // Hae tutkimusalue
                $tutkimusalue = Tutkimusalue::getSingle($id)->with( array(
                    'tutkimus',
                    //'yksikot', Ei käytetä missään, yksiköt haetaan erikseen
                    //'yksikot.yksikkoTyyppi', Ei käytetä missään, yksiköt haetaan erikseen
                    'luoja',
                    'muokkaaja'))->first();
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

                // Muistiinpanoja ei näytetä katselijalle
                if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
                    unset($properties['muistiinpanot']);
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

    /**
     * Tallenna tutkimusalue
     *
     */
    public function store(Request $request) {

        /*
         * Käyttöoikeus
         */
    	if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_tutkimusalue.luonti', $request->input('properties.ark_tutkimus_id'))) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'ark_tutkimus_id'	    => 'required|numeric',
            'nimi'					=> 'required|string'
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

                $tutkimusalue = new Tutkimusalue($request->all()['properties']);
                // Joko point tai polygon
                if(isset($request->all()['properties']['sijainti'])) {
                    $geom_alue = MipGis::getAreaGeometryValue($request->all()['properties']['sijainti']);
                    $tutkimusalue->sijainti = $geom_alue;
                } else if(isset($request->sijainti_piste)) {
                    $geom_piste = MipGis::getPointGeometryValue($request->all()['properties']['sijainti_piste']);
                    $tutkimusalue->sijainti_piste = $geom_piste;
                }

                // Inventointi-tutkimuksen kohteiden päivitys
                if(isset($request->all()['properties']['kohteet'])) {
                    TutkimusInvKohteet::paivita_kohteet($tutkimusalue->ark_tutkimus_id, $request->all()['properties']['kohteet']);
                }

                $tutkimusalue->luoja = Auth::user()->id;
                $tutkimusalue->save();

             } catch(Exception $e) {
                 DB::rollback();
                 MipJson::setGeoJsonFeature();
                 MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
                 MipJson::addMessage(Lang::get('tutkimusalue.save_failed'));
                 return MipJson::getJson();
             }

            // Onnistunut case
            DB::commit();

            MipJson::addMessage(Lang::get('tutkimusalue.save_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $tutkimusalue->id));
            MipJson::setResponseStatus(Response::HTTP_OK);

         } catch(Exception $e) {
             MipJson::setGeoJsonFeature();
             MipJson::setMessages(array(Lang::get('tutkimusalue.save_failed'),$e->getMessage()));
             MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
         }

        return MipJson::getJson();
    }

    /**
     * Tutkimusalueen päivitys
     */
    public function update(Request $request, $id) {
        /*
         * Käyttöoikeus
         */
    	if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_tutkimusalue.muokkaus', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        /*
         * Validointi
         */
        $validator = Validator::make($request->all()['properties'], [
            'ark_tutkimus_id'	    => 'required|numeric',
            'nimi'					=> 'required_without:vain_sijainti|string',
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
                $tutkimusalue = Tutkimusalue::find($id);

                if(!$tutkimusalue){
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('tutkimusalue.search_not_found'));
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                }
                else {
                    DB::beginTransaction();
                    Utils::setDBUser();

                    try {
                        //Jos päivitetään sijaintia tiedostosta, ei tallenneta muita tietoja kuin ainoastaan geometria
                        if(isset($request->all()['properties']['vain_sijainti']) && $request->all()['properties']['vain_sijainti'] == "true") {
                            if(isset($request->all()['geometry']['type'])){
                                if($request->all()['geometry']['type'] == 'Point') {
                                    $geom = MipGis::getPointGeometryValue($request->all()['geometry']['coordinates'][0] . " ".  $request->all()['geometry']['coordinates'][1]);
                                    $tutkimusalue->sijainti = $geom;
                                } else if($request->all()['geometry']['type'] == 'Polygon') {
                                    $coordsAsText = MipGis::extractPolygonPoints($request->all()['geometry']);
                                    $geom = MipGis::getAreaGeometryValue($coordsAsText);
                                    $tutkimusalue->sijainti = $geom;
                                }
                            }
                        } else {
                            $tutkimusalue->fill($request->all()['properties']);

                            // Asetetaan piste tai alue-sijainti, tai tyhjennetään jos poistettu.
                            // Alue
                            if(isset($request->all()['properties']['sijainti'])) {
                                $geom_alue = MipGis::getAreaGeometryValue($request->all()['properties']['sijainti']);
                                $tutkimusalue->sijainti = $geom_alue;
                            } else {
                                // pistesijainti poistettu
                                $tutkimusalue->sijainti = null;
                            }
                            // Piste
                            if(isset($request->all()['properties']['sijainti_piste'])) {
                                $geom_piste = MipGis::getPointGeometryValue($request->all()['properties']['sijainti_piste']);
                                $tutkimusalue->sijainti_piste = $geom_piste;
                            } else {
                                // sijainti alue poistettu
                                $tutkimusalue->sijainti_piste = null;
                            }

                            // Inventointi-tutkimuksen kohteiden päivitys
                            if(isset($request->all()['properties']['kohteet'])) {

                                // Jos TutkimusInventointiKohteet jotka nyt tulevat eroavat aikaisemmista, päivitetään ne
                                $existingInvKohteet = TutkimusInvKohteet::byTutkimusId($tutkimusalue->ark_tutkimus_id)->get();
                                $isDifferent = false;
                                $existingIdList = [];
                                $incomingIdList = [];
                                foreach($existingInvKohteet as $ek) {
                                    array_push($existingIdList, $ek->ark_kohde_id);
                                }
                                foreach($request->all()['properties']['kohteet'] as $ik) {
                                    array_push($incomingIdList, $ik['kohde_id']);
                                }

                                if($incomingIdList != $existingIdList) {
                                    TutkimusInvKohteet::paivita_kohteet($tutkimusalue->ark_tutkimus_id, $request->all()['properties']['kohteet']);
                                }

                            }

                        }
                        $author_field = Tutkimusalue::UPDATED_BY;
                        $tutkimusalue->$author_field = Auth::user()->id;

                        $tutkimusalue->update();

                    } catch(Exception $e) {
                        DB::rollback();
                        throw $e;
                    }

                    // Päivitys onnistui
                    DB::commit();

                    MipJson::addMessage(Lang::get('tutkimusalue.save_success'));
                    MipJson::setGeoJsonFeature(null, array("id" => $tutkimusalue->id));

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
                MipJson::setMessages(array(Lang::get('tutkimusalue.update_failed'), $e->getMessage()));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Poista tutkimusalue. Asetetaan poistettu aikaleima ja poistaja, ei deletoida riviä.
     */
    public function destroy($id) {

        /*
         * Role check
         */
    	if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_tutkimusalue.poisto', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }
        $tutkimusalue = Tutkimusalue::find($id);

        if(!$tutkimusalue) {
            // return: not found
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('tutkimusalue.search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            $author_field = Tutkimusalue::DELETED_BY;
            $when_field = Tutkimusalue::DELETED_AT;
            $tutkimusalue->$author_field = Auth::user()->id;
            $tutkimusalue->$when_field = \Carbon\Carbon::now();

            $tutkimusalue->save();


            DB::commit();

            MipJson::addMessage(Lang::get('tutkimusalue.delete_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $tutkimusalue->id));

        } catch(Exception $e) {
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('tutkimusalue.delete_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    public function historia($id) {

        /*
         * Käyttöoikeus
         */
    	if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_tutkimusalue.katselu', $id)) {
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
        //TODO ei ole tutkimusalueelle muutoshistoriaa tehty
        //return TutkimusalueMuutoshistoria::getById($id);

    }
}
