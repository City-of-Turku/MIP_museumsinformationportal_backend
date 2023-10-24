<?php

namespace App\Http\Controllers\Julkinen;

use App\Utils;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Rak\Kiinteisto;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class PublicKiinteistoController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @version 1.0
     * @since 1.0
     * @param \Illuminate\Http\Request $request
     * @return MipJson
     */
    public function index(Request $request)
    {

        $validator = Validator::make($request->all(), [
            //'kiinteistotunnus' 	=> 'size:17|regex:(\d{3}-\d{3}-\d{4}-\d{4})', // 123-123-1234-1234
            "kiinteistotunnus" => "string",
            "id" => "numeric|exists:kiinteisto,id",
            //"aluerajaus"		=> "string|regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/",
        ]);

        if ($validator->fails()) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            foreach ($validator->errors()->all() as $error) {
                MipJson::addMessage($error);
            }
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            return MipJson::getJson();
        }

        $search = $request->input('search');

        try {
            /*
             * By default return ALL items from db (with LIMIT and ORDER options)
             */
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "kunta";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";
            /*
             * Search variable is for implementing the free search functionality.
             * If search variable is given, limit the results by it.
             * If search variable is not given, return all items.
             * The value of the variable is searched from all fields listed below.
             */
            if ($search != null) {
                $kiinteistot = Kiinteisto::getAllPublicInformation()
                    ->where('paikkakunta', 'LIKE', "%{$search}%")
                    ->orWhere('kunta', 'LIKE', "%{$search}%")
                    ->orWhere('osoite', 'LIKE', "%{$search}%")
                    ->orWhere('kiinteisto.nimi', 'LIKE', "%{$search}%");
            } else {
                $kiinteistot = Kiinteisto::getAllPublicInformation();
            }

            // rakennusten osoitteet
            $kiinteistot = $kiinteistot->with(
                array(
                    'rakennusOsoitteet' => function ($query) use ($jarjestys_kentta, $jarjestys_suunta) {
                        if ($jarjestys_kentta == "osoite") {
                            $query->orderBy("katunimi", $jarjestys_suunta);
                            $query->orderBy("katunumero", $jarjestys_suunta);
                        }
                    }
                )
            );

            /*
             * If ANY search terms are given limit results by them
             */
            if ($request->id) {
                $kiinteistot->withID($request->id);
            }
            if ($request->kunta) {
                $kiinteistot->withMunicipality($request->kunta);
            }
            if ($request->kuntanumero) {
                $kiinteistot->withMunicipalityNumber($request->kuntanumero);
            }
            if ($request->kuntaId) {
                $kiinteistot->withMunicipalityId($request->kuntaId);
            }
            if ($request->kyla) {
                $kiinteistot->withVillage($request->kyla);
            }
            if ($request->kylanumero) {
                $kiinteistot->withVillageNumber($request->kylanumero);
            }
            if ($request->kylaId) {
                $kiinteistot->withVillageId($request->kylaId);
            }
            if ($request->nimi) {
                $kiinteistot->withName($request->nimi);
            }
            if ($request->osoite) {
                $kiinteistot->withAddress($request->osoite);
            }
            if ($request->kiinteistotunnus) {
                $kiinteistot->withPropertyID($request->kiinteistotunnus);
            }
            if ($request->aluerajaus) {
                $kiinteistot->withBoundingBox($request->aluerajaus);
            }
            if ($request->polygonrajaus) {
                $kiinteistot->withPolygon($request->polygonrajaus);
            }
            if ($request->arvotus) {
                $kiinteistot->withArvotustyyppiId($request->arvotus);
            }
            if ($request->paikkakunta) {
                $kiinteistot->withPaikkakunta($request->paikkakunta);
            }

            // order the results by given parameters
            $kiinteistot->withOrderBy($request->aluerajaus, $jarjestys_kentta, $jarjestys_suunta);

            // calculate the total rows of the search results
            $total_rows = Utils::getCount($kiinteistot); //count($kiinteistot->get());

            // limit the results rows by given params
            $kiinteistot->withLimit($rivi, $riveja);

            // Execute the query
            $kiinteistot = $kiinteistot->get();

            MipJson::initGeoJsonFeatureCollection(count($kiinteistot), $total_rows);
            foreach ($kiinteistot as $kiinteisto) {

                /*
                 * clone $kiinteisto so we can handle the "properties" separately
                 * -> remove "sijainti" from props
                 */
                $properties = clone ($kiinteisto);
                unset($properties['sijainti']);
                //$properties->rakennukset = $buildings;
                //$properties->test = $kiinteisto->test;
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($kiinteisto->sijainti), $properties);
            }

            MipJson::addMessage(Lang::get('kiinteisto.search_success'));

        } catch (Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('kiinteisto.search_failed'));
        }

        return MipJson::getJson();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return MipJson
     */
    public function show($id)
    {

        if (!is_numeric($id)) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        } else {
            try {
                $kiinteisto = Kiinteisto::getSinglePublicInformation($id)->with(array('kyla', 'kyla.kunta', 'kulttuurihistoriallisetarvot', 'arvotustyyppi'))->first();

                if ($kiinteisto) {

                    //Unset values from array kyla
                    $kiinteisto->kyla->makeHidden(['luoja', 'luotu', 'muokkaaja', 'muokattu', 'poistettu', 'poistaja']);

                    //Unset same values from array kyla->kunta
                    $kiinteisto->kyla->kunta->makeHidden(['luoja', 'luotu', 'muokkaaja', 'muokattu', 'poistettu', 'poistaja']);

                    $properties = clone ($kiinteisto);
                    unset($properties['sijainti']);

                    MipJson::setGeoJsonFeature(json_decode($kiinteisto->sijainti), $properties);
                    MipJson::addMessage(Lang::get('kiinteisto.search_success'));
                } else {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
                }
            } catch (QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('kiinteisto.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Display the list of the buildings of Estate
     *
     * @param int $id

     * @version 1.0
     * @since 1.0
     */
    public function get_buildings($id)
    {

        if (!is_numeric($id)) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        } else {
            try {
                $estate = Kiinteisto::find($id);

                // If estate is not public return not found
                if (!$estate->julkinen) {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('rakennus.search_not_found'));
                    return MipJson::getJson();
                }


                if ($estate) {

                    $buildings = $estate->buildings()->with(array('osoitteet'))->orderby('inventointinumero')->get();

                    //Hide some fields from the response
                    $buildings->makeHidden([
                        'luoja',
                        'luotu',
                        'muokkaaja',
                        'muokattu',
                        'poistettu',
                        'poistaja',
                        'asuin_ja_liikehuoneistoja',
                        'kunto',
                        'erityispiirteet',
                        'sisatilakuvaus',
                        'muut_tiedot',
                        'arvotus',
                        'arvotustyyppi_id',
                        'kulttuurihistoriallisetarvot_perustelut',
                        'postinumero',
                        'kuntotyyppi_id',
                        'rakennushistoria'
                    ]);

                    // calculate the total rows of the search results
                    $total_rows = count($buildings);

                    if ($total_rows > 0) {
                        MipJson::initGeoJsonFeatureCollection(count($buildings), $total_rows);
                        MipJson::addMessage(Lang::get('rakennus.search_success'));
                        foreach ($buildings as $building) {

                            /*
                             * clone $kiinteisto so we can handle the "properties" separately
                             * -> remove "sijainti" from props
                             */
                            $properties = clone ($building);
                            unset($properties['sijainti']);
                            MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($building->sijainti), $properties);
                        }
                    }
                    if (count($buildings) <= 0) {
                        MipJson::setGeoJsonFeature();
                        //MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                        MipJson::addMessage(Lang::get('rakennus.search_not_found'));
                    }
                } else {
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('rakennus.search_not_found'));
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                }
            } catch (QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('rakennus.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Haku kiinteistötunnuksella.
     * Haetaan ensin tunnuksella kiinteistön id ja sillä käytetään show fuktiota.
     */
    function getByIdentifier($kiinteistotunnus)
    {

        try {
            $kiinteisto = DB::table('kiinteisto')->select('id')->where('kiinteistotunnus', '=', $kiinteistotunnus)->first();

            if ($kiinteisto) {
                // Normaali haku id mukaan
                return self::show($kiinteisto->id);
            } else {
                // Kiinteistöä ei löydy MIP:stä
                MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            }

        } catch (Exception $ex) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kiinteisto.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
    }
}