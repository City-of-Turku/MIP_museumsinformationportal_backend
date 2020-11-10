<?php

namespace App\Http\Controllers;

use App\Kayttaja;
use App\Korityyppi;
use App\Valinta;
use App\WmsRajapinta;
use App\Ark\Ajoitus;
use App\Ark\Ajoitustarkenne;
use App\Ark\Alkuperaisyys;
use App\Ark\ArkKarttaKoko;
use App\Ark\ArkKarttaTyyppi;
use App\Ark\ArkLoytotyyppi;
use App\Ark\ArkLoytotyyppiTarkenne;
use App\Ark\ArkMittakaava;
use App\Ark\ArkSailytystila;
use App\Ark\Hoitotarve;
use App\Ark\Kohdelaji;
use App\Ark\Kokoelmalaji;
use App\Ark\Konservointivaihe;
use App\Ark\Kunto;
use App\Ark\LoytoMateriaali;
use App\Ark\LoytoMateriaalikoodi;
use App\Ark\LoytoMerkinta;
use App\Ark\LoytoTila;
use App\Ark\Maastomerkinta;
use App\Ark\Naytekoodi;
use App\Ark\NayteTalteenottotapa;
use App\Ark\NayteTila;
use App\Ark\Naytetyyppi;
use App\Ark\Rajaustarkkuus;
use App\Ark\Rauhoitusluokka;
use App\Ark\Tuhoutumissyy;
use App\Ark\Tutkimuslaji;
use App\Ark\Tyyppi;
use App\Ark\Tyyppitarkenne;
use App\Ark\YksikkoKaivaustapa;
use App\Ark\YksikkoMaalaji;
use App\Ark\YksikkoSeulontatapa;
use App\Ark\YksikkoTyyppi;
use App\Library\String\MipJson;
use App\Rak\Aluetyyppi;
use App\Rak\ArvoalueenKulttuurihistoriallinenArvo;
use App\Rak\Arvotustyyppi;
use App\Rak\InventointiProjektiLaji;
use App\Rak\InventointiprojektiTyyppi;
use App\Rak\Katetyyppi;
use App\Rak\Kattotyyppi;
use App\Rak\Kayttotarkoitus;
use App\Rak\KiinteistonKulttuurihistoriallinenArvo;
use App\Rak\Kuntotyyppi;
use App\Rak\MatkaraportinSyy;
use App\Rak\Perustustyyppi;
use App\Rak\Porrashuonetyyppi;
use App\Rak\RakennuksenKulttuurihistoriallinenArvo;
use App\Rak\Rakennustyyppi;
use App\Rak\Runkotyyppi;
use App\Rak\SuojelutyyppiRyhma;
use App\Rak\SuunnittelijaAmmattiarvo;
use App\Rak\SuunnittelijaLaji;
use App\Rak\SuunnittelijaTyyppi;
use App\Rak\Tilatyyppi;
use App\Rak\Tyylisuunta;
use App\Rak\Vuoraustyyppi;
use App\Ark\KonservointiKasittely;
use App\Ark\KonservointiMateriaali;
use App\Ark\KonservointiMenetelma;
use App\Ark\KonservointiToimenpide;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class ValintaController extends Controller {

	public function index() {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.valinta.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		try {

			$entities = Valinta::orderBy("kategoria", "ASC")->get();
			$total_rows = count($entities);
			MipJson::initGeoJsonFeatureCollection($total_rows, $total_rows);

			foreach ($entities as $entity) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
			}

			if($total_rows <= 0) {
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				MipJson::addMessage(Lang::get('valinta.search_not_found'));
			}
			else
				MipJson::addMessage(Lang::get("valinta.search_success"));

		} catch(Exception $e) {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('valinta.search_failed'));
		}

		return MipJson::getJson();
	}

	/**
	 * Method to list values of given caetgory
	 *
	 * @param String $category
	 * @return <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function listCategoryValues($category) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.valinta.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		try {
			// TODO: kaikki loput kategoriat
			// TODO: Lisätään where aktiivinen = true
		    switch ($category) {
				case "kayttotarkoitus":
					$entities = Kayttotarkoitus::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "rakennustyyppi":
					$entities = Rakennustyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->where('aktiivinen', '=', 'true')->orderBy("nimi_fi")->get();
					break;
				case "perustus":
					$entities = Perustustyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "runko":
					$entities = Runkotyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "kate":
					$entities = Katetyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "vuoraus":
					$entities = Vuoraustyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "kattotyyppi":
					$entities = Kattotyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "kunto":
					$entities = Kuntotyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "arvotus":
					$entities = Arvotustyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "aluetyyppi":
					$entities = Aluetyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "tilatyyppi":
					$entities = Tilatyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "inventointiprojektityyppi":
					$entities = InventointiprojektiTyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "inventointiprojektilaji":
					$entities = InventointiProjektiLaji::select("id", "tekninen_projekti", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "nykyinen_tyyli":
					$entities = Tyylisuunta::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "porrastyyppi":
					$entities = Porrashuonetyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "suojelutyypit":
					$entities = SuojelutyyppiRyhma::select("id", "nimi_fi", "nimi_se", "nimi_en")
						->with(array('suojelutyypit' => function($query) {
							$query->orderBy('nimi_fi');
						}))
						->orderBy("suojelutyyppi_ryhma.nimi_fi")
						->get();
					break;
				case "suunnittelijaammattiarvo":
					$entities = SuunnittelijaAmmattiarvo::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "suunnittelijalaji":
					$entities = SuunnittelijaLaji::select("id", "nimi_fi", "nimi_se", "nimi_en", "yritys")->orderBy("nimi_fi")->get();
					break;
				case "suunnittelijatyyppi":
					$entities = SuunnittelijaTyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "matkaraportinsyy":
					$entities = Matkaraportinsyy::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "ark_kohdetyyppi":
					$entities = Tyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "ark_kohdetyyppitarkenne":
				    $entities = Tyyppitarkenne::select("id", "nimi_fi", "nimi_se", "nimi_en", "ark_kohdetyyppi_id")->orderBy("nimi_fi")->get();
				    break;
				case "ark_kohdelaji":
				    $entities = Kohdelaji::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "ajoitus":
				    $entities = Ajoitus::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "ajoitustarkenne":
				    $entities = Ajoitustarkenne::select("id", "nimi_fi", "nimi_se", "nimi_en", "ajoitus_id")->orderBy("nimi_fi")->get();
				    break;
				case "alkuperaisyys":
				    $entities = Alkuperaisyys::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "rajaustarkkuus":
				    $entities = Rajaustarkkuus::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "maastomerkinta":
				    $entities = Maastomerkinta::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "ark_kunto":
				    $entities = Kunto::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "hoitotarve":
				    $entities = Hoitotarve::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "rauhoitusluokka":
					$entities = Rauhoitusluokka::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "ark_tutkimuslaji":
					$entities = Tutkimuslaji::select("id", "nimi_fi", "nimi_se", "nimi_en", "aktiivinen")->orderBy("nimi_fi")->get();
					break;
				case "ark_tuhoutumissyy":
					$entities = Tuhoutumissyy::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
					break;
				case "ark_kokoelmalaji":
				    $entities = Kokoelmalaji::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "yksikko_tyyppi":
				    $entities = YksikkoTyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "yksikko_kaivaustapa":
				    $entities = YksikkoKaivaustapa::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "yksikko_seulontatapa":
				    $entities = YksikkoSeulontatapa::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
				    break;
				case "ark_loyto_materiaalikoodi":
				    $entities = LoytoMateriaalikoodi::select("id", "nimi_fi", "nimi_se", "nimi_en", "koodi", "aktiivinen")->orderBy("nimi_fi")->get();
				    break;
				case "ark_loyto_materiaali":
				    $entities = LoytoMateriaali::select("id", "nimi_fi", "nimi_se", "nimi_en", "aktiivinen")->orderBy("nimi_fi")->get();
				    break;
				case "ark_loyto_tyyppi":
				    $entities = ArkLoytotyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en", "aktiivinen")->orderBy("nimi_fi")->get();
				    break;
				case "ark_loyto_tyyppi_tarkenne":
				    $entities = ArkLoytotyyppiTarkenne::select("id", "nimi_fi", "nimi_se", "nimi_en", "aktiivinen")->orderBy("nimi_fi")->get();
				    break;
				case "ark_loyto_merkinta":
				    $entities = LoytoMerkinta::select("id", "nimi_fi", "nimi_se", "nimi_en", "aktiivinen")->orderBy("nimi_fi")->get();
				    break;
				case "ark_loyto_tila":
				    $entities = LoytoTila::select("id", "nimi_fi", "nimi_se", "nimi_en", "aktiivinen")->orderBy("nimi_fi")->get();
				    break;
				case "yksikko_maalaji":
				    $entities = YksikkoMaalaji::select("id", "nimi_fi", "nimi_se", "nimi_en", "aktiivinen")->orderBy("nimi_fi")->get();
				    break;
				case "ark_naytekoodi":
				    $entities = Naytekoodi::select("id", "nimi_fi", "nimi_se", "nimi_en", "koodi", "aktiivinen")->orderBy("nimi_fi")->get();
				    break;
				case "ark_nayte_tila":
				    $entities = NayteTila::select("id", "nimi_fi", "nimi_se", "nimi_en", "aktiivinen")->orderBy("nimi_fi")->get();
				    break;
				case "ark_nayte_talteenottotapa":
				    $entities = NayteTalteenottotapa::select("id", "nimi_fi", "nimi_se", "nimi_en", "aktiivinen")->orderBy("nimi_fi")->get();
				    break;
				case "ark_naytetyyppi":
				    $entities = Naytetyyppi::select("id", "nimi_fi", "nimi_se", "nimi_en", "aktiivinen")->orderBy("nimi_fi")->get();
				    break;
				case "korityyppi":
				    $entities = Korityyppi::select("id", "nimi_fi", "nimi_se", "nimi_en", "taulu")->orderBy("nimi_fi")->get();
				    break;
				case "ark_karttatyyppi":
				    $entities = ArkKarttaTyyppi::select("id", "tyyppi", "numero")->orderBy("tyyppi")->get();
				    break;
				case "ark_karttakoko":
				    $entities = ArkKarttaKoko::select("id", "koko")->orderBy("koko")->get();
				    break;
				case "ark_mittakaava":
				    $entities = ArkMittakaava::select("id","mittakaava")->orderBy("mittakaava")->get();
				    break;
				case "ark_kons_menetelma":
				    $entities = KonservointiMenetelma::select("id", "nimi", "kuvaus", "aktiivinen")->orderBy("nimi")->get();
				    break;
				case "ark_kons_toimenpide":
				    $entities = KonservointiToimenpide::select("id", "nimi", "aktiivinen")->orderBy("nimi")->get();
				    break;
				case "ark_sailytystila":
				    $entities = ArkSailytystila::select("id", "nimi_fi", "nimi_se", "nimi_en", "aktiivinen")->orderBy("id")->get();
				    break;
				case "ark_kons_materiaali":
				    $entities = KonservointiMateriaali::select("id", "nimi", "aktiivinen")->orderBy("nimi")->get();
				    break;
				case "ark_kons_kasittely":
				    // Palautetaan käsittelyt joilla ei ole päätöspäivää tai se on voimassa
				    $today = Carbon::today()->toDateString();

				    $entities = KonservointiKasittely::select("id", "kasittelytunnus", "alkaa")
				        ->whereNull("paattyy")
				        ->orWhere("paattyy", ">=", $today)
				        ->orderBy("kasittelytunnus")->get();
				    break;
				case "ark_kons_kasittely_kaikki":
				    $entities = KonservointiKasittely::select("id", "kasittelytunnus", "alkaa", "paattyy")->orderBy("kasittelytunnus")->get();
				    break;
				case "ark_konservointivaihe":
				    $entities = Konservointivaihe::select("id", "nimi_fi", "nimi_en", "nimi_se", "aktiivinen")->get();
				    break;
				default:
					$entities = Valinta::withCategory($category)->orderBy("arvo_fi")->get();
			}

			return $entities;

		} catch(Exception $e) {
    		MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		MipJson::addMessage(Lang::get('valinta.search_failed'));
    	}

    	return MipJson::getJson();
	}

	/**
	 * Method to list culture historical values of a given type
	 *
	 * @param Request $request
	 * @return MipJson
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function listCultureHistoricalValues(Request $request) {
		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.valinta.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$validator = Validator::make($request->all(), [
				"tyyppi"	=> "required|string|in:kiinteisto,rakennus,arvoalue",
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
				switch ($request->tyyppi) {
					case "kiinteisto" :
						$entities = KiinteistonKulttuurihistoriallinenArvo::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
						break;
					case "rakennus"	:
						$entities = RakennuksenKulttuurihistoriallinenArvo::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
						break;
					case "arvoalue" :
						$entities = ArvoalueenKulttuurihistoriallinenArvo::select("id", "nimi_fi", "nimi_se", "nimi_en")->orderBy("nimi_fi")->get();
						break;
					default:
						throw new Exception("Invalid type selector");
				}

			    $total_rows = count($entities);
				MipJson::initGeoJsonFeatureCollection($total_rows, $total_rows);

				foreach ($entities as $entity) {
					MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
				}

				if($total_rows <= 0) {
					MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
					MipJson::addMessage(Lang::get('valinta.search_not_found'));
				}

			} catch(Exception $e) {
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('valinta.search_failed'));
			}
		}

		return MipJson::getJson();
	}

	/**
	 * Method to list ALL WMS layer values
	 *
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function listWmsValues() {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.valinta.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		try {

			$entities = WmsRajapinta::orderBy("nimi", "ASC")->get();
			$total_rows = count($entities);
			MipJson::initGeoJsonFeatureCollection($total_rows, $total_rows);

			foreach ($entities as $entity) {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
			}

			if($total_rows <= 0) {
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				MipJson::addMessage(Lang::get('wms.search_not_found'));
			}
			else
				MipJson::addMessage(Lang::get("wms.search_success"));

		} catch(Exception $e) {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('wms.search_failed'));
		}

		return MipJson::getJson();
	}

}
