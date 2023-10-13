<?php

namespace App\Http\Controllers\Julkinen;

use App\Utils;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Rak\Arvoalue;
use App\Rak\Kiinteisto;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class PublicArvoalueController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'rivi' => 'numeric',
            'rivit' => 'numeric',
            'jarjestys' => 'string',
            'jarjestys_suunta' => 'string',
            'kunta' => 'string',
            'kuntanumero' => 'numeric',
            'kyla' => 'string',
            'kylanumero' => 'numeric',
            'alue_nimi' => 'string',
            'nimi' => 'string',
            'aluetyyppi' => 'string',
            'id' => "numeric|exists:arvoalue,id",
            'inventointinumero' => 'numeric',
            //"aluerajaus"		=> "string|regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/",
        ]);

        if ($validator->fails()) {
            MipJson::setGeoJsonFeature();
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

                $entities = Arvoalue::getAllPublicInformation();

                /*
                 * If ANY search terms are given limit results by them
                 */
                if ($request->input('id')) {
                    $entities->withID($request->input('id'));
                }
                if ($request->input('paikkakunta')) {
                    $entities->withPaikkakunta($request->input('paikkakunta'));
                }
                if ($request->input('nimi')) {
                    $entities->withName($request->input('nimi'));
                }
                if ($request->aluerajaus) {
                    $entities->withBoundingBox($request->aluerajaus);
                }
                //Polygonrajaus tarkoittaa vapaamuotoista piirrettyä geometriaa jonka mukaan rajataan
                if ($request->polygonrajaus) {
                    $entities->withPolygon($request->polygonrajaus);
                }
                if ($request->inventointiprojektiId || $request->inventoija) {
                    $entities->withInventointiprojektiOrInventoija($request->inventointiprojektiId, $request->inventoija);
                }
                if ($request->input('aluetyyppi')) {
                    $entities->withAreatypeId($request->input('aluetyyppi'));
                }
                if ($request->input('arvotus')) {
                    $entities->withArvotustyyppiId($request->input('arvotus'));
                }
                if ($request->input('alue_nimi')) {
                    $entities->withAreaName($request->input('alue_nimi'));
                }
                if ($request->input('kyla')) {
                    $entities->withVillageName($request->input('kyla'));
                }
                if ($request->input('kylaId')) {
                    $entities->withVillageId($request->input('kylaId'));
                }
                if ($request->input('kylanumero')) {
                    $entities->withVillageNumber($request->input('kylanumero'));
                }
                if ($request->input('kunta')) {
                    $entities->withMunicipalityName($request->input('kunta'));
                }
                if ($request->input('kuntaId')) {
                    $entities->withMunicipalityId($request->input('kuntaId'));
                }
                if ($request->input('kuntanumero')) {
                    $entities->withMunicipalityNumber($request->input('kuntanumero'));
                }
                if ($request->input('alue_id')) {
                    $entities->withAreaId($request->input('alue_id'));
                }

                $entities = $entities->with(
                    array(
                        'aluetyyppi',
                        'arvotustyyppi',
                        '_alue',
                        'kylat' => function ($query) use ($jarjestys_kentta, $jarjestys_suunta) {
                            if ($jarjestys_kentta == "kyla") {
                                $query->orderBy("nimi", $jarjestys_suunta);
                            }
                        },
                        'kylat.kunta' => function ($query) use ($jarjestys_kentta, $jarjestys_suunta) {
                            if ($jarjestys_kentta == "kunta") {
                                $query->orderBy("nimi", $jarjestys_suunta);
                            }
                        }
                    )
                );

                // order the results by given parameters
                $entities->withOrderBy($request->aluerajaus, $jarjestys_kentta, $jarjestys_suunta);

                // calculate the total rows of the search results
                $total_rows = Utils::getCount($entities);

                // limit the results rows by given params
                $entities->withLimit($rivi, $riveja);

                // Execute the query
                $entities = $entities->get();

                MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
                foreach ($entities as $entity) {

                    //Hide fields from kylat and kylat.kunta arrays
                    foreach ($entity->kylat as $kyla) {
                        $kyla->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
                        $kyla->kunta->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
                    }
                    //Hide fields from aluetyyppi array
                    if (!is_null($entity->aluetyyppi)) {
                        $entity->aluetyyppi->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
                    }
                    //Hide fields from arvotustyyppi array
                    if (!is_null($entity->arvotustyyppi)) {
                        $entity->arvotustyyppi->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
                    }
                    //Hide fields from _alue array
                    if (!is_null($entity->_alue)) {
                        $entity->_alue->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja', 'lisatiedot', 'lahteet', 'arkeologinen_kohde']);
                    }


                    /*
                     * -> remove "sijainti" from props
                     */
                    $properties = clone ($entity);
                    unset($properties['sijainti']);
                    unset($properties['alue']);

                    if (!is_null($entity->sijainti)) {
                        MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($entity->sijainti), $properties);
                    } else {
                        MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($entity->alue), $properties);
                    }
                }

                MipJson::addMessage(Lang::get('arvoalue.search_success'));

            } catch (Exception $e) {
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
                MipJson::addMessage(Lang::get('arvoalue.search_failed'));
            }
        }
        return MipJson::getJson();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        if (!is_numeric($id)) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        } else {
            try {
                $entity = Arvoalue::getSinglePublicInformation($id)->with(
                    array(
                        'arvotustyyppi',
                        'kulttuurihistoriallisetarvot',
                        'aluetyyppi',
                        '_alue',
                        'kylat',
                        'kylat.kunta',
                    )
                )->first();

                if ($entity) {

                    //Hide values from entity  
                    $entity->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja', 'tarkistettu', 'arkeologinen_kohde']);

                    //Hide values from arvotustyyppi array
                    if (!is_null($entity->arvotustyyppi)) {
                        $entity->arvotustyyppi->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
                    }

                    //Hide values from kulttuurihistoriallisetarvot array
                    foreach ($entity->kulttuurihistoriallisetarvot as $kulttuurihistoriallinenarvo) {
                        $kulttuurihistoriallinenarvo->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
                    }

                    //Hide values from aluetyyppi array
                    if (!is_null($entity->aluetyyppi)) {
                        $entity->aluetyyppi->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
                    }

                    //Hide values from _alue array
                    if (!is_null($entity->_alue)) {
                        $entity->_alue->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja', 'lisatiedot', 'lahteet', 'arkeologinen_kohde']);
                    }

                    //Hide values from kylat array
                    foreach ($entity->kylat as $kyla) {
                        $kyla->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
                        $kyla->kunta->makeHidden(['poistettu', 'poistaja', 'luotu', 'luoja', 'muokattu', 'muokkaaja']);
                    }

                    $properties = clone ($entity);
                    unset($properties['sijainti']);
                    unset($properties['alue']);

                    if (!is_null($entity->sijainti)) {
                        MipJson::setGeoJsonFeature(json_decode($entity->sijainti), $properties);
                    } else if (!is_null($entity->alue)) {
                        MipJson::setGeoJsonFeature(json_decode($entity->alue), $properties);
                    } else {
                        MipJson::setGeoJsonFeature(null, $properties);
                    }

                    MipJson::addMessage(Lang::get('arvoalue.search_success'));
                } else {
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('arvoalue.search_not_found'));
                }
            } catch (QueryException $e) {
                MipJson::addMessage(Lang::get('arvoalue.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Method to get all estates within an valuearea
     *
     * @param int $id the id of the arvoalue
     * @return MipJson
     */
    public function showEstatesWithin($id) {

    	if(!is_numeric($id)) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {
    		try {
    			$arvoalue = Arvoalue::getSinglePublicInformation($id)->first();

    			if($arvoalue) {
    				$estates = Kiinteisto::getWithinArvoalue($id); // no ->get() here because raw select

    				MipJson::initGeoJsonFeatureCollection(count($estates), count($estates));
    				foreach($estates as $estate) {
                        //Next estate if estate -> julkinen is false
                        if(!$estate->julkinen) continue;
                        //Hide values from estate
                        $estate->makeHidden(['poistettu', 
                                             'poistaja', 
                                             'luotu', 
                                             'luoja', 
                                             'muokattu', 
                                             'muokkaaja',
                                             'palstanumero',
                                             'postinumero',
                                             'asutushistoria',
                                             'lahiymparisto',
                                             'pihapiiri',
                                             'muu_historia',
                                             'tarkistettu',
                                             'julkinen',
                                             'lahteet',
                                             'perustelut',
                                             'paikallismuseot_kuvaus',
                                             'linkit_paikallismuseoihin',
                                             'lisatiedot',
                                             'omistajatiedot']); 

			    		$properties = clone($estate);
			    		unset($properties['sijainti']);

			    		MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($estate->sijainti), $properties);
    				}
    				MipJson::addMessage(Lang::get('arvoalue.search_success'));
    			}
    			else {
    				MipJson::setGeoJsonFeature();
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				MipJson::addMessage(Lang::get('arvoalue.search_not_found'));
    			}
    		}
    		catch(QueryException $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::addMessage(Lang::get('arvoalue.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
    }    

}