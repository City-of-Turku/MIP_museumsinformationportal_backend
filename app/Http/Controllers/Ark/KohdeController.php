<?php

namespace App\Http\Controllers\Ark;

use App\Kayttaja;
use App\Utils;
use App\Ark\KiinteistoRakennus;
use App\Ark\Kohde;
use App\Ark\KohdeAjoitus;
use App\Ark\KohdeAlakohde;
use App\Ark\KohdeKuntaKyla;
use App\Ark\KohdeMuutoshistoria;
use App\Ark\KohdeSijainti;
use App\Ark\KohdeSuojelutieto;
use App\Ark\KohdeTyyppi;
use App\Ark\KohdeVanhaKunta;
use App\Ark\Tutkimus;
use App\Ark\TutkimusInvKohteet;
use App\Http\Controllers\Controller;
use App\Integrations\KyppiService;
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


class KohdeController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @version 1.0
     * @since 1.0
     * @param \Illuminate\Http\Request $request
     * @return MipJson
     */
    public function index(Request $request) {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_kohde.katselu')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }


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
        //DB::enableQueryLog();

         try {
            /*
             * By default return ALL items from db (with LIMIT and ORDER options)
             */
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "nimi";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $kohteet = Kohde::getAll()->with( array(
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
   				'sijainnit',
   				'kiinteistotrakennukset',
   				'inventointitutkimukset.tutkimuskayttajat'
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
            // Inventointi-tutkimusten filtteröinti pivot tyyliin.
            if($request->tutkimuksen_nimi) {
                $nimi = $request->tutkimuksen_nimi;
                $kohteet = $kohteet->whereHas('inventointiTutkimukset', function($q) use($nimi) {
                    $q->where('ark_tutkimus.nimi', 'ILIKE', "%" . $nimi . "%");
                });
            }
            // Inventoijan mukaan filtteröinti ( inventoija on ark_tutkimus_inv_kohteet taulussa inventoijana )
            if($request->inventoija) {
                $kayttajaId = $request->inventoija;
                $kohteet = $kohteet->whereHas('inventointiTutkimukset', function($query) use($kayttajaId) {
                    $query->where('inventoija_id', '=', $kayttajaId);
                });
                /* HUOM Tämä hakee pelkästään tutkimuksen käyttäjistä.
                $kohteet = $kohteet->whereHas('inventointiTutkimukset', function($query) use($kayttajaId) {
                    $query->whereIn('ark_tutkimus.id', function($q) use ($kayttajaId) {
                        $q->select('ark_tutkimus_id')
                        ->from('ark_tutkimus_kayttaja')
                        ->where('kayttaja_id', '=', $kayttajaId)
                        ->whereNull('poistettu');
                    });
                });
                */
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
            //Log::debug(print_r(DB::getQueryLog(), true));

            MipJson::initGeoJsonFeatureCollection(count($kohteet), $total_rows);

            $userArkRole = Auth::user()->ark_rooli;
            foreach ($kohteet as $kohde) {

                if($userArkRole === 'katselija') {
                   unset($kohde->tarkastus_muistiinpano);
                }

                // Jos on inventointitutkimuksia, fiksataan inventoijan nimi mukaan
                foreach ($kohde->inventointitutkimukset as $it){

                    if($it->pivot && $it->pivot->inventoija_id) {
                        $inventoija = Kayttaja::where('id', '=', $it->pivot->inventoija_id)->first();
                        $inventoijaNimi = '';
                        if($inventoija && $inventoija->etunimi && $inventoija->sukunimi) {
                            $inventoijaNimi = $inventoija->sukunimi . " " . $inventoija->etunimi;
                        }
                        $it->pivot->inventoija = $inventoijaNimi;
                    }
                }


                //$properties = clone($kohde);
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $kohde);
                //MipJson::addGeometryCollectionToFeature('Feature', $kohde->sijainnit, $properties);
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

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_kohde.katselu')) {
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
                    $kohde = Kohde::getSingle($id)
                    ->with( array(
                    	'ajoitukset.ajoitus',
                    	'ajoitukset.tarkenne',
                    	'alkuperaisyys',
                    	'hoitotarve',
                    	'laji',
                    	'luoja',
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
                    	'tuhoutumissyy',
                    	'kiinteistotrakennukset',
                        'kiinteistotrakennukset.osoitteet',
                    	'tuhoutumissyy',
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
                        'inventointitutkimukset'
                    ))->first();

                if(!$kohde) {
                	MipJson::setGeoJsonFeature();
                	MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                	MipJson::addMessage(Lang::get('kohde.search_not_found'));
                }

                $properties = clone($kohde);
                unset($properties['ark_kohdelaji_id']);
                unset($properties['rauhoitusluokka_id']);
                unset($properties['alkuperaisyys_id']);
                unset($properties['kunto_id']);
                unset($properties['hoitotarve_id']);
                unset($properties['rajaustarkkuus_id']);
                unset($properties['maastomerkinta_id']);
                unset($properties['tuhoutumissyy_id']);

                //Tarkastuksen muistiinpano -kenttää ei näytetä koskaan pelkille katselijoille
                $userArkRole = Auth::user()->ark_rooli;
                if($userArkRole === 'katselija') {
                    unset($properties['tarkastus_muistiinpano']);
                }

                // Tarkistetaan onko Kypissä tietoja muutettu ja asetetaan vipu proppareihin.
                // Vain kohteet joilla on jo tunnus tarkistetaan
                if($userArkRole === 'tutkija' || $userArkRole === 'pääkäyttäjä') {
                    if( !empty($kohde->muinaisjaannostunnus) ){
                        try {
                            if(self::tarkistaKyppiMuutos($kohde)){
                                $properties->kyppi_updated = true;
                            }
                        } catch (Exception $e) {
                            MipJson::addMessage($e->getMessage());
                        }
                    }
                }

                // Jos on inventointitutkimuksia, fiksataan inventoijan nimi mukaan
                foreach ($kohde->inventointitutkimukset as $it){

                    if($it->pivot && $it->pivot->inventoija_id) {
                        $inventoija = Kayttaja::where('id', '=', $it->pivot->inventoija_id)->first();
                        $inventoijaNimi = '';
                        if($inventoija && $inventoija->etunimi && $inventoija->sukunimi) {
                            $inventoijaNimi = $inventoija->sukunimi . " " . $inventoija->etunimi;
                        }
                        $it->pivot->inventoija = $inventoijaNimi;
                    }
                }

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


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {

        /*
         * Role check
         * Käyttäjällä tulee olla luontioikeus TAI jos käyttäjä on katselija
         *  1. tiedoissa tulee olla mukana inventointitiedot
         *  2. käyttäjän tulee olla inventoijana ko. inventointitutkimuksessa
         */
        $hasPermission = false;

        if(Kayttaja::hasPermission('arkeologia.ark_kohde.luonti')) {
            $hasPermission = true;
        }

        if(!$hasPermission) {
            $aktiivisetInventointitutkimukset = Tutkimus::getAktiivisetInventointitutkimukset(Auth::user()->id);
            $inventointitiedot = $request->all()['properties']['inventointitiedot'];
            //Log::debug($inventointitiedot);

            foreach($aktiivisetInventointitutkimukset as $t) {
                if($t->id == $inventointitiedot['inventointitutkimus_id']) {
                    $hasPermission = true;
                    break;
                }
            }
        }

        if(!$hasPermission) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all(), [
            'properties.nimi'	=> 'required',
        ]);

        if ($validator->fails()) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            foreach($validator->errors()->all() as $error) {
                MipJson::addMessage($error);
                MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            }
            return MipJson::getJson();
        }
        try {
            // wrap the operations into a transaction
            DB::beginTransaction();
            Utils::setDBUser();

            try {

                $kohde = new Kohde($request->all()['properties']);
                $kohde->ark_kohdelaji_id = $request->input('properties.laji.id');
                $kohde->rauhoitusluokka_id = $request->input('properties.rauhoitusluokka.id');
                $kohde->alkuperaisyys_id = $request->input('properties.alkuperaisyys.id');
                $kohde->kunto_id = $request->input('properties.kunto.id');
                $kohde->hoitotarve_id = $request->input('properties.hoitotarve.id');
                $kohde->rajaustarkkuus_id = $request->input('properties.rajaustarkkuus.id');
                $kohde->maastomerkinta_id = $request->input('properties.maastomerkinta.id');

                $author_field = Kohde::CREATED_BY;
                $kohde->$author_field = Auth::user()->id;

                $kohde->save();

                KohdeSijainti::paivita_kohde_sijainnit($kohde->id, $request->input('properties.sijainnit'));
                KohdeAjoitus::paivita_kohde_ajoitukset($kohde->id, $request->input('properties.ajoitukset'));
                KohdeTyyppi::paivita_kohde_tyypit($kohde->id, $request->input('properties.tyypit'));
                KohdeKuntaKyla::paivita_kohde_kunnatkylat($kohde->id, $request->input('properties.kunnatkylat'));
                KohdeSuojelutieto::paivita_kohde_suojelutiedot($kohde->id, $request->input('properties.suojelutiedot'));
                KiinteistoRakennus::paivita_kohde_kiinteistorakennustiedot($kohde->id, $request->input('properties.kiinteistotrakennukset'));
                KohdeVanhaKunta::paivita_kohde_vanhatkunnat($kohde->id, $request->input('properties.vanhat_kunnat'));
                KohdeAlakohde::paivita_kohde_alakohteet($kohde->id, $request->input('properties.alakohteet'));

                if($request->input('properties.inventointitiedot.inventointitutkimus_id')) {
                    //Log::debug("Inventointitiedot ovat!");
                    TutkimusInvKohteet::paivita_kohde($request->input('properties.inventointitiedot'), $kohde->id);
                }

            } catch(Exception $e) {
                Log::error("Kohde tallennus virhe: " . $e);
                // Exception, always rollback the transaction
                DB::rollback();
                throw $e;
            }

            // And commit the transaction as all went well
            DB::commit();

            MipJson::addMessage(Lang::get('kohde.save_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $kohde->id));
            MipJson::setResponseStatus(Response::HTTP_OK);

        } catch(Exception $e) {
            Log::error("Kohde tallennus virhe: " . $e);
            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('kohde.save_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        /*
         * Role check
         * Käyttäjällä tulee olla luontioikeus TAI jos käyttäjä on katselija
         *  1. tiedoissa tulee olla mukana inventointitiedot
         *  2. käyttäjän tulee olla inventoijana ko. inventointitutkimuksessa
         */
        $hasPermission = false;

        if(Kayttaja::hasPermission('arkeologia.ark_kohde.muokkaus')) {
            $hasPermission = true;
        }

        if(!$hasPermission) {
            $aktiivisetInventointitutkimukset = Tutkimus::getAktiivisetInventointitutkimukset(Auth::user()->id);
            $inventointitiedot = $request->all()['properties']['inventointitiedot'];
            //Log::debug($inventointitiedot);

            foreach($aktiivisetInventointitutkimukset as $t) {
                if($t->id == $inventointitiedot['inventointitutkimus_id']) {
                    $hasPermission = true;
                    break;
                }
            }
        }

        if(!$hasPermission) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all(), [
            //'kyla.id' 			=> 'required|numeric|exists:kyla,id',
            //'kiinteistotunnus' 	=> 'required|size:17|regex:(\d{3}-\d{3}-\d{4}-\d{4})', // 123-123-1234-1234
            //'nimi'				=> 'required',
            //'postinumero'		=> 'size:5|regex:/\b\d{5}\b/',
            //"sijainti"			=> "required|regex:/^\d+\.\d+\ \d+\.\d+$/", // 20.123456 60.123456 (lat, lon)
            //"tarkistettu"		=> "date_format:Y-m-d"
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
                $kohde = Kohde::find($id);

                if(!$kohde){
                    //error user not found
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('kohde.search_not_found'));
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                }
                else {
                    // wrap the operations into a transaction
                    DB::beginTransaction();
                    Utils::setDBUser();

                    try {
                    	$kohde->fill($request->all()['properties']);

                    	$kohde->ark_kohdelaji_id = $request->input('properties.laji.id');
                    	$kohde->rauhoitusluokka_id = $request->input('properties.rauhoitusluokka.id');
                    	$kohde->alkuperaisyys_id = $request->input('properties.alkuperaisyys.id');
                    	$kohde->kunto_id = $request->input('properties.kunto.id');
                    	$kohde->hoitotarve_id = $request->input('properties.hoitotarve.id');
                    	$kohde->rajaustarkkuus_id = $request->input('properties.rajaustarkkuus.id');
                    	$kohde->maastomerkinta_id = $request->input('properties.maastomerkinta.id');

                    	KohdeSijainti::paivita_kohde_sijainnit($kohde->id, $request->input('properties.sijainnit'));
                    	KohdeAjoitus::paivita_kohde_ajoitukset($kohde->id, $request->input('properties.ajoitukset'));
                    	KohdeTyyppi::paivita_kohde_tyypit($kohde->id, $request->input('properties.tyypit'));
                    	KohdeKuntaKyla::paivita_kohde_kunnatkylat($kohde->id, $request->input('properties.kunnatkylat'));
                    	KohdeSuojelutieto::paivita_kohde_suojelutiedot($kohde->id, $request->input('properties.suojelutiedot'));
                    	KiinteistoRakennus::paivita_kohde_kiinteistorakennustiedot($kohde->id, $request->input('properties.kiinteistotrakennukset'));
                    	KohdeVanhaKunta::paivita_kohde_vanhatkunnat($kohde->id, $request->input('properties.vanhat_kunnat'));
                    	KohdeAlakohde::paivita_kohde_alakohteet($kohde->id, $request->input('properties.alakohteet'));

                    	if($request->input('properties.inventointitiedot.inventointitutkimus_id')) {
                    	    //Log::debug("Inventointitiedot ovat!");
                    	    TutkimusInvKohteet::paivita_kohde($request->input('properties.inventointitiedot'), $kohde->id);
                    	}

                    	// Kyppi-status päivitetään aina null arvoon tallennuksessa = MIP:ssä uudempi versio
                    	$kohde->kyppi_status = null;

                        $author_field = Kohde::UPDATED_BY;
                        $kohde->$author_field = Auth::user()->id;

                        // Päivittämällä parentin aikaleimaa saadaan lapsitaulujen muutokset muutoshistoriaan helpommin
                        $kohde->touch();
                        $kohde->update();

                    } catch(Exception $e) {
                        // Exception, always rollback the transaction
                        DB::rollback();
                        throw $e;
                    }

                    // And commit the transaction as all went well
                    DB::commit();

                    MipJson::addMessage(Lang::get('kohde.save_success'));
                    MipJson::setGeoJsonFeature(null, array("id" => $kohde->id));
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
                MipJson::setMessages(array(Lang::get('kohde.update_failed'), $e->getMessage()));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_kohde.poisto')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $entity = Kohde::find($id);

        if(!$entity) {
            // return: not found
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kohde.search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }

        try {
            DB::beginTransaction();
            Utils::setDBUser();

            $author_field = Kohde::DELETED_BY;
            $when_field = Kohde::DELETED_AT;
            $entity->$author_field = Auth::user()->id;
            $entity->$when_field = \Carbon\Carbon::now();

            $entity->save();
            // NO NEED FOR ->delete()

            DB::commit();

            MipJson::addMessage(Lang::get('kohde.delete_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

        } catch(Exception $e) {
            // Exception, always rollback the transaction
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('kohde.delete_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }


    // Historiatoiminnallisuus
    public function historia($id) {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_kohde.katselu')) {
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

        return KohdeMuutoshistoria::getById($id);

    }

    /**
     * Hakee kohteet annetun alueen sisältä.
     * Käytetään inventointi-tutkimuksen kohteiden hakuun.
     * Palautetaan vain tarvittavat tiedot.
     */
    public function kohteetPolygon(Request $request) {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_kohde.katselu')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        try {
            // Haetaan kohteet. Suodatetaan tuplat koska yhdellä kohteella voi olla useampi sijainti.
            $kohteet = Kohde::haeAlueenKohteet($request->sijainti)->with(array('sijainnit', 'tyypit.tyyppi', 'laji'))->distinct()->get();

            self::muodostaKohdeDtoLista($kohteet);

            MipJson::addMessage(Lang::get('kohde.search_success'));

        } catch (QueryException $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kohde.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    public function inventointitutkimusKohteet($tutkimusId) {
        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('arkeologia.ark_kohde.katselu')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        try {
            // Haetaan kohteet. Suodatetaan tuplat koska yhdellä kohteella voi olla useampi sijainti.
            $tutkimus = Tutkimus::where('id', $tutkimusId)->first();
            $tutkimusKohteet = $tutkimus->inventointikohteet->get();
            self::muodostaKohdeDtoLista($tutkimusKohteet);

            MipJson::addMessage(Lang::get('kohde.search_success'));

        } catch (QueryException $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kohde.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Muodostaa kohteista feature listan jossa kohteista valittu tarvittavat tiedot.
     */
    private static function muodostaKohdeDtoLista($kohdeLista){

        MipJson::initGeoJsonFeatureCollection(count($kohdeLista));

        // Muodostetaan dtot joilla palautetaan tarvittavat kentät
        foreach ($kohdeLista as $kohde) {
            //Log::debug(print_r($kohde, true));
            $kohdeDto = new \stdClass;
            $kohdeDto->id = $kohde->id;
            $kohdeDto->nimi = $kohde->nimi;
            $kohdeDto->muinaisjaannostunnus = $kohde->muinaisjaannostunnus;
            if($kohde->laji && $kohde->laji->nimi_fi) {
                $kohdeDto->laji = $kohde->laji->nimi_fi;
            }
            $kohdeDto->sijainnit = $kohde->sijainnit;

            if(count($kohde->tyypit) == 0){
                $kohdeDto->tyyppi = 'Ei määritelty';
            } else {
                $kohdeDto->tyyppi = $kohde->tyypit[0]->tyyppi->nimi_fi;
            }

            $properties = clone($kohdeDto);
            MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $properties);
        }
    }

    /**
     * Hakee muinaisjäännöksen Kypistä ja asettaa vivun jonka mukaan UI:ssa näytetään ilmoitus tietojen muuttumisesta.
     * Käyttäjä voi päivittää tiedot, jolloin haetaan kyseisen MJ kaikki tiedot.
     */
    private static function tarkistaKyppiMuutos($kohde){

        $kyppiService = new KyppiService();

        try {
            // True palauttaa pelkän muutospäivän
            $kyppiMuutospvm = $kyppiService->haeMuinaisjaannosSoap($kohde->muinaisjaannostunnus, true);
        } catch (Exception $e) {
            Log::error('Kyppi palautti virheen: ' .$e->getMessage());
            return false;
        }

        // Jos kohde on vasta viety MIP -> Kyppiin, muokkauspäivää ei vielä ole.
        if(!$kyppiMuutospvm) {
            return false;
        }

        // Tällä voi testata tietojen hakua kypistä kohteen avauksessa
        //$kyppiMuutospvm = '2019-05-05';

        // Jos status on 1 = uusi tai 2 = muokattu näytetään ilmoitus muutoksesta
        if ($kohde->kyppi_status != null){
            return true;
        }
        // Jos 'muutettu' päivämäärä on Kypissä isompi kuin kannan kohteen muokattu pvm, näytetään ilmoitus muutoksesta.
        // Tämä skenaario toteutuu jos samana päivänä on MJ-rekisterissä avattavaa kohdetta muokattu.
        if( strtotime($kyppiMuutospvm) > strtotime($kohde->muokattu) ){
            return true;
        }
        return false;

    }
}
