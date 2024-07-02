<?php

namespace App\Http\Controllers\Rak;

use App\Kayttaja;
use App\Kyla;
use App\Utils;
use App\Http\Controllers\Controller;
use App\Library\Gis\MipGis;
use App\Library\String\MipJson;
use App\Rak\Aluetyyppi;
use App\Rak\Arvotustyyppi;
use App\Rak\InventointiprojektiKiinteisto;
use App\Rak\Kiinteisto;
use App\Rak\KiinteistoMuutoshistoria;
use App\Rak\KiinteistoSuojelutyyppi;
use App\Rak\KiinteistonKulttuurihistoriallinenArvo;
use App\Rak\Kuva;
use App\Rak\KuvaKiinteisto;
use App\Rak\Tilatyyppi;
use App\Muistot\Muistot_muisto;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class KiinteistoController extends Controller {

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
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }


        $validator = Validator::make($request->all(), [
            //'kiinteistotunnus' 	=> 'size:17|regex:(\d{3}-\d{3}-\d{4}-\d{4})', // 123-123-1234-1234
            "kiinteistotunnus"	=> "string",
            "id"				=> "numeric|exists:kiinteisto,id",
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
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "kunta";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $kiinteistot = Kiinteisto::getAll();

            // rakennusten osoitteet
            $kiinteistot = $kiinteistot->with( array(
                'rakennusOsoitteet' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
	                if ($jarjestys_kentta == "osoite") {
	                    $query->orderBy("katunimi", $jarjestys_suunta);
	                    $query->orderBy("katunumero", $jarjestys_suunta);
	                }
                },
                'inventoijat_str',
                'inventointiprojektit' => function($query) {
	                $query->withExcludeTechProjects()->select('inventointiprojekti.nimi');
                },
                'luoja'
            ));

            /*
             * If ANY search terms are given limit results by them
             */
            if($request->id) {
                $kiinteistot->withID($request->id);
            }
            if($request->kunta) {
                $kiinteistot->withMunicipality($request->kunta);
            }
            if($request->kuntanumero) {
                $kiinteistot->withMunicipalityNumber($request->kuntanumero);
            }
            if($request->kuntaId) {
                $kiinteistot->withMunicipalityId($request->kuntaId);
            }
            if($request->kyla) {
                $kiinteistot->withVillage($request->kyla);
            }
            if($request->kylanumero) {
                $kiinteistot->withVillageNumber($request->kylanumero);
            }
            if($request->kylaId) {
                $kiinteistot->withVillageId($request->kylaId);
            }
            if($request->nimi) {
                $kiinteistot->withName($request->nimi);
            }
            if($request->osoite) {
                $kiinteistot->withAddress($request->osoite);
            }
            if($request->kiinteistotunnus) {
                $kiinteistot->withPropertyID($request->kiinteistotunnus);
            }
            if($request->aluerajaus) {
                $kiinteistot->withBoundingBox($request->aluerajaus);
            }
            if($request->polygonrajaus) {
                $kiinteistot->withPolygon($request->polygonrajaus);
            }
            if($request->inventointiprojektiId || $request->inventoija) {
                $kiinteistot->withInventointiprojektiOrInventoija($request->inventointiprojektiId, $request->inventoija);
            }
            if($request->palstanumero) {
                $kiinteistot->withPalstanumero($request->palstanumero);
            }
            if($request->arvotus) {
                $kiinteistot->withArvotustyyppiId($request->arvotus);
            }
            if($request->paikkakunta) {
                $kiinteistot->withPaikkakunta($request->paikkakunta);
            }

            if($request->luoja) {
            	$kiinteistot->withLuoja($request->luoja);
            }

            // order the results by given parameters
            $kiinteistot->withOrderBy($request->aluerajaus, $jarjestys_kentta, $jarjestys_suunta);

            // calculate the total rows of the search results
            $total_rows = Utils::getCount($kiinteistot);//count($kiinteistot->get());

            // ID:t listaan ennen rivien rajoitusta
            $kori_id_lista = Utils::getIdLista($kiinteistot);

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
                $properties = clone($kiinteisto);
                unset($properties['sijainti']);
                //$properties->rakennukset = $buildings;
                //$properties->test = $kiinteisto->test;
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($kiinteisto->sijainti), $properties);
            }

            /*
             * Koritoiminnallisuus. Palautetaan id:t listana.
             */
            MipJson::setIdList($kori_id_lista);

            MipJson::addMessage(Lang::get('kiinteisto.search_success'));

        } catch(Exception $e) {
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
    public function show($id) {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
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

                if(Auth::user()->rooli == 'katselija') {
                    //BUG#7270 Lisätään kiinteistölle kulttuurihistorialliset arvot ja arvotustyyppi JOS kiinteistö on julkinen
                    //Kulttuurihistoriallisten arvojen lisääminen $kiinteisto->kulttuurihistoriallisetarvot = $kiinteisto->kulttuurihistoriallisetarvot() tavalla ei
                    //jostain syystä toiminut, siksi toteutus näin päin.
                    $kiinteisto = Kiinteisto::getSingleForKatselija($id)->with(array('kyla', 'kyla.kunta', 'luoja', 'muokkaaja', 'kulttuurihistoriallisetarvot', 'arvotustyyppi'))->first();

                    if(!$kiinteisto->julkinen) {
                        unset($kiinteisto->kulttuurihistoriallisetarvot);
                        unset($kiinteisto->arvotustyyppi);
                    }

                } else {
                    $kiinteisto = Kiinteisto::getSingle($id)
                    ->with( array(
                        'aluetyypit',
                        'arvotustyyppi',
                        'historialliset_tilatyypit',
                        'kulttuurihistoriallisetarvot',
                        'kyla',
                        'kyla.kunta',
                    	'inventointiprojektit',
                        'suojelutiedot',
                        'suojelutiedot.suojelutyyppi',
                        'suojelutiedot.suojelutyyppi.suojelutyyppiryhma',
                        'luoja',
                        'muokkaaja',
                    	'matkaraportit',
                    	'matkaraportit.luoja',
                    	'matkaraportit.syyt'

                    ))->first();
                }

                /*
                 * 'rakennusOsoitteet' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
	                if ($jarjestys_kentta == "osoite") {
	                    $query->orderBy("katunimi", $jarjestys_suunta);
	                    $query->orderBy("katunumero", $jarjestys_suunta);
	                }
                }
                 */

                if($kiinteisto) {

                	/*
                	 * Haetaan jokaiselle inventointiprojektille VIIMEKSI LISÄTTY inventointitieto per inventoija.
                	 * Tehdään tässä, koska ei onnistunut näppärästi query builderilla muualla.
                	 */
                	foreach($kiinteisto->inventointiprojektit as $ip) {
                		$ip->inventoijat = Kiinteisto::inventoijat($kiinteisto->id, $ip->id);
                	}

                    $properties = clone($kiinteisto);
                    unset($properties['sijainti']);

                    MipJson::setGeoJsonFeature(json_decode($kiinteisto->sijainti), $properties);
                    MipJson::addMessage(Lang::get('kiinteisto.search_success'));
                }
                else {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
                }
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('kiinteisto.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Kiinteistöjen haku koriin
     */
    public function kori(Request $request) {

        if(!$request->kori_id_lista){
            MipJson::addMessage(Lang::get('kiinteisto.search_failed'));
            return MipJson::getJson();
        }

        try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 25;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "kunta";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            // Käytetään annettua id listaa tai haetaan tapahtumilta id:t
            $idLista = array();

            $idLista = $request->kori_id_lista;

            for($i = 0; $i<sizeof($idLista); $i++) {
                $idLista[$i] = intval($idLista[$i]);
            }

            $kiinteistot = Kiinteisto::getAll();

            // Suodatus id listan mukaan
            $kiinteistot->withKiinteistoIdLista($idLista);

            // Rivien määrän laskenta
            $total_rows = Utils::getCount($kiinteistot);

            // ID:t listaan ennen rivien rajoitusta
            $kori_id_lista = Utils::getIdLista($kiinteistot);

            // Rivimäärien rajoitus parametrien mukaan
            $kiinteistot->withLimit($rivi, $riveja);

            // Sorttaus
            $kiinteistot->withOrderBy($request->aluerajaus, $jarjestys_kentta, $jarjestys_suunta);

            // suorita query
            $kiinteistot = $kiinteistot->get();

            MipJson::initGeoJsonFeatureCollection(count($kiinteistot), $total_rows);

            foreach ($kiinteistot as $kiinteisto) {
                //Tarkastetaan jokaiselle kiinteistölle kuuluvat oikeudet
                $kiinteisto->oikeudet = Kayttaja::getPermissionsByEntity(null, 'kiinteisto', $kiinteisto->id);

                // kiinteistön lisäys feature listaan
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $kiinteisto);
            }

            /*
             * Koritoiminnallisuus. Palautetaan kiinteistöjen id:t listana.
             */
            MipJson::setIdList($kori_id_lista);

            MipJson::addMessage(Lang::get('kiinteisto.search_success'));
        }
        catch(QueryException $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kiinteisto.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
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
    public function get_buildings($id) {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
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
                $estate = Kiinteisto::find($id);
                
                if($estate) {

                    $buildings = $estate->buildings()->with(array('rakennustyypit', 'osoitteet'))->orderby('inventointinumero')->get();
                    
                    // calculate the total rows of the search results
                    $total_rows = count($buildings);

                    if($total_rows > 0) {
                        MipJson::initGeoJsonFeatureCollection(count($buildings), $total_rows);
                        MipJson::addMessage(Lang::get('rakennus.search_success'));
                        foreach ($buildings as $building) {

                            /*
                             * clone $kiinteisto so we can handle the "properties" separately
                             * -> remove "sijainti" from props
                             */
                            $properties = clone($building);
                            unset($properties['sijainti']);
                            MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($building->sijainti), $properties);
                        }
                    }
                    if(count($buildings) <= 0) {
                        MipJson::setGeoJsonFeature();
                        //MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                        MipJson::addMessage(Lang::get('rakennus.search_not_found'));
                    }
                }
                else {
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('rakennus.search_not_found'));
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                }
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('rakennus.search_failed'));
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
         */
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.luonti')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all(), [
            'kyla.id' 			=> 'required|numeric|exists:kyla,id',
            'kiinteistotunnus' 	=> 'required|size:17|regex:(\d{3}-\d{3}-\d{4}-\d{4})', // 123-123-1234-1234
            "sijainti"			=> "required|regex:/^\d+\.\d+\ \d+\.\d+$/", // 20.123456 60.123456 (lat, lon)
            "tarkistettu"		=> "nullable|date_format:Y-m-d"
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

                // wrap the operations into a transaction
                DB::beginTransaction();
                Utils::setDBUser();

                try {
                    // convert geometry from DB to lat/lon String like: "20.123456789 60.123456789" -> 653452134FDAF523452DF
                    $geom = MipGis::getPointGeometryValue($request->sijainti);

                    $kiinteisto = new Kiinteisto($request->all());
                    $kyla = new Kyla($request->input('kyla'));
                    $kiinteisto->kyla_id = $kyla->id;

                    if ($request->arvotustyyppi) {
                        $arvotustyyppi = new Arvotustyyppi($request->input('arvotustyyppi'));
                        $kiinteisto->arvotustyyppi_id = $arvotustyyppi->id;
                    } else {
                        $kiinteisto->arvotustyyppi_id = null;
                    }

                    $kiinteisto->kiinteiston_sijainti = $geom;

                    $author_field = Kiinteisto::CREATED_BY;
                    $kiinteisto->$author_field = Auth::user()->id;

                    $kiinteisto->save();

                    Tilatyyppi::update_kiinteisto_historiallinentilatyypit($kiinteisto->id, $request->input('historialliset_tilatyypit'));
                    Aluetyyppi::update_kiinteisto_aluetyypit($kiinteisto->id, $request->input('aluetyypit'));
                    KiinteistonKulttuurihistoriallinenArvo::update_kiinteisto_kulttuurihistoriallisetarvot($kiinteisto->id, $request->input('kulttuurihistoriallisetarvot'));
                    KiinteistoSuojelutyyppi::update_kiinteisto_suojelutyypit($kiinteisto->id, $request->input('suojelutiedot'));

                    //Update the related kiinteisto inventointiprojekti
                    if($request->inventointiprojekti) {
                        InventointiprojektiKiinteisto::insertInventointitieto($request->inventointiprojekti, $kiinteisto->id);
                    }
                } catch(Exception $e) {
                    // Exception, always rollback the transaction
                    DB::rollback();
                    throw $e;
                }

                // And commit the transaction as all went well
                DB::commit();

                MipJson::addMessage(Lang::get('kiinteisto.save_success'));
                MipJson::setGeoJsonFeature(null, array("id" => $kiinteisto->id));
                //MipJson::setData(array("id" => $kiinteisto->id));
                MipJson::setResponseStatus(Response::HTTP_OK);
            }
            catch(Exception $e) {
                MipJson::setGeoJsonFeature();
                MipJson::setMessages(array(Lang::get('kiinteisto.save_failed'),$e->getMessage()));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }// else

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
         */
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.muokkaus')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all(), [
            'kyla.id' 			=> 'required|numeric|exists:kyla,id',
            'kiinteistotunnus' 	=> 'required|size:17|regex:(\d{3}-\d{3}-\d{4}-\d{4})', // 123-123-1234-1234
            "sijainti"			=> "required|regex:/^\d+\.\d+\ \d+\.\d+$/", // 20.123456 60.123456 (lat, lon)
            "tarkistettu"		=> "nullable|date_format:Y-m-d"
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
                $entity = Kiinteisto::find($id);

                if(!$entity){
                    //error user not found
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                }
                else {

                    //Old julkinen value
                    $julkinenOld = $entity->julkinen;

                    // wrap the operations into a transaction
                    DB::beginTransaction();
                    Utils::setDBUser();

                    try {
                        // convert geometry from DB to lat/lon String like: "20.123456789 60.123456789"
                        $geom = MipGis::getPointGeometryValue($request->sijainti);

                        $entity->fill($request->all());
                        if ($request->kyla) {
                            $kyla = new Kyla($request->input('kyla'));
                            $entity->kyla_id = $kyla->id;
                        }

                        if ($request->arvotustyyppi) {
                            $arvotustyyppi = new Arvotustyyppi($request->input('arvotustyyppi'));
                            $entity->arvotustyyppi_id = $arvotustyyppi->id;
                        } else {
                            $entity->arvotustyyppi_id = null;
                        }

                        $entity->kiinteiston_sijainti = $geom;

                        $author_field = Kiinteisto::UPDATED_BY;
                        $entity->$author_field = Auth::user()->id;

                        $entity->save();

                        Tilatyyppi::update_kiinteisto_historiallinentilatyypit($entity->id, $request->input('historialliset_tilatyypit'));
                        Aluetyyppi::update_kiinteisto_aluetyypit($entity->id, $request->input('aluetyypit'));
                        KiinteistonKulttuurihistoriallinenArvo::update_kiinteisto_kulttuurihistoriallisetarvot($entity->id, $request->input('kulttuurihistoriallisetarvot'));
                        KiinteistoSuojelutyyppi::update_kiinteisto_suojelutyypit($entity->id, $request->input('suojelutiedot'));

                        //Update the related kiinteisto inventointiprojekti
                        if($request->inventointiprojekti) {
                            InventointiprojektiKiinteisto::insertInventointitieto($request->inventointiprojekti, $entity->id);
                        }

                        $julkinenNew = $request->julkinen;
                        //Update the related images julkinen value accordingly IF the value has changed.
                        if($julkinenNew != $julkinenOld) {
                            if($julkinenNew == true) {
                                //Set the first image to julkinen
                                $imgRef = KuvaKiinteisto::getAll($id)->orderBy('jarjestys', 'ASC')->first();
                                if($imgRef) {
                                    $img = Kuva::getSingle($imgRef->kuva_id)->first();

                                    if($img != null) {
                                        $img->julkinen = true;
                                        $img->update();
                                    }
                                }
                            } else {
                                //Set all of the images to not julkinen
                                $imgRefs = KuvaKiinteisto::getAll($id)->get();
                                foreach($imgRefs as $imgRef) {
                                    $img = Kuva::getSingle($imgRef->kuva_id)->first();

                                    if($img != null) {
                                        $img->julkinen = false;
                                        $img->update();
                                    }
                                }
                            }
                        }

                        /*
                         * Jos pyynnössä tulee poistettavia InventointiprojektiKiinteisto-rivejä, merkitään rivit kannasta poistetuiksi
                         */
                        if($request->inventointiprojektiDeleteList && count($request->inventointiprojektiDeleteList)>0){
                            foreach($request->inventointiprojektiDeleteList as $ip){
                            	InventointiprojektiKiinteisto::deleteInventointitieto($ip['inventointiprojektiId'], $entity->id, $ip['inventoijaId']);
                            }
                        }

                    } catch(Exception $e) {
                        // Exception, always rollback the transaction
                        DB::rollback();
                        throw $e;
                    }

                    // And commit the transaction as all went well
                    DB::commit();

                    MipJson::addMessage(Lang::get('kiinteisto.save_success'));
                    MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
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
                MipJson::setMessages(array(Lang::get('kiinteisto.update_failed'),$e->getMessage()));
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
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.poisto')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $entity = Kiinteisto::find($id);

        if(!$entity) {
            // return: not found
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
        }


        try {
            DB::beginTransaction();
            Utils::setDBUser();

            $author_field = Kiinteisto::DELETED_BY;
            $when_field = Kiinteisto::DELETED_AT;
            $entity->$author_field = Auth::user()->id;
            $entity->$when_field = \Carbon\Carbon::now();

            $entity->save();
            // NO NEED FOR ->delete()

            DB::commit();

            MipJson::addMessage(Lang::get('kiinteisto.delete_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

        } catch(Exception $e) {
            // Exception, always rollback the transaction
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::setMessages(array(Lang::get('kiinteisto.delete_failed'),$e->getMessage()));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Method to get all inventoring projects that this Estate belongs to
     *
     * @param int $id
     * @return MipJson
     * @version 1.0
     * @since 1.0
     */
    public function get_inventoringprojects($id) {
        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
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
                $kiinteisto = Kiinteisto::getSingle($id)->first();

                if($kiinteisto) {
                    $inventoring_projects = $kiinteisto->inventoringProjects()->get();

                    MipJson::initGeoJsonFeatureCollection(count($inventoring_projects), count($inventoring_projects));
                    foreach($inventoring_projects as $project) {

                        $project->inventoija_nimi = ($project->etunimi || $project->sukunimi) ? $project->sukunimi." ".$project->etunimi : $project->inventoija_nimi = $project->pivot->inventoija_nimi;
                        $project->inventoija_id = $project->pivot->inventoija_id;
                        $project->kenttapaiva = $project->pivot->kenttapaiva;
                        $project->inventointipaiva = $project->pivot->inventointipaiva;
                        $project->inventointipaiva_tekstina = $project->pivot->inventointipaiva_tekstina;
                        unset($project->pivot, $project->etunimi, $project->sukunimi);
                        MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $project);
                    }
                    MipJson::addMessage(Lang::get('inventointiprojekti.search_success'));
                }
                else {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
                }
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('inventointiprojekti.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Method to add new relation between given inventoring project and given estate
     *
     * @param int $estate_id
     * @param Request $request
     * @return MipJson
     * @version 1.0
     * @since 1.0
     */
    public function add_inventoringproject($estate_id, Request $request) {
        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.muokkaus')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }
        $validator = Validator::make($request->all(), [
            'inventointiprojekti_id' 	=> 'required|numeric|exists:inventointiprojekti,id',
            'inventoija_id'				=> 'required|numeric|exists:kayttaja,id',
            'inventointipaiva'			=> 'required|date_format:"Y-m-d"',
            'kenttapaiva'				=> 'required|date_format:"Y-m-d"'
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
                $kiinteisto = Kiinteisto::getSingle($estate_id)->first();

                if($kiinteisto) {
                    $relation_data = array("inventointipaiva" => $request->inventointipaiva, "kenttapaiva" => $request->kenttapaiva, "inventoija_id" => $request->inventoija_id);
                    $inventoring_projects = $kiinteisto->inventoringProjects()->attach($request->inventointiprojekti_id, $relation_data);
                    MipJson::addMessage(Lang::get('inventointiprojekti.save_success'));
                    MipJson::setGeoJsonFeature(null, array("kiinteisto_id" => $kiinteisto->id, "inventointiprojekti_id" => $request->id));
                    MipJson::setResponseStatus(Response::HTTP_OK);
                }
                else {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
                }
            }
            catch(Exception $e) {
                MipJson::setGeoJsonFeature();
                MipJson::setMessages(array(Lang::get('inventointiprojekti.save_failed'),$e->getMessage()));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return MipJson::getJson();
    }

    /**
     * Method to delete relation between given estate and given inventoringproject
     *
     * @param int $estate_id
     * @param Request $request
     * @return MipJson
     * @version 1.0
     * @since 1.0
     */
    public function delete_inventoringproject($estate_id, Request $request) {
        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.muokkaus')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }
        $validator = Validator::make($request->all(), [
            'inventointiprojekti_id' 			=> 'required|numeric|exists:inventointiprojekti,id',
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
                $kiinteisto = Kiinteisto::getSingle($estate_id)->first();

                if($kiinteisto) {
                    $inventoring_projects = $kiinteisto->inventoringProjects()->detach($request->inventointiprojekti_id);
                    MipJson::addMessage(Lang::get('inventointiprojekti.save_success'));
                    MipJson::setGeoJsonFeature(null, array("kiinteisto_id" => $kiinteisto->id, "inventointiprojekti_id" => $request->id));
                    MipJson::setResponseStatus(Response::HTTP_OK);
                }
                else {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
                }
            }
            catch(Exception $e) {
                MipJson::setGeoJsonFeature();
                MipJson::setMessages(array(Lang::get('inventointiprojekti.save_failed'),$e->getMessage()));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return MipJson::getJson();
    }

    public function listKiinteistoImages(Request $request, $id) {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        if(!is_numeric($id)) {
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
        }
        else {
            try {

                $validator = Validator::make($request->all(), [
                    'rivi'				=> 'numeric',
                    'rivit'				=> 'numeric',
                    'jarjestys'			=> 'string',
                    'jarjestys_suunta'	=> 'string',
                ]);

                if ($validator->fails()) {
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
                    foreach($validator->errors()->all() as $error)
                        MipJson::addMessage($error);
                        MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
                }
                else {

                    /*
                     * Initialize the variables that needs to be "reformatted"
                     */
                    $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
                    $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
                    $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
                    $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

                    $kiinteisto = Kiinteisto::find($id);

                    if($kiinteisto) {

                        $entities = $kiinteisto->images()->orderby('kuva.'.$jarjestys_kentta, $jarjestys_suunta)->get();
                        $total_rows = count($entities);

                        if($total_rows <= 0) {
                            MipJson::setGeoJsonFeature();
                            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                            MipJson::addMessage(Lang::get('kuva.search_not_found'));
                        }
                        else {
                            MipJson::initGeoJsonFeatureCollection(intval($riveja), $total_rows);
                            MipJson::addMessage(Lang::get('kuva.search_success'));
                            $row_counter = 0;
                            foreach ($entities as $entity) {
                                if($row_counter >= $rivi && $row_counter <= $row_counter+$riveja) {

                                    $images = Kuva::getImageUrls($entity->polku.$entity->nimi);
                                    $entity->url = $images->original;
                                    $entity->url_tiny = $images->tiny;
                                    $entity->url_small = $images->small;
                                    $entity->url_medium = $images->medium;
                                    $entity->url_large = $images->large;

                                    MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
                                }
                                $row_counter++;
                            }
                        }
                    }
                    else {
                        MipJson::setGeoJsonFeature();
                        MipJson::addMessage(Lang::get('alue.search_not_found'));
                        //MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    }
                }
            } catch(QueryException $e) {
                MipJson::addMessage(Lang::get('alue.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    public function updateKiinteistoImages(Request $request, $id) {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.muokkaus')) {
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
                MipJson::setGeoJsonFeature();

                /*
                 * Note:
                 * PATCH endpoint requires data in BODY of a request
                 * This endpoint expects data to be given in BODY of request in format:
                 * [{"op":"add","items":[1,2,3]},{"op":"remove","items":[6,7,8]}]
                 */
                $items = array("no data delivered!");
                if($request->getContent()) {
                    $items = json_decode($request->getContent());

                    foreach ($items as $item) {
                        foreach ($item->items as $item_id) {
                            if($item->op == "remove")
                                Kiinteisto::find($id)->images()->detach($item_id);
                                else if($item->op == "add") {
                                    if(Kuva::find($item_id)) {
                                        $exists = DB::select("SELECT * FROM kuva_kiinteisto WHERE kuva_id = ? AND kiinteisto_id = ?", [$item_id, $id]);
                                        if(count($exists) == 0) {
                                            Kiinteisto::find($id)->images()->attach($item_id);
                                        }
                                    }
                                }
                        }
                    }

                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('kuva.attach_success'));
                }
                else {
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage(Lang::get('kuva.attach_failed'));
                    MipJson::addMessage(Lang::get('kuva.patch_body_missing'));
                    MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
                }

            } catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('alue.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    public function historia($id) {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
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

        return KiinteistoMuutoshistoria::getById($id);

    }

    /*
     * Check availability of the kiinteistotunnus.
     * If the kiinteistotunnus is not available, get the first
     * available column number for the kiinteisto
     */
    public function checkAvailability(Request $request) {



        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all(), [
            "kiinteistotunnus"	=> "required|string",
            "id"				=> "numeric|exists:kiinteisto,id",
            "pn"				=> "numeric"
        ]);

        function checkKiinteistoTunnus($request) {
            $kt = $request->kiinteistotunnus;
            $id = $request->id;
            $pn = $request->palstanumero;
            $kiinteisto = null;

            if($id){
                $kiinteisto = Kiinteisto::GetSingle($id)->first();
            }
            //Case kiinteistötunnus: Jos annetaan pelkästään kiinteistötunnus, tarkastetaan onko se vapaa.
            //Jos on palatetaan valid, jos ei ole palautetaan invalid ja seuraava vapaa palstanumero
            if($kt) {
                //Sallitaan sama kt
                if($kiinteisto != null && $kt == $kiinteisto->kiinteistotunnus) {
                    //Jos muita kiinteistöjä ei ole
                    $count = DB::table('kiinteisto')->where('kiinteistotunnus', '=', $request->kiinteistotunnus)->whereNull(Kiinteisto::DELETED_AT)->count();
                    if($count > 1) {
                        //Muita samalla ktlla on, annetaan seuraava vapaa palstanumero
                        $freePalstanumero = Kiinteisto::getNextFreePalstanumero($kt);//The next free palstanumero

                        if($kiinteisto->palstanumero) {
                            $freePalstanumero = Kiinteisto::getNextFreePalstanumero($kt);//The next free palstanumero
                            MipJson::setGeoJsonFeature(null, array("valid" => false, "kiinteistotunnus" => $kt, "palstanumero" => $freePalstanumero));
                        } else {
                            $freePalstanumero = Kiinteisto::getNextFreePalstanumero($kt);//The next free palstanumero
                            MipJson::setGeoJsonFeature(null, array("valid" => false, "kiinteistotunnus" => $kt, "palstanumero" => $freePalstanumero));
                        }
                    } else {
                        MipJson::setGeoJsonFeature(null, array("valid" => true, "kiinteistotunnus" => $kt, "palstanumero" => null));
                    }
                } else {
                    //Tarkastetaan onko ehdotettu kt vapaa
                    $count = DB::table('kiinteisto')->where('kiinteistotunnus', '=', $request->kiinteistotunnus)->whereNull(Kiinteisto::DELETED_AT)->count();

                    if($count > 0) {
                        //Ei ole vapaa, annetaan seuraava vapaa palstanumero
                        $freePalstanumero = Kiinteisto::getNextFreePalstanumero($kt);//The next free palstanumero
                        MipJson::setGeoJsonFeature(null, array("valid" => false, "kiinteistotunnus" => $kt, "palstanumero" => $freePalstanumero));
                    } else {
                        //Vapaa, palautetaan valid ja kt
                        MipJson::setGeoJsonFeature(null, array("valid" => true, "kiinteistotunnus" => $kt, "palstanumero" => null));
                    }
                }
            }
        }

        function checkPalstanumero($request) {
            $kt = $request->kiinteistotunnus;
            $id = $request->id;
            $pn = $request->palstanumero;
            $kiinteisto = null;

            if($id) {
                $kiinteisto = Kiinteisto::GetSingle($id)->first();
            }

            if($pn) {
                //Sallitaan sama kt + pn
                if($kiinteisto != null && $kt == $kiinteisto->kiinteistotunnus && $pn == $kiinteisto->palstanumero) {
                    MipJson::setGeoJsonFeature(null, array("valid" => true, "kiinteistotunnus" => $kt, "palstanumero" => $pn));
                } else {
                    //Muuttuneet, tarkastetaan onko $kt + $pn yhdistelmä vapaa
                    $count = DB::table('kiinteisto')->where('kiinteistotunnus', '=', $kt)->where('palstanumero', '=', $pn)->whereNull(Kiinteisto::DELETED_AT)->count();

                    if($count > 0) {
                        //Ei ole vapaa, annetaan seuraava palstanumero
                        $freePalstanumero = Kiinteisto::getNextFreePalstanumero($kt); //The next free palstanumero
                        MipJson::setGeoJsonFeature(null, array("valid" => false, "kiinteistotunnus" => $kt, "palstanumero" => $freePalstanumero));
                    } else {
                        //On vapaa, palautetaan ehdotettu kt + pn
                        MipJson::setGeoJsonFeature(null, array("valid" => true, "kiinteistotunnus" => $kt, "palstanumero" => $pn));
                    }
                }
            }
        }

        try {
            checkKiinteistotunnus($request);
            checkPalstanumero($request);

            MipJson::setResponseStatus(Response::HTTP_OK);
        } catch(Exception $ex) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kiinteisto.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();
    }

    /**
     * Haku kiinteistötunnuksella.
     * Haetaan ensin tunnuksella kiinteistön id ja sillä käytetään show fuktiota.
     */
    function getByIdentifier($kiinteistotunnus) {

        try {
            $kiinteisto = DB::table('kiinteisto')->select('id')->where('kiinteistotunnus', '=', $kiinteistotunnus)->first();

            if($kiinteisto){
                // Normaali haku id mukaan
                return self::show($kiinteisto->id);
            }else{
               // Kiinteistöä ei löydy MIP:stä
               MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
               MipJson::setGeoJsonFeature();
               MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            }

        } catch(Exception $ex) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kiinteisto.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
      }

    /**
     * Get memories. Should NOT be called, if Muistot is not part of the MIP-system!
     */
    function getMemories($id) {

        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('muistot.muisto.katselu')) {
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
                $kiinteisto = Kiinteisto::getSingle($id)->first();

                if($kiinteisto) {
                    $query = $kiinteisto->memories();
                    $query->where('poistettu', false);
                    $query->where('ilmiannettu', false);
                  
                    if(!Kayttaja::hasPermission('muistot.yksityinen_muisto.katselu')) {
                        $query->where('julkinen', true);
                        $query->with('muistot_henkilo_filtered');
                    } else {
                        $query->with('muistot_henkilo');
                    }
                    
                    $query->with('muistot_aihe');                    
                    $memories = $query->get();
                  
                    $count = ($memories) ? count($memories) : 0;
                    if ($count > 0) {
                      MipJson::initGeoJsonFeatureCollection(count($memories), count($memories));
                      foreach($memories as $memory) {
                          if($memory->muistot_henkilo_filtered)
                          {
                              $memory->muistot_henkilo = $memory->muistot_henkilo_filtered;
                          }

                          MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $memory);
                      }
                    }
                    MipJson::addMessage(Lang::get('kiinteisto.search_success'));
                }
                else {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
                }
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('kiinteisto.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();

    }
}
