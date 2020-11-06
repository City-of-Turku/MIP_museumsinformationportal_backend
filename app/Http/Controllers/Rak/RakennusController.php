<?php

namespace App\Http\Controllers\Rak;

use App\Kayttaja;
use App\Utils;
use App\Http\Controllers\Controller;
use App\Library\Gis\MipGis;
use App\Library\String\MipJson;
use App\Rak\Arvotustyyppi;
use App\Rak\InventointiprojektiKiinteisto;
use App\Rak\Katetyyppi;
use App\Rak\Kattotyyppi;
use App\Rak\Kayttotarkoitus;
use App\Rak\Kuntotyyppi;
use App\Rak\Kuva;
use App\Rak\Perustustyyppi;
use App\Rak\RakennuksenKulttuurihistoriallinenArvo;
use App\Rak\Rakennus;
use App\Rak\RakennusMuutoshistoria;
use App\Rak\RakennusMuutosvuosi;
use App\Rak\RakennusOmistaja;
use App\Rak\RakennusOsoite;
use App\Rak\RakennusSuojelutyyppi;
use App\Rak\Rakennustyyppi;
use App\Rak\Runkotyyppi;
use App\Rak\SuunnittelijaRakennus;
use App\Rak\Tyylisuunta;
use App\Rak\Vuoraustyyppi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class RakennusController extends Controller {

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
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$validator = Validator::make($request->all(), [
			'kiinteisto_id'			=> 'numeric|exists:kiinteisto,id',
			'sijainti'				=> 'regex:/^\d+\.\d+\ \d+\.\d+$/', // 20.123456 60.123456 (lat, lon)
			'inventointinumero' 	=> 'string',
			'rakennustunnus'		=> 'string',
			'kiinteistotunnus'		=> 'string',
			"kiinteisto_nimi"		=> 'string',
			"kiinteisto_osoite"		=> "string",
			"id"					=> "numeric|exists:rakennus,id",
			"suunnittelija"			=> "string",
			//"aluerajaus"		=> "string|regex:/^\d+\.\d+\ \d+\.\d+,\d+\.\d+\ \d+\.\d+$/",
		]);

		if ($validator->fails()) {
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			foreach($validator->errors()->all() as $error)
				MipJson::addMessage($error);
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
		}
		else {
			try {

				/*
				 * Initialize the variables that needs to be "reformatted"
				 */
				$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
				$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
				$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
				$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

				$joins = [];
				if($request->muutosvuosi_alku || $request->muutosvuosi_lopetus) {
					array_push($joins, 'muutosvuosi');
				}


				$entities = Rakennus::getAll();

				// include all related entities needed here
				$entities = $entities->with( array(
					'rakennustyypit' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
						if ($jarjestys_kentta == "rakennustyyppi") {
							$query->orderBy("nimi_fi", $jarjestys_suunta);
						}
					},
					'osoitteet' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
						if ($jarjestys_kentta == "osoite") {
							$query->orderBy("katunimi", $jarjestys_suunta);
							$query->orderBy("katunumero", $jarjestys_suunta);
						}
					},
					'kiinteisto' => function($query) { $query->select('id', 'kiinteistotunnus', 'nimi', 'osoite', 'postinumero', 'palstanumero' ,'kyla_id', 'paikkakunta'); },
					'kiinteisto.kyla' => function($query) { $query->select('id', 'nimi', 'nimi_se', 'kunta_id', 'kylanumero'); },
					'kiinteisto.kyla.kunta' => function($query) { $query->select('id', 'nimi', 'nimi_se', 'kuntanumero'); },
					'luoja'
				));


				/*
				 * If ANY search terms are given limit results by them
				 */
				if($request->id)
					$entities->withID($request->id);
				if($request->kiinteisto_id)
					$entities->withPropertyID($request->kiinteisto_id);
				if($request->kiinteisto_nimi)
					$entities->withEstateName($request->kiinteisto_nimi);
				if($request->kiinteistotunnus)
					$entities->withEstateIdentifier($request->kiinteistotunnus);
				if($request->rakennustunnus)
					$entities->withBuildingIdentifier($request->rakennustunnus);
				if($request->rakennustyyppi)
					$entities->withBuildingTypeId($request->rakennustyyppi);
				if($request->suunnittelija)
					$entities->withDesigner($request->suunnittelija);
				if($request->kunta)
					$entities->withMunicipality($request->kunta);
				if($request->kuntaId)
				    $entities->withKuntaId($request->kuntaId);
				if($request->kuntanumero)
					$entities->withMunicipalityNumber($request->kuntanumero);
				if($request->kyla)
					$entities->withVillage($request->kyla);
				if($request->kylaId)
				    $entities->withVillageId($request->kylaId);
				if($request->kylanumero)
					$entities->withVillageNumber($request->kylanumero);
				if($request->paikkakunta)
					$entities->withPaikkakunta($request->paikkakunta);
				if($request->rakennus_osoite)
					$entities->withAddress($request->rakennus_osoite);
				if($request->palstanumero)
				    $entities->withPalstanumero($request->palstanumero);
				if($request->aluerajaus)
					$entities->withBoundingBox($request->aluerajaus);
				if($request->polygonrajaus) {
					$entities->withPolygon($request->polygonrajaus);
				}
				if($request->inventointiprojektiId || $request->inventoija) {
					$entities->withInventointiprojektiOrInventoija($request->inventointiprojektiId, $request->inventoija);
				}
				if($request->arvotus) {
				    $entities->withArvotustyyppiId($request->arvotus);
				}
				if($request->luoja) {
					$entities->withLuoja($request->luoja);
				}
				if($request->rakennustyypin_kuvaus) {
					$entities->withRakennustyypin_kuvaus($request->rakennustyypin_kuvaus);
				}
				if($request->rakennusvuosi_alku && $request->rakennusvuosi_lopetus){
				    $entities->withRakennusvuosi_aikajakso($request->rakennusvuosi_alku, $request->rakennusvuosi_lopetus);
				}else{
				    if($request->rakennusvuosi_alku) {
				        $entities->withRakennusvuosi_alku($request->rakennusvuosi_alku);
				    }
				    if($request->rakennusvuosi_lopetus) {
				        $entities->withRakennusvuosi_lopetus($request->rakennusvuosi_lopetus);
				    }
				}
				if($request->muutosvuosi_alku) {
					$entities->withMuutosvuosi_alku($request->muutosvuosi_alku);
					$entities->with(['muutosvuodet']);
				}
				if($request->muutosvuosi_lopetus) {
					$entities->withMuutosvuosi_lopetus($request->muutosvuosi_lopetus);
					$entities->with(['muutosvuodet']);
				}
				if($request->rakennusvuosi_kuvaus) {
					$entities->withRakennusvuosi_kuvaus($request->rakennusvuosi_kuvaus);
				}
				if($request->muutosvuosi_kuvaus) {
					$entities->withMuutosvuosi_kuvaus($request->muutosvuosi_kuvaus);
					$entities->with(['muutosvuodet']);
				}
				if($request->alkuperainen_kaytto) {
					$entities->withAlkuperainenkaytto($request->alkuperainen_kaytto);
					$entities->with(['alkuperaisetkaytot']);
				}
				if($request->nykykaytto) {
					$entities->withNykykaytto($request->nykykaytto);
					$entities->with(['nykykaytot']);
				}
				if($request->perustus) {
					$entities->withPerustus($request->perustus);
					$entities->with(['perustustyypit']);
				}
				if($request->runko) {
					$entities->withRunko($request->runko);
					$entities->with(['runkotyypit']);
				}
				if($request->vuoraus) {
					$entities->withVuoraus($request->vuoraus);
					$entities->with(['vuoraustyypit']);
				}
				if($request->katto) {
					$entities->withKatto($request->katto);
					$entities->with(['kattotyypit']);
				}
				if($request->kate) {
					$entities->withKate($request->kate);
					$entities->with(['katetyypit']);
				}
				if($request->kunto) {
					$entities->withKunto($request->kunto);
					$entities->with(['kuntotyyppi']);
				}
				if($request->nykytyyli) {
					$entities->withNykytyyli($request->nykytyyli);
					$entities->with(['nykyinentyyli']);
				}
				if($request->purettu) {
					$entities->withPurettu($request->purettu);
				}
				if($request->kulttuurihistorialliset_arvot) {
					$entities->withKulttuurihistorialliset_arvot($request->kulttuurihistorialliset_arvot);
					$entities->with(['kulttuurihistoriallisetarvot']);
				}
				if($request->kuvaukset) {
					$entities->withKuvaukset($request->kuvaukset);
				}

				// calculate the total rows of the search results
				//$total_rows = $entities->count();
				$total_rows = Utils::getCount($entities);

				// ID:t listaan ennen rivien rajoitusta
				$kori_id_lista = Utils::getIdLista($entities);

		    	if($jarjestys_kentta=="bbox_center" && $request->aluerajaus != null) {
		    		$entities->withBoundingBoxOrder($request->aluerajaus);
		    	} else {
	    			$entities->withOrder($jarjestys_kentta, $jarjestys_suunta);
		    	}

		    	// limit the results rows by given params
		    	$entities->withLimit($rivi, $riveja);

		    	// Execute the query
		    	$entities = $entities->get();

				MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
		    	foreach ($entities as $entity) {
		    		$properties = clone($entity);
		    		unset($properties['sijainti']);

		    		MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($entity->sijainti), $properties);
		    	}

		    	/*
		    	 * Koritoiminnallisuus. Palautetaan id:t listana.
		    	 */
		    	MipJson::setIdList($kori_id_lista);

		    	MipJson::addMessage(Lang::get('rakennus.search_success'));

			} catch(Exception $e) {
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
	public function show($id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.katselu')) {
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

				$entity = Rakennus::getSingle($id)->with('luoja')->with('muokkaaja')
				->with( array(
					'kuntotyyppi',
					'arvotustyyppi',
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
					'kiinteisto',
					'kiinteisto.kyla',
					'kiinteisto.kyla.kunta',
					'suojelutiedot',
					'suojelutiedot.suojelutyyppi',
					'suojelutiedot.suojelutyyppi.suojelutyyppiryhma',
					'muutosvuodet',
					'osoitteet' => function($query) { $query->orderBy('jarjestysnumero'); },
					'omistajat',
					'suunnittelijat.suunnittelijatyyppi',
					'suunnittelijat.suunnittelija.laji',
					'suunnittelijat.suunnittelija.ammattiarvo'
				))->first();

				if($entity) {
					$properties = clone($entity);
					// remove the sijainti as it will be in the "geometry" section of the json
					unset($properties['sijainti']);

					MipJson::setGeoJsonFeature(json_decode($entity->sijainti), $properties);
					MipJson::addMessage(Lang::get('rakennus.search_success'));

				} else {
					MipJson::setGeoJsonFeature();
					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					MipJson::addMessage(Lang::get('rakennus.search_not_found'));
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
	 * Rakennusten haku koriin
	 */
	public function kori(Request $request) {

	    if(!$request->kori_id_lista){
	        MipJson::addMessage(Lang::get('rakennus.search_failed'));
	        return MipJson::getJson();
	    }

	    try {
	        $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
	        $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 25;
	        $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
	        $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

	        // Käytetään annettua id listaa tai haetaan tapahtumilta id:t
	        $idLista = array();

	        $idLista = $request->kori_id_lista;

	        for($i = 0; $i<sizeof($idLista); $i++) {
	            $idLista[$i] = intval($idLista[$i]);
	        }

	        $rakennukset = Rakennus::getAll();

	        // include all related entities needed here
	        $rakennukset = $rakennukset->with( array(
	            'rakennustyypit' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
	            if ($jarjestys_kentta == "rakennustyyppi") {
	                $query->orderBy("nimi_fi", $jarjestys_suunta);
	            }
	            },
	            'osoitteet' => function($query) use ($jarjestys_kentta, $jarjestys_suunta) {
	            if ($jarjestys_kentta == "osoite") {
	                $query->orderBy("katunimi", $jarjestys_suunta);
	                $query->orderBy("katunumero", $jarjestys_suunta);
	            }
	            },
	            'kiinteisto' => function($query) { $query->select('id', 'kiinteistotunnus', 'nimi', 'osoite', 'postinumero', 'palstanumero' ,'kyla_id', 'paikkakunta'); },
	            'kiinteisto.kyla' => function($query) { $query->select('id', 'nimi', 'nimi_se', 'kunta_id', 'kylanumero'); },
	            'kiinteisto.kyla.kunta' => function($query) { $query->select('id', 'nimi', 'nimi_se', 'kuntanumero'); },
	            'luoja'
	                ));

	        // Suodatus id listan mukaan
	        $rakennukset->withRakennusIdLista($idLista);

	        // Rivien määrän laskenta
	        $total_rows = Utils::getCount($rakennukset);

	        // ID:t listaan ennen rivien rajoitusta
	        $kori_id_lista = Utils::getIdLista($rakennukset);

	        // Rivimäärien rajoitus parametrien mukaan
	        $rakennukset->withLimit($rivi, $riveja);

	        // Sorttaus
	        $rakennukset->withOrder($jarjestys_kentta, $jarjestys_suunta);

	        // suorita query
	        $rakennukset = $rakennukset->get();

	        MipJson::initGeoJsonFeatureCollection(count($rakennukset), $total_rows);

	        foreach ($rakennukset as $rakennus) {
	            //Tarkastetaan jokaiselle rakennukselle kuuluvat oikeudet
	            $rakennus->oikeudet = Kayttaja::getPermissionsByEntity(null, 'rakennus', $rakennus->id);
	            MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $rakennus);
	        }

	        /*
	         * Koritoiminnallisuus. Palautetaan kiinteistöjen id:t listana.
	         */
	        MipJson::setIdList($kori_id_lista);

	        MipJson::addMessage(Lang::get('rakennus.search_success'));
	    }
	    catch(QueryException $e) {
	        MipJson::setGeoJsonFeature();
	        MipJson::addMessage(Lang::get('rakennus.search_failed'));
	        MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
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
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.luonti')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$validator = Validator::make($request->all(), [
			'kiinteisto.id'			=> 'required|numeric|exists:kiinteisto,id',
			'inventointinumero'		=> 'required|numeric',
			'rakennustunnus'		=> 'string',
			'sijainti'				=> 'required|regex:/^\d+\.\d+\ \d+\.\d+$/', // 20.123456 60.123456 (lat, lon)
			'purettu'				=> 'required',
			'nykyinen_tyyli.id'		=> 'numeric|exists:tyylisuunta,id',
			'rakennusvuosi_alku'	=> 'numeric',
			'rakennusvuosi_loppu'	=> 'numeric',
			'muutosvuodet.alkuvuosi' => 'integer|min:0',
			'muutosvuodet.loppuvuosi' => 'integer|min:0',
			'osoitteet.katunimi'	=> 'string'

				// TODO CHECKS!
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
					// convert geometry from DB to lat/lon String like: "20.123456789 60.123456789"
					$geom = MipGis::getPointGeometryValue($request->sijainti);

					$entity = new Rakennus($request->all());
					$entity->rakennuksen_sijainti = $geom;

					$author_field = Rakennus::CREATED_BY;
					$entity->$author_field = Auth::user()->id;

					if ($request->kiinteisto) {
						$entity->kiinteisto_id = $request->kiinteisto['id'];
					}

					// select lists: tyylisuunta
					if ($request->nykyinentyyli) {
						$tyylisuunta = new Tyylisuunta($request->input('nykyinentyyli'));
						$entity->nykyinen_tyyli_id = $tyylisuunta->id;
					} else {
						$entity->nykyinen_tyyli_id = null;
					}
					// select lists: kunto
					if ($request->kuntotyyppi) {
						$kunto = new Kuntotyyppi($request->input('kuntotyyppi'));
						$entity->kuntotyyppi_id = $kunto->id;
					} else {
						$entity->kuntotyyppi_id = null;
					}
					// select lists: arvotus
					if ($request->arvotustyyppi) {
						$arvotus = new Arvotustyyppi($request->input('arvotustyyppi'));
						$entity->arvotustyyppi_id = $arvotus->id;
					} else {
						$entity->arvotustyyppi_id = null;
					}

					$entity->save();

					// multiselects
					Kattotyyppi::update_rakennus_kattotyypit($entity->id, $request->input('kattotyypit'));
					Rakennustyyppi::update_rakennus_rakennustyypit($entity->id, $request->input('rakennustyypit'));
					Kayttotarkoitus::update_rakennus_alkuperaisetkaytot($entity->id, $request->input('alkuperaisetkaytot'));
					Kayttotarkoitus::update_rakennus_nykykaytot($entity->id, $request->input('nykykaytot'));
					Perustustyyppi::update_rakennus_perustustyypit($entity->id, $request->input('perustustyypit'));
					Runkotyyppi::update_rakennus_runkotyypit($entity->id, $request->input('runkotyypit'));
					Vuoraustyyppi::update_rakennus_vuoraustyypit($entity->id, $request->input('vuoraustyypit'));
					Katetyyppi::update_rakennus_katetyypit($entity->id, $request->input('katetyypit'));
					RakennuksenKulttuurihistoriallinenArvo::update_rakennus_kulttuurihistoriallisetarvot($entity->id, $request->input('kulttuurihistoriallisetarvot'));
					RakennusSuojelutyyppi::update_rakennus_suojelutyypit($entity->id, $request->input('suojelutiedot'));
					RakennusMuutosvuosi::update_rakennus_muutosvuodet($entity->id, $request->input('muutosvuodet'));
					RakennusOsoite::update_rakennus_osoitteet($entity->id, $request->input('osoitteet'));
					RakennusOmistaja::update_rakennus_omistajat($entity->id, $request->input('omistajat'));


					//Update the related kiinteisto inventointiprojekti
					if($request->inventointiprojekti) {
						InventointiprojektiKiinteisto::insertInventointitieto($request->inventointiprojekti, $entity->kiinteisto_id);
					}

					//Add suunnittelija info
					SuunnittelijaRakennus::updateSuunnittelijat($entity->id, $request->suunnittelijat);

				} catch(Exception $e) {
					// Exception, always rollback the transaction
					DB::rollback();
					throw $e;
				}
				// And commit the transaction as all went well
				DB::commit();

				MipJson::addMessage(Lang::get('rakennus.save_success'));
				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
				MipJson::setResponseStatus(Response::HTTP_OK);

			} catch(Exception $e) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('rakennus.save_failed'));
			}
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
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.muokkaus')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$validator = Validator::make($request->all(), [
			'kiinteisto.id'			=> 'required|numeric|exists:kiinteisto,id',
			'inventointinumero'		=> 'required|numeric',
			'rakennustunnus'		=> 'nullable|string',
			'sijainti'				=> 'required|regex:/^\d+\.\d+\ \d+\.\d+$/', // 20.123456 60.123456 (lat, lon)
			'rakennusvuosi_alku'	=> 'nullable|numeric',
			'rakennusvuosi_loppu'	=> 'nullable|numeric',
			'muutosvuodet.alkuvuosi' => 'nullable|integer|min:0',
			'muutosvuodet.loppuvuosi' => 'nullable|integer|min:0',
			'osoitteet.katunimi'	=> 'nullable|string'

				// TODO checks!
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
				$entity = Rakennus::find($id);

				if(!$entity){
					//error, entity not found
					MipJson::setGeoJsonFeature();
					MipJson::addMessage(Lang::get('rakennus.search_not_found'));
					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				}
				else {

					// wrap the operations into a transaction
					DB::beginTransaction();
					Utils::setDBUser();

					try {

						// convert geometry from DB to lat/lon String like: "20.123456789 60.123456789"
						$geom = MipGis::getPointGeometryValue($request->sijainti);

						$entity->fill($request->all());
						$entity->rakennuksen_sijainti = $geom;

						$author_field = Rakennus::UPDATED_BY;
						$entity->$author_field = Auth::user()->id;

						if ($request->kiinteisto) {
							$entity->kiinteisto_id = $request->kiinteisto['id'];
						}

						// select lists: tyylisuunta
						if ($request->nykyinentyyli) {
							$tyylisuunta = new Tyylisuunta($request->input('nykyinentyyli'));
							$entity->nykyinen_tyyli_id = $tyylisuunta->id;
						} else {
							$entity->nykyinen_tyyli_id = null;
						}
						// select lists: kunto
						if ($request->kuntotyyppi) {
							$kunto = new Kuntotyyppi($request->input('kuntotyyppi'));
							$entity->kuntotyyppi_id = $kunto->id;
						} else {
							$entity->kuntotyyppi_id = null;
						}
						// select lists: arvotus
						if ($request->arvotustyyppi) {
							$arvotus = new Arvotustyyppi($request->input('arvotustyyppi'));
							$entity->arvotustyyppi_id = $arvotus->id;
						} else {
							$entity->arvotustyyppi_id = null;
						}

						$entity->save();

						Kattotyyppi::update_rakennus_kattotyypit($entity->id, $request->input('kattotyypit'));
						Rakennustyyppi::update_rakennus_rakennustyypit($entity->id, $request->input('rakennustyypit'));
						Kayttotarkoitus::update_rakennus_alkuperaisetkaytot($entity->id, $request->input('alkuperaisetkaytot'));
						Kayttotarkoitus::update_rakennus_nykykaytot($entity->id, $request->input('nykykaytot'));
						Perustustyyppi::update_rakennus_perustustyypit($entity->id, $request->input('perustustyypit'));
						Runkotyyppi::update_rakennus_runkotyypit($entity->id, $request->input('runkotyypit'));
						Vuoraustyyppi::update_rakennus_vuoraustyypit($entity->id, $request->input('vuoraustyypit'));
						Katetyyppi::update_rakennus_katetyypit($entity->id, $request->input('katetyypit'));
						RakennuksenKulttuurihistoriallinenArvo::update_rakennus_kulttuurihistoriallisetarvot($entity->id, $request->input('kulttuurihistoriallisetarvot'));
						RakennusSuojelutyyppi::update_rakennus_suojelutyypit($entity->id, $request->input('suojelutiedot'));
						RakennusMuutosvuosi::update_rakennus_muutosvuodet($entity->id, $request->input('muutosvuodet'));
						RakennusOsoite::update_rakennus_osoitteet($entity->id, $request->input('osoitteet'));
						RakennusOmistaja::update_rakennus_omistajat($entity->id, $request->input('omistajat'));

						//Update the related kiinteisto inventointiprojekti
						if($request->inventointiprojekti) {
							InventointiprojektiKiinteisto::insertInventointitieto($request->inventointiprojekti, $entity->kiinteisto_id);
						}

						//Update suunnittelija info
						SuunnittelijaRakennus::updateSuunnittelijat($entity->id, $request->suunnittelijat);

					} catch(Exception $e) {
					    Log::debug("Rakennuksen tallennus epäonnistui: " . $e);
						// Exception, always rollback the transaction
						DB::rollback();
						throw $e;
					}

					// And commit the transaction as all went well
					DB::commit();

					MipJson::addMessage(Lang::get('rakennus.save_success'));
					MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
					MipJson::setResponseStatus(Response::HTTP_OK);
				}

			} catch(Exception $e) {
			    Log::debug("Rakennuksen tallennus epäonnistui: " . $e);
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('rakennus.save_failed'));
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
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.poisto')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$entity = Rakennus::find($id);

		if(!$entity) {
			// return: not found
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('rakennus.search_not_found'));
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
			return MipJson::getJson();
		}

		// wrap the operations into a transaction
		DB::beginTransaction();
		Utils::setDBUser();

		try {

			$author_field = Rakennus::DELETED_BY;
			$when_field = Rakennus::DELETED_AT;
			$entity->$author_field = Auth::user()->id;
			$entity->$when_field = \Carbon\Carbon::now();

			$entity->save();


			// DO NOT USE DELETE, IT DOES NOT UPDATE THE DELETED BY
			// 	$deleted_rows = $entity->delete();

			// And commit the transaction as all went well
			DB::commit();


			MipJson::addMessage(Lang::get('rakennus.delete_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			return MipJson::getJson();

		} catch(Exception $e) {
			// Exception, always rollback the transaction
			DB::rollback();

			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('rakennus.delete_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			return MipJson::getJson();
		}
	}

	public function listStaircases(Request $request, $id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.katselu')) {
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

					$building = Rakennus::find($id);

					if($building) {
						$entities = $building->staircases()->with('porrashuonetyyppi')->orderBy($jarjestys_kentta, $jarjestys_suunta);
						$total_rows = Utils::getCount($entities);
						$entities = $entities->skip($rivi)->take($riveja)->get();

						if($total_rows <= 0) {
							MipJson::setGeoJsonFeature();
							//MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
							MipJson::addMessage(Lang::get('porrashuone.search_not_found'));
						}
						else {
							MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
							MipJson::addMessage(Lang::get('porrashuone.search_success'));
							foreach ($entities as $entity) {
								MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
							}
						}
					}
					else {
						MipJson::setGeoJsonFeature();
						MipJson::addMessage(Lang::get('rakennus.search_not_found'));
						MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					}
				}
			} catch(QueryException $e) {
				MipJson::addMessage(Lang::get('rakennus.search_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
		return MipJson::getJson();
	}

	/**
	 * Method to get desingers of given building
	 *
	 * @param int $id
	 * @param Request $request
	 * @return <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @author
	 * @version 1.0
	 * @since
	 */
	public function listDesigners($id, Request $request) {
		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.katselu')) {
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

					$building = Rakennus::find($id);
					if($building) {
						$designers = $building->designers()->get();
						if(count($designers) <= 0) {
							MipJson::setGeoJsonFeature();
							//MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
							MipJson::addMessage(Lang::get('suunnittelija.search_not_found'));
						}
						else {
							MipJson::initGeoJsonFeatureCollection(count($designers), count($designers));
							MipJson::addMessage(Lang::get('suunnittelija.search_success'));
							foreach($designers as $designer) {

								/*
								 * set the designer name if exists in
								 * 1. suunnittelija.etunimi OR suunnittelija.sukunimi
								 * 2. suunnittelija.rakentajan_nimi
								 * 3. suunnittelija_rakennus.suunnittelijan_nimi <-- Field has been removed.
								 */
								if($designer->etunimi || $designer->sukunimi)
									$designer->nimi = $designer->sukunimi." ".$designer->etunimi;
								else if($designer->kokonimi)
									$designer->nimi = $designer->kokonimi;
								//else if($designer->suunnittelijan_nimi)
								//	$designer->nimi = $designer->suunnittelijan_nimi;

								$designer->lisatiedot = $designer->pivot->lisatiedot;
								$designer->suunnittelijan_nimi = $designer->pivot->suunnittelijan_nimi;
								unset($designer->pivot, $designer->etunimi, $designer->sukunimi, $designer->kokonimi, $designer->suunnittelijan_nimi);
								MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $designer);
							}
						}
					}
					else {
						MipJson::setGeoJsonFeature();
						MipJson::addMessage(Lang::get('rakennus.search_not_found'));
						MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					}
				}
			} catch(QueryException $e) {
				MipJson::addMessage(Lang::get('rakennus.search_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
		return MipJson::getJson();
	}

	/**
	 * Method to add relation between the given building and designer
	 *
	 * @return MipJson
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function addDesigner($rakennus_id, Request $request) {
		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.muokkaus')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
		$validator = Validator::make($request->all(), [
				'suunnittelija_id' 			=> 'required|numeric|exists:suunnittelija,id',
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
				$rakennus = Rakennus::find($rakennus_id);

				if($rakennus) {
					$rakennus->designers()->attach($request->suunnittelija_id, array("lisatiedot" => $request->lisatiedot));
					MipJson::addMessage(Lang::get('rakennus.save_success'));
					MipJson::setGeoJsonFeature(null, array("rakennus_id" => $rakennus->id, "suunnittelija_id" => $request->suunnittelija_id));
					MipJson::setResponseStatus(Response::HTTP_OK);
				}
				else {
					MipJson::setGeoJsonFeature();
					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					MipJson::addMessage(Lang::get('rakennus.search_not_found'));
				}
			}
			catch(Exception $e) {
				MipJson::setGeoJsonFeature();
				MipJson::setMessages(array(Lang::get('rakennus.save_failed'),$e->getMessage()));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
		return MipJson::getJson();
	}

	public function deleteDesigner($rakennus_id, Request $request) {
		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.muokkaus')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}
		$validator = Validator::make($request->all(), [
				'suunnittelija_id' 			=> 'required|numeric|exists:suunnittelija,id',
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
				$rakennus = Rakennus::find($rakennus_id);
				if($rakennus) {
					$rakennus->designers()->detach($request->suunnittelija_id);
					MipJson::addMessage(Lang::get('rakennus.save_success'));
					MipJson::setGeoJsonFeature(null, array("rakennus_id" => $rakennus->id, "suunnittelija_id" => $request->suunnittelija_id));
					MipJson::setResponseStatus(Response::HTTP_OK);
				}
				else {
					MipJson::setGeoJsonFeature();
					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					MipJson::addMessage(Lang::get('rakennus.search_not_found'));
				}
			}
			catch(Exception $e) {
				MipJson::setGeoJsonFeature();
				MipJson::setMessages(array(Lang::get('rakennus.save_failed'),$e->getMessage()));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
		return MipJson::getJson();
	}

	public function listRakennusImages(Request $request, $id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.katselu')) {
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

					$rakennus = Rakennus::find($id);

					if($rakennus) {

						$entities = $rakennus->images()->orderby('kuva.'.$jarjestys_kentta, $jarjestys_suunta)->get();
						$total_rows = count($entities);

						if($total_rows <= 0) {
							MipJson::setGeoJsonFeature();
							//MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
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
						MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					}
				}
			} catch(QueryException $e) {
				MipJson::addMessage(Lang::get('alue.search_failed'));
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
		return MipJson::getJson();
	}

	public function updateRakennusImages(Request $request, $id) {

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
								Rakennus::find($id)->images()->detach($item_id);
								else if($item->op == "add") {
									if(Kuva::find($item_id)) {
										$exists = DB::select("SELECT * FROM kuva_rakennus WHERE kuva_id = ? AND rakennus_id = ?", [$item_id, $id]);
										if(count($exists) == 0) {
											Rakennus::find($id)->images()->attach($item_id);
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
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.katselu')) {
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

		return RakennusMuutoshistoria::getById($id);
	}

	/*
	 * Siirrä rakennus toiseen kiinteistöön.
	 */
	public function siirra(Request $request, $id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.muokkaus')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$validator = Validator::make($request->all(), [
				'kiinteistotunnus'			=> 'required|exists:kiinteisto,kiinteistotunnus',
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
			$entity = Rakennus::find($id);

			if(!$entity){
				//error, entity not found
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('rakennus.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);

				return MipJson::getJson();
			}

			// wrap the operations into a transaction
			DB::beginTransaction();
			Utils::setDBUser();

			try {

				if($request->palstanumero) {
					$kiinteisto = DB::table('kiinteisto')->where('kiinteistotunnus', $request->kiinteistotunnus)->where('palstanumero', $request->palstanumero)->get();
				} else {
					$kiinteisto = DB::table('kiinteisto')->where('kiinteistotunnus', $request->kiinteistotunnus)->get();
				}

				if(!$kiinteisto) {
					MipJson::setGeoJsonFeature();
					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
					return MipJson::getJson();
				} else if(count($kiinteisto) > 1) {
					MipJson::setGeoJsonFeature();
					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					MipJson::addMessage(Lang::get('kiinteisto.too_many_found'));
					return MipJson::getJson();
				}

				$kiinteisto = $kiinteisto[0];

				$entity->kiinteisto_id = $kiinteisto->id;


				//Jos inventointinumero on päällekkäinen, anna tälle rakennukselle seuraava vapaa inventointinumero
				$rakennusCount = DB::table('rakennus')->where('kiinteisto_id', $kiinteisto->id)->where('inventointinumero', $entity->inventointinumero)->count();

				if($rakennusCount > 0) {
					$entity->inventointinumero = DB::table('rakennus')->where('kiinteisto_id', $kiinteisto->id)->max('inventointinumero')+1;
				}

				$author_field = Rakennus::UPDATED_BY;
				$entity->$author_field = Auth::user()->id;

				$entity->save();

			} catch(Exception $e) {
				// Exception, always rollback the transaction
				DB::rollback();
				throw $e;
			}

			// And commit the transaction as all went well
			DB::commit();

			MipJson::addMessage(Lang::get('rakennus.save_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			MipJson::setResponseStatus(Response::HTTP_OK);

		} catch(Exception $e) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('rakennus.save_failed'));
		}

		return MipJson::getJson();
	}
}
