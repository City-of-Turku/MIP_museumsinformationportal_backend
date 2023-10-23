<?php

namespace App\Http\Controllers\Julkinen;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Utils;
use App\Library\String\MipJson;
use App\Rak\Rakennus;
use App\Rak\Kiinteisto;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class PublicRakennusController extends Controller
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
            'kiinteisto_id' => 'numeric|exists:kiinteisto,id',
            'sijainti' => 'regex:/^\d+\.\d+\ \d+\.\d+$/',
            // 20.123456 60.123456 (lat, lon)
            'inventointinumero' => 'string',
            'rakennustunnus' => 'string',
            'kiinteistotunnus' => 'string',
            "kiinteisto_nimi" => 'string',
            "kiinteisto_osoite" => "string",
            "id" => "numeric|exists:rakennus,id",
            "suunnittelija" => "string",
            //"aluerajaus"		=> "string|regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/",
        ]);

        if ($validator->fails()) {
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            foreach ($validator->errors()->all() as $error)
                MipJson::addMessage($error);
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        } else {
            try {

                /*
                 * Initialize the variables that needs to be "reformatted"
                 */
                $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
                $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
                $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
                $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

                $joins = [];
                if ($request->muutosvuosi_alku || $request->muutosvuosi_lopetus) {
                    array_push($joins, 'muutosvuosi');
                }


                $entities = Rakennus::getAllPublicInformation();

                // include all related entities needed here
                $entities = $entities->with(
                    array(
                        'kiinteisto' => function ($query) {
                            $query->select('id', 'kiinteistotunnus', 'nimi', 'osoite', 'kyla_id', 'paikkakunta'); },
                        'kiinteisto.kyla' => function ($query) {
                            $query->select('id', 'nimi', 'nimi_se', 'kunta_id', 'kylanumero'); },
                        'kiinteisto.kyla.kunta' => function ($query) {
                            $query->select('id', 'nimi', 'nimi_se', 'kuntanumero'); }
                    )
                );


                /*
                 * If ANY search terms are given limit results by them
                 */
                if ($request->id)
                    $entities->withID($request->id);
                if ($request->kiinteisto_id)
                    $entities->withPropertyID($request->kiinteisto_id);
                if ($request->kiinteisto_nimi)
                    $entities->withEstateName($request->kiinteisto_nimi);
                if ($request->kiinteistotunnus)
                    $entities->withEstateIdentifier($request->kiinteistotunnus);
                if ($request->rakennustunnus)
                    $entities->withBuildingIdentifier($request->rakennustunnus);
                if ($request->rakennustyyppi)
                    $entities->withBuildingTypeId($request->rakennustyyppi);
                if ($request->suunnittelija)
                    $entities->withDesigner($request->suunnittelija);
                if ($request->kunta)
                    $entities->withMunicipality($request->kunta);
                if ($request->kuntaId)
                    $entities->withKuntaId($request->kuntaId);
                if ($request->kuntanumero)
                    $entities->withMunicipalityNumber($request->kuntanumero);
                if ($request->kyla)
                    $entities->withVillage($request->kyla);
                if ($request->kylaId)
                    $entities->withVillageId($request->kylaId);
                if ($request->kylanumero)
                    $entities->withVillageNumber($request->kylanumero);
                if ($request->paikkakunta)
                    $entities->withPaikkakunta($request->paikkakunta);
                if ($request->rakennus_osoite)
                    $entities->withAddress($request->rakennus_osoite);
                if ($request->palstanumero)
                    $entities->withPalstanumero($request->palstanumero);
                if ($request->aluerajaus)
                    $entities->withBoundingBox($request->aluerajaus);
                if ($request->polygonrajaus) {
                    $entities->withPolygon($request->polygonrajaus);
                }
                if ($request->inventointiprojektiId || $request->inventoija) {
                    $entities->withInventointiprojektiOrInventoija($request->inventointiprojektiId, $request->inventoija);
                }
                if ($request->luoja) {
                    $entities->withLuoja($request->luoja);
                }
                if ($request->rakennustyypin_kuvaus) {
                    $entities->withRakennustyypin_kuvaus($request->rakennustyypin_kuvaus);
                }
                if ($request->rakennusvuosi_alku && $request->rakennusvuosi_lopetus) {
                    $entities->withRakennusvuosi_aikajakso($request->rakennusvuosi_alku, $request->rakennusvuosi_lopetus);
                } else {
                    if ($request->rakennusvuosi_alku) {
                        $entities->withRakennusvuosi_alku($request->rakennusvuosi_alku);
                    }
                    if ($request->rakennusvuosi_lopetus) {
                        $entities->withRakennusvuosi_lopetus($request->rakennusvuosi_lopetus);
                    }
                }
                if ($request->muutosvuosi_alku) {
                    $entities->withMuutosvuosi_alku($request->muutosvuosi_alku);
                    $entities->with(['muutosvuodet']);
                }
                if ($request->muutosvuosi_lopetus) {
                    $entities->withMuutosvuosi_lopetus($request->muutosvuosi_lopetus);
                    $entities->with(['muutosvuodet']);
                }
                if ($request->rakennusvuosi_kuvaus) {
                    $entities->withRakennusvuosi_kuvaus($request->rakennusvuosi_kuvaus);
                }
                if ($request->muutosvuosi_kuvaus) {
                    $entities->withMuutosvuosi_kuvaus($request->muutosvuosi_kuvaus);
                    $entities->with(['muutosvuodet']);
                }
                if ($request->alkuperainen_kaytto) {
                    $entities->withAlkuperainenkaytto($request->alkuperainen_kaytto);
                    $entities->with(['alkuperaisetkaytot']);
                }
                if ($request->nykykaytto) {
                    $entities->withNykykaytto($request->nykykaytto);
                    $entities->with(['nykykaytot']);
                }
                if ($request->perustus) {
                    $entities->withPerustus($request->perustus);
                    $entities->with(['perustustyypit']);
                }
                if ($request->runko) {
                    $entities->withRunko($request->runko);
                    $entities->with(['runkotyypit']);
                }
                if ($request->vuoraus) {
                    $entities->withVuoraus($request->vuoraus);
                    $entities->with(['vuoraustyypit']);
                }
                if ($request->katto) {
                    $entities->withKatto($request->katto);
                    $entities->with(['kattotyypit']);
                }
                if ($request->kate) {
                    $entities->withKate($request->kate);
                    $entities->with(['katetyypit']);
                }
                if ($request->nykytyyli) {
                    $entities->withNykytyyli($request->nykytyyli);
                    $entities->with(['nykyinentyyli']);
                }
                if ($request->purettu) {
                    $entities->withPurettu($request->purettu);
                }
                if ($request->kulttuurihistorialliset_arvot) {
                    $entities->withKulttuurihistorialliset_arvot($request->kulttuurihistorialliset_arvot);
                    $entities->with(['kulttuurihistoriallisetarvot']);
                }
                if ($request->kuvaukset) {
                    $entities->withKuvaukset($request->kuvaukset);
                }

                // calculate the total rows of the search results
                //$total_rows = $entities->count();
                $total_rows = Utils::getCount($entities);

                if ($jarjestys_kentta == "bbox_center" && $request->aluerajaus != null) {
                    $entities->withBoundingBoxOrder($request->aluerajaus);
                } else {
                    $entities->withOrder($jarjestys_kentta, $jarjestys_suunta);
                }

                // limit the results rows by given params
                $entities->withLimit($rivi, $riveja);

                // Execute the query
                $entities = $entities->get();

                //Hide fields from the response
                $entities->makeHidden(['arvotustyyppi_id', 'arvotustyyppi_nimi']);

                MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
                foreach ($entities as $entity) {
                    $properties = clone ($entity);
                    unset($properties['sijainti']);

                    MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($entity->sijainti), $properties);
                }

                MipJson::addMessage(Lang::get('rakennus.search_success'));

            } catch (Exception $e) {
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
                MipJson::addMessage(Lang::get('rakennus.search_failed'));
            }
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

                $entity = Rakennus::getSingle($id)
                    ->with(
                        array(
                            'nykyinentyyli',
                            'rakennustyypit',
                            'alkuperaisetkaytot',
                            'nykykaytot',
                            'perustustyypit',
                            'runkotyypit',
                            'katetyypit',
                            'vuoraustyypit',
                            'kattotyypit',
                            'kulttuurihistoriallisetarvot',
                            'muutosvuodet',
                            'osoitteet' => function ($query) {
                                $query->orderBy('jarjestysnumero'); },
                            'suunnittelijat.suunnittelijatyyppi',
                            'suunnittelijat.suunnittelija.laji',
                            'suunnittelijat.suunnittelija.ammattiarvo'
                        )
                    )->first();

                //Unset values from array rakennustyypit
                $entity->rakennustyypit->makeHidden(['luotu', 'muokattu', 'luoja', 'muokkaaja', 'poistaja', 'poistettu']);

                //Unset values from array osoitteet
                $entity->osoitteet->makeHidden(['muokattu', 'muokkaaja', 'luoja', 'luotu']);

                //Unset values from entity
                $entity->makeHidden(['kunto', 
                                     'luotu', 
                                     'muokattu', 
                                     'luoja', 
                                     'muokkaaja', 
                                     'poistaja', 
                                     'poistettu', 
                                     'postinumero', 
                                     'erityispiirteet', 
                                     'sisatilakuvaus', 
                                     'muut_tiedot', 
                                     'asuin_ja_liikehuoneistoja',
                                     'kuntotyyppi_id',
                                     'arvotus',
                                     'arvotustyyppi_id',
                                     'kulttuurihistoriallisetarvot_perustelut']);

                if ($entity) {
                    $properties = clone ($entity);
                    // remove the sijainti as it will be in the "geometry" section of the json
                    unset($properties['sijainti']);

                    MipJson::setGeoJsonFeature(json_decode($entity->sijainti), $properties);
                    MipJson::addMessage(Lang::get('rakennus.search_success'));

                } else {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('rakennus.search_not_found'));
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
     * Fetch only required values for displaying data on map
     * 
     * Returns both rakennus and its linked kiinteisto entities
     *
     */
    public function getMapItems(Request $request)
    {
        // if request is empty, return empty array. Only return items when
        // searchParam is defined
        if (empty($request->all())) {
            return [];
        }

        try {
            $entities = Rakennus::getAllPublicMapItems();

            if ($request->perustustyyppi)
                $entities->withPerustus($request->perustustyyppi);
            if ($request->runkotyyppi)
                $entities->withRunko($request->runkotyyppi);
            if ($request->rakennustyyppi)
                $entities->withBuildingTypeId($request->rakennustyyppi);
            if ($request->tyylisuunta)
                $entities->withNykytyyli($request->tyylisuunta);


            $total_rows = Utils::getCount($entities);
            $rakennukset = $entities->get();

            $kiinteistoIds = $rakennukset->pluck('kiinteisto_id')->unique();
            $kiinteistot = Kiinteisto::getAllPublicMapItems()->whereIn('id', $kiinteistoIds)->get();

            $rakennuksetTransformed = $rakennukset->map(function ($rakennus) {
                $rakennus->type = 'rakennus';
                return $rakennus;
            });
            
            $kiinteistotTransformed = $kiinteistot->map(function ($kiinteisto) {
                $kiinteisto->type = 'kiinteisto';
                return $kiinteisto;
            });
            
            return $rakennuksetTransformed->concat($kiinteistotTransformed);
        } catch (QueryException $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('rakennus.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
    }
}