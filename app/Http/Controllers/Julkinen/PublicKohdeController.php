<?php

namespace App\Http\Controllers\Julkinen;

use App\Utils;
use App\Ark\Kohde;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class PublicKohdeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @version 1.0
     * @since 1.0
     * @param \Illuminate\Http\Request $request
     * @return MipJson
     */
    public function index(Request $request) {


        $validator = Validator::make($request->all(), [
            //"kiinteistotunnus"	=> "string",
            //"id"				=> "numeric|exists:kiinteisto,id",
            //"aluerajaus"		=> "string|regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/",
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
            /*
             * By default return ALL items from db (with LIMIT and ORDER options)
             */
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $kohteet = Kohde::getAllPublicInformation()->with( array(
            	'ajoitukset.ajoitus' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
						if ($jarjestys_kentta == "ajoitus") {
							$query->orderBy("nimi_fi", $jarjestys_suunta);
						}
    				},
            	'ajoitukset.tarkenne' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
						if ($jarjestys_kentta == "ajoitustarkenne") {
							$query->orderBy("nimi_fi", $jarjestys_suunta);
						}
    				},
            	'kunnatkylat.kunta' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
						if ($jarjestys_kentta == "kunta") {
							$query->orderBy("nimi", $jarjestys_suunta);
						}
    				},
            	'kunnatkylat.kyla' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
						if ($jarjestys_kentta == "kyla") {
							$query->orderBy("nimi", $jarjestys_suunta);
						}
    				},
            	'tyypit.tyyppi' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
						if ($jarjestys_kentta == "tyyppi") {
							$query->orderBy("nimi_fi", $jarjestys_suunta);
						}
    				},
            	'tyypit.tarkenne' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
						if ($jarjestys_kentta == "tyyppitarkenne") {
							$query->orderBy("nimi_fi", $jarjestys_suunta);
						}
    				},
   				'laji',
   				'sijainnit'
            ));

            if($request->kunta) {
            	$kohteet->withKuntaNimi($request->kunta);
            }
            if($request->kuntanumero) {
            	$kohteet->withKuntaNumero($request->kuntanumero);
            }
            if($request->kuntaId) {
                $kohteet->withKuntaId($request->kuntaId);
            }
            if($request->kyla) {
            	$kohteet->withKylaNimi($request->kyla);
            }
            if($request->kylanumero) {
            	$kohteet->withKylaNumero($request->kylanumero);
            }
            if($request->kylaId) {
                $kohteet->withKylaId($request->kylaId);
            }
            if($request->nimi) {
                $kohteet->withName($request->nimi);
            }
            if($request->muinaisjaannostunnus) {
            	$kohteet->withRelicId($request->muinaisjaannostunnus);
            }
            if($request->kohdelajit) {
            	$kohteet->withKohdeLajit($request->kohdelajit);
            }
            if($request->kohdetyypit) {
            	$kohteet->withKohdeTyypit($request->kohdetyypit);
            }
            if($request->kohdetyyppitarkenteet) {
            	$kohteet->withKohdeTyyppiTarkenteet($request->kohdetyyppitarkenteet);
            }
            if($request->ajoitukset) {
            	$kohteet->withAjoitukset($request->ajoitukset);
            }
            if ($request->kiinteistotunnus) {
            	$kohteet->withKiinteistotunnus($request->kiinteistotunnus);
            }
            /*
             *   Kohde tyhjä. Oletuksena ei haeta tyhjiä.
             *  1 = ei, 2 = kyllä, 3 = kaikki
             */
            if($request->tyhja){
                if($request->tyhja != 3){
                    $kohteet->withTyhjaKohde($request->tyhja);
                }
            }else{
                $tyhja = 1;
                $kohteet->withTyhjaKohde($tyhja);
            }
            /*
             *   Vaatii tarkastusta. Oletuksena kaikki.
             *  1 = ei, 2 = kyllä, 3 = kaikki
             */
            if($request->vaatii_tarkastusta){
                if($request->vaatii_tarkastusta != 3){
                    $kohteet->withVaatiiTarkastusta($request->vaatii_tarkastusta);
                }
            }

            if ($request->kyppitilat) {
                $kohteet->withKyppitilat($request->kyppitilat);
            }
            // Aluerajaus tarkoittaa bounding boxia
            if($request->aluerajaus) {
                $kohteet->withBoundingBox($request->aluerajaus);
            }
            // Polygonrajaus tarkoittaa vapaamuotoista piirrettyä geometriaa jonka mukaan rajataan
            if($request->polygonrajaus) {
                $kohteet->withPolygon($request->polygonrajaus);
            }

            $kohteet->withOrderBy($request->aluerajaus, $jarjestys_kentta, $jarjestys_suunta);

            // calculate the total rows of the search results
            $total_rows = Utils::getCount($kohteet);

            // limit the results rows by given params
            $kohteet->withLimit($rivi, $riveja);

            // Execute the query
            $kohteet = $kohteet->get();

            MipJson::initGeoJsonFeatureCollection(count($kohteet), $total_rows);

            foreach ($kohteet as $kohde) {

                //Hide values from kohde
                $kohde->makeHidden([
                    'tarkastus_muistiinpano',
                    'tuhoutumissyy_id',
                    'tuhoutumiskuvaus',
                    'virallinenkatselmus',
                    'mahdollisetseuraamukset',
                    'luotu',
                    'luoja',
                    'muokattu',
                    'muokkaaja',
                    'poistettu',
                    'poistaja',
                    'vaatii_tarkastusta',
                    'viranomaisurl',
                    'avattu',
                    'avaaja',
                    'muutettu',
                    'muuttaja'
                ]);

                //Hide values from kohde->ajoitukset
                foreach ($kohde->ajoitukset as $ajoitus) {
                    $ajoitus->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);

                    //Hide values from kohde->ajoitukset->ajoitus
                    if (!is_null($ajoitus->ajoitus)) {
                        $ajoitus->ajoitus->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }

                    //Hide values from kohde->ajoitukset->tarkenne
                    if (!is_null($ajoitus->tarkenne)) {
                        $ajoitus->tarkenne->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }
                }

                //Hide values from kohde->kunnatkylat
                foreach ($kohde->kunnatkylat as $kunnankyla) {
                    $kunnankyla->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);

                    //Hide values from kohde->kunnatkylat->kunta
                    if (!is_null($kunnankyla->kunta)) {
                        $kunnankyla->kunta->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }

                    //Hide values from kohde->kunnatkylat->kyla
                    if (!is_null($kunnankyla->kyla)) {
                        $kunnankyla->kyla->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }
                }

                //Hide values from kohde->tyypit
                foreach ($kohde->tyypit as $tyyppi) {
                    $tyyppi->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);

                    //Hide values from kohde->tyypit->tyyppi
                    if (!is_null($tyyppi->tyyppi)) {
                        $tyyppi->tyyppi->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }

                    //Hide values from kohde->tyypit->tarkenne
                    if (!is_null($tyyppi->tarkenne)) {
                        $tyyppi->tarkenne->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }
                }

                //Hide values from kohde->laji
                if (!is_null($kohde->laji)) {
                    $kohde->laji->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja',
                        'poistettu',
                        'poistaja'
                    ]);
                }

                //Hide values from kohde->sijainnit
                foreach ($kohde->sijainnit as $sijainti) {
                    $sijainti->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);
                }

                //Hide values from kohde->kiinteistotrakennukset



                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $kohde);
            }

            MipJson::addMessage(Lang::get('kohde.search_success'));

         } catch(Exception $e) {
             MipJson::setGeoJsonFeature();
             MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
             MipJson::addMessage(Lang::get('kohde.search_failed'));
         }

        return MipJson::getJson();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return MipJson
     */
    public function show($id) {

        if(!is_numeric($id)) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        }
        else {
            try {
                    $kohde = Kohde::getSinglePublicInformation($id)
                    ->with( array(
                    	'ajoitukset.ajoitus',
                    	'ajoitukset.tarkenne',
                    	'alkuperaisyys',
                    	'hoitotarve',
                    	'laji',
                    	'kunto',
                    	'kunnatkylat.kunta',
                    	'kunnatkylat.kyla',
                    	'maastomerkinta',
                    	'muokkaaja',
                    	'rajaustarkkuus',
                    	'rauhoitusluokka',
                    	'sijainnit',
                    	'suojelutiedot.suojelutyyppi',
                    	'tyypit.tyyppi',
                    	'tyypit.tarkenne',
                    	'kiinteistotrakennukset',
                        'kiinteistotrakennukset.osoitteet',
                    	'vanhatKunnat',
                    	'mjrtutkimukset',
                    	'mjrtutkimukset.mjrtutkimuslaji',
                    	'alakohteet',
                    	'alakohteet.tyyppi',
                    	'alakohteet.tyyppitarkenne',
                    	'alakohteet.ajoitukset.ajoitus',
                    	'alakohteet.ajoitukset.tarkenne',
                    	'alakohteet.sijainnit',
                        'tutkimukset',
                    ))->first();

                if(!$kohde) {
                	MipJson::setGeoJsonFeature();
                	MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                	MipJson::addMessage(Lang::get('kohde.search_not_found'));
                }

                //Hide values from kohde
                $kohde->makeHidden([
                    'tarkastus_muistiinpano',
                    'tuhoutumissyy_id',
                    'tuhoutumiskuvaus',
                    'virallinenkatselmus',
                    'mahdollisetseuraamukset',
                    'luotu',
                    'luoja',
                    'muokattu',
                    'muokkaaja',
                    'poistettu',
                    'poistaja',
                    'vaatii_tarkastusta',
                    'viranomaisurl',
                    'avattu',
                    'avaaja',
                    'muutettu',
                    'muuttaja',
                    'ark_kohdelaji_id',
                    'rauhoitusluokka_id',
                    'alkuperaisyys_id',
                    'kunto_id',
                    'hoitotarve_id',
                    'rajaustarkkuus_id',
                    'maastomerkinta_id',
                    'tuhoutumissyy_id'
                ]);

                //Hide values from kohde->ajoitukset
                foreach ($kohde->ajoitukset as $ajoitus) {
                    $ajoitus->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);

                    //Hide values from kohde->ajoitukset->ajoitus
                    if (!is_null($ajoitus->ajoitus)) {
                        $ajoitus->ajoitus->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }

                    //Hide values from kohde->ajoitukset->tarkenne
                    if (!is_null($ajoitus->tarkenne)) {
                        $ajoitus->tarkenne->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }
                }

                //Hide values from kohde->alkuperäisyys
                if (!is_null($kohde->alkuperaisyys)) {
                    $kohde->alkuperaisyys->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja',
                        'poistettu',
                        'poistaja'
                    ]);
                }

                //Hide values from kohde->hoitotarve   
                if (!is_null($kohde->hoitotarve)) {
                    $kohde->hoitotarve->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja',
                        'poistettu',
                        'poistaja'
                    ]);
                }

                //Hide values from kohde->laji
                if (!is_null($kohde->laji)) {
                    $kohde->laji->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja',
                        'poistettu',
                        'poistaja'
                    ]);
                }

                //Hide values from kohde->kunto
                if (!is_null($kohde->kunto)) {
                    $kohde->kunto->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja',
                        'poistettu',
                        'poistaja'
                    ]);
                }

                //Hide values from kohde->kunnatkylat
                foreach ($kohde->kunnatkylat as $kunnankyla) {
                    $kunnankyla->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);

                    //Hide values from kohde->kunnatkylat->kunta
                    if (!is_null($kunnankyla->kunta)) {
                        $kunnankyla->kunta->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }

                    //Hide values from kohde->kunnatkylat->kyla
                    if (!is_null($kunnankyla->kyla)) {
                        $kunnankyla->kyla->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }
                }

                //Hide values from kohde->maastomerkinta
                if (!is_null($kohde->maastomerkinta)) {
                    $kohde->maastomerkinta->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja',
                        'poistettu',
                        'poistaja'
                    ]);
                }

                //Hide values from kohde->rajaustarkkuus
                if (!is_null($kohde->rajaustarkkuus)) {
                    $kohde->rajaustarkkuus->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja',
                        'poistettu',
                        'poistaja'
                    ]);
                }

                //Hide values from kohde->rauhoitusluokka
                if (!is_null($kohde->rauhoitusluokka)) {
                    $kohde->rauhoitusluokka->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja',   
                        'poistettu',
                        'poistaja'
                    ]);
                }

                //Hide values from kohde->sijainnit
                foreach ($kohde->sijainnit as $sijainti) {
                    $sijainti->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);
                }

                //Hide values from kohde->suojelutiedot
                foreach ($kohde->suojelutiedot as $suojelutieto) {
                    $suojelutieto->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);

                    //Hide values from kohde->suojelutiedot->suojelutyyppi
                    if (!is_null($suojelutieto->suojelutyyppi)) {
                        $suojelutieto->suojelutyyppi->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }
                }

                //Hide values from kohde->tyypit
                foreach ($kohde->tyypit as $tyyppi) {
                    $tyyppi->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);

                    //Hide values from kohde->tyypit->tyyppi
                    if (!is_null($tyyppi->tyyppi)) {
                        $tyyppi->tyyppi->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'
                        ]);
                    }

                    //Hide values from kohde->tyypit->tarkenne
                    if (!is_null($tyyppi->tarkenne)) {
                        $tyyppi->tarkenne->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'   
                        ]);
                    }
                }

                //Hide values from kohde->kiinteistotrakennukset
                foreach ($kohde->kiinteistotrakennukset as $kiinteistotrakennus) {
                    $kiinteistotrakennus->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);

                    //Hide values from kohde->kiinteistotrakennukset->osoitteet
                    foreach ($kiinteistotrakennus->osoitteet as $osoite) {
                        $osoite->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja'
                        ]);
                    }
                }

                //Hide values from kohde->vanhatKunnat
                foreach ($kohde->vanhatKunnat as $vanhaKunta) {
                    $vanhaKunta->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);
                }

                //Hide values from kohde->mjrtutkimukset
                foreach ($kohde->mjrtutkimukset as $mjrtutkimus) {
                    $mjrtutkimus->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);

                    //Hide values from kohde->mjrtutkimukset->mjrtutkimuslaji
                    if (!is_null($mjrtutkimus->mjrtutkimuslaji)) {
                        $mjrtutkimus->mjrtutkimuslaji->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja',
                            'poistettu',
                            'poistaja'   
                        ]);
                    }
                }

                //Hide values from kohde->alakohteet and its inner objects
                foreach ($kohde->alakohteet as $alakohde) {
                    $alakohde->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja',
                        'poistettu',
                        'poistaja'
                    ]);

                    //Hide values from kohde->alakohteet->tyyppi
                    if (!is_null($alakohde->tyyppi)) {
                        $alakohde->tyyppi->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja'
                        ]);
                    }

                    //Hide values from kohde->alakohteet->tyyppitarkenne
                    if (!is_null($alakohde->tyyppitarkenne)) {
                        $alakohde->tyyppitarkenne->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja'
                        ]);
                    }

                    //Hide values from kohde->alakohteet->ajoitukset
                    foreach ($alakohde->ajoitukset as $ajoitus) {
                        $ajoitus->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja'
                        ]);

                        //Hide values from kohde->alakohteet->ajoitukset->ajoitus
                        if (!is_null($ajoitus->ajoitus)) {
                            $ajoitus->ajoitus->makeHidden([
                                'luotu',
                                'luoja',
                                'muokattu',
                                'muokkaaja',
                                'poistettu',
                                'poistaja'
                            ]);
                        }

                        //Hide values from kohde->alakohteet->ajoitukset->tarkenne
                        if (!is_null($ajoitus->tarkenne)) {
                            $ajoitus->tarkenne->makeHidden([
                                'luotu',
                                'luoja',
                                'muokattu',
                                'muokkaaja',
                                'poistettu',
                                'poistaja'
                            ]);
                        }
                    }

                    //Hide values from kohde->alakohteet->sijainnit
                    foreach ($alakohde->sijainnit as $sijainti) {
                        $sijainti->makeHidden([
                            'luotu',
                            'luoja',
                            'muokattu',
                            'muokkaaja'
                        ]);
                    }
                }

                //Hide values from kohde->tutkimukset
                foreach ($kohde->tutkimukset as $tutkimus) {
                    $tutkimus->makeHidden([
                        'luotu',
                        'luoja',
                        'muokattu',
                        'muokkaaja'
                    ]);
                }

                $properties = clone($kohde);


                MipJson::setGeoJsonFeature(null, $properties);
                MipJson::addMessage(Lang::get('kohde.search_success'));
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('kohde.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }
}
