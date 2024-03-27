<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
*/

/*
 * Login route
 */
Route::post('/kayttaja/kirjaudu/', 'Auth\AuthController@login');
Route::post("/kayttaja/{kayttajatunnus}/salasana_unohtunut/",	"KayttajaController@restorePassword"); // restore forgotten password for given username

/*
 * Map routes
 */
Route::get("/kartta/getfeatureinfo/", 	"KarttaController@featureInfoProxy");
Route::get("/kartta/{taso}", 			"KarttaController@proxy");


/*
 * Image routes
 */
Route::get("/raportti/kuva/{id}/pieni",			"Rak\KuvaController@viewSmall");
Route::get("/raportti/ark_kuva/{id}/pieni",			"Ark\ArkKuvaController@viewSmall");
Route::get("/raportti/muistot_kuva/{id}/pieni",			"Muistot\MuistotKuvaController@viewSmall");

/*
 * OAI-PMH routes, currently do not require authentication
 */
Route::get("/oaipmh/", "FinnaController@index");


Route::group(['middleware' => ['prikka']], function () {
	Route::get("/prikka/muisto/{id}/",			"Muistot\MuistoController@getMuisto");
	Route::post("/prikka/tallennamuistot",		"Muistot\MuistoController@saveMuistot");
	Route::post("/prikka/tallennaaiheet",			"Muistot\AiheController@saveAiheet");
});


/*
 * Inside of this routeGroup All the routes require user to be authenticated
 */
Route::group(['middleware' => ['auth.jwt', 'setLocale']], function () {

	/*
	 * Userguide
	 */
	Route::get("/kayttoohje",				"KayttoohjeController@getKayttoohje");
	/*
	 * Release notes
	 */
	Route::get("/releasenotes",				"ReleasenotesController@getReleasenotes");
	/*
	 * Report routes - old ones, will be useless in future
	 */
	// Route::get("/raportti/kiinteistot/{type?}",			"ReportController@kiinteistotRaportti");
	// Route::get("/raportti/kiinteisto/{id}/{type?}",		"ReportController@kiinteistoRaportti");
	// Route::get("/raportti/alue/{id}/{type?}",			"ReportController@alueRaportti");
	// Route::get("/raportti/kiinteistoraportti/",			"ReportController@kiinteistoPerustietoRaportti");

	/*
	 * The new report server routes
	 */
	Route::get("/raportti/kayttaja/{userid}", 			"ReportController@index"); //Get USERS reports
	Route::post("/raportti/", 							"ReportController@createReportRequest"); //Create a new report request
	Route::delete("/raportti/{id}",						"ReportController@deleteReportRequest"); //Delete
	Route::get("/raportti/{id}/tila", 					"ReportController@getReportRequestStatus"); //Get single report status
	Route::get("/raportti/{id}/lataus",					"ReportController@downloadReport"); //Get the download url for the single report

	/*
	 * Alue
	 */
	Route::get("/alue/",							"Rak\AlueController@index");
	Route::post("/alue/",							"Rak\AlueController@store");
	Route::get("/alue/{alue_id}/",					"Rak\AlueController@show");
	Route::post("/alue/kori",         				"Rak\AlueController@kori");
	Route::put("/alue/{alue_id}/",					"Rak\AlueController@update");
	Route::delete("/alue/{alue_id}/",				"Rak\AlueController@destroy");
	Route::get("/alue/{alue_id}/historia/",			"Rak\AlueController@historia");
	Route::get("/alue/{alue_id}/arvoalue/",			"Rak\AlueController@listValueAreas");
	Route::patch("/alue/{alue_id}/kuva/",			"Rak\AlueController@updateAreaImages");
	route::get("/alue/{alue_id}/kuva/",				"Rak\AlueController@listAreaImages");

	/*
	 * Arvoalue
	 */
	Route::get("/arvoalue/",												"Rak\ArvoalueController@index");
	Route::post("/arvoalue/",												"Rak\ArvoalueController@store");
	Route::post("/arvoalue/kori",         				                    "Rak\ArvoalueController@kori");
	Route::get("/arvoalue/{arvoalue_id}/",									"Rak\ArvoalueController@show");
	Route::put("/arvoalue/{arvoalue_id}/",									"Rak\ArvoalueController@update");
	Route::delete("/arvoalue/{arvoalue_id}/",								"Rak\ArvoalueController@destroy");
	Route::get("/arvoalue/{arvoalue_id}/kiinteistot",						"Rak\ArvoalueController@showEstatesWithin");
	Route::get("/arvoalue/{arvoalue_id}/historia/",							"Rak\ArvoalueController@historia");

	/*
	 * Inventointiprojekti
	 */
	Route::get("/inventointiprojekti/",															"Rak\InventointiprojektiController@index");
	Route::post("/inventointiprojekti/",														"Rak\InventointiprojektiController@store");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/",								"Rak\InventointiprojektiController@show");
	Route::put("/inventointiprojekti/{inventointiprojekti_id}/",								"Rak\InventointiprojektiController@update");
	Route::delete("/inventointiprojekti/{inventointiprojekti_id}/",								"Rak\InventointiprojektiController@destroy");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/alue/",							"Rak\InventointiprojektiController@listAreas");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/arvoalue/",						"Rak\InventointiprojektiController@listValueAreas");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/kiinteisto/",						"Rak\InventointiprojektiController@listEstates");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/kyla/",							"Rak\InventointiprojektiController@listVillages");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/inventoija/",						"Rak\InventointiprojektiController@listInventorers");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/muutinventoijat/",				"Rak\InventointiprojektiController@listEntityInventorers");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/rakennus/",						"Rak\InventointiprojektiController@listBuildings");
	Route::post("/inventointiprojekti/{inventointiprojekti_id}/inventoija", 					"Rak\InventointiprojektiController@addInventor");
	Route::delete("/inventointiprojekti/{inventointiprojekti_id}/inventoija/{inventoija_id}", 	"Rak\InventointiprojektiController@deleteInventor");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/inventointipaiva", 				"Rak\InventointiprojektiController@getInventointipaiva");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/kenttapaiva", 					"Rak\InventointiprojektiController@getKenttapaiva");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/historia/",						"Rak\InventointiprojektiController@historia");
	Route::get("/inventointiprojekti/{inventointiprojekti_id}/kaikki/kylat",					"Rak\InventointiprojektiController@listAllVillages");

	/*
	 * Inventointijulkaisu
	 */
	Route::get("/inventointijulkaisu/",															"Rak\InventointijulkaisuController@index");
	Route::post("/inventointijulkaisu/",														"Rak\InventointijulkaisuController@store");
	Route::get("/inventointijulkaisu/{inventointijulkaisu_id}/",								"Rak\InventointijulkaisuController@show");
	Route::put("/inventointijulkaisu/{inventointijulkaisu_id}/",								"Rak\InventointijulkaisuController@update");
	Route::delete("/inventointijulkaisu/{inventointijulkaisu_id}/",								"Rak\InventointijulkaisuController@destroy");
	Route::get("/inventointijulkaisu/{inventointijulkaisu_id}/historia/",						"Rak\InventointijulkaisuController@historia");



	/*
	 * User and Auth endpoints - Only for authenticated users
	 * User and Authentication uses the same controller and model
	 */
	Route::post("/kayttaja/", 										"KayttajaController@store"); 		// create new user
	Route::get("/kayttaja/", 										"KayttajaController@index"); 		// list all users (with given keywords)
	Route::get("/kayttaja/kirjaudu/", 								"KayttajaController@loggedUser");	// display logged user data (by token)
	Route::get("/kayttaja/kirjaudu_ulos/", 							"Auth\AuthController@logout"); 		// log out
	Route::get("/kayttaja/{kayttaja_id}", 							"KayttajaController@show"); 		// get SINGLE user details
	Route::delete("/kayttaja/{kayttaja_id}/", 						"KayttajaController@destroy");		// Delete SINGLE user
	Route::put("/kayttaja/{kayttaja_id}/", 							"KayttajaController@update");		// update SINGLE user
	Route::get("/kayttaja/{kayttaja_id}/inventointiprojektit",		"KayttajaController@get_inventoringprojects"); //Get the users inventory projects

	/*
	 * Kiinteisto
	 */
	Route::post("/kiinteisto/",											"Rak\KiinteistoController@store");
	Route::get("/kiinteisto/",											"Rak\KiinteistoController@index");
	Route::get("/kiinteisto/kiinteistotunnus/",							"Rak\KiinteistoController@checkAvailability"); //This needs to be before @show route, otherwise all requests go to @show
	Route::get("/kiinteisto/kiinteistotunnushaku/{kiinteistotunnus}",	"Rak\KiinteistoController@getByIdentifier");
	Route::get("/kiinteisto/{kiinteisto_id}/",							"Rak\KiinteistoController@show");
	Route::post("/kiinteisto/kori",         				            "Rak\KiinteistoController@kori");
	Route::delete("/kiinteisto/{kiinteisto_id}/",						"Rak\KiinteistoController@destroy");
	Route::put("/kiinteisto/{kiinteisto_id}/",							"Rak\KiinteistoController@update");
	Route::get("/kiinteisto/{kiinteisto_id}/rakennus/",					"Rak\KiinteistoController@get_buildings");
	Route::post("/kiinteisto/{kiinteisto_id}/inventointiprojekti/",		"Rak\KiinteistoController@add_inventoringproject");
	Route::get("/kiinteisto/{kiinteisto_id}/inventointiprojekti/",		"Rak\KiinteistoController@get_inventoringprojects");
	Route::delete("/kiinteisto/{kiinteisto_id}/inventointiprojekti/",	"Rak\KiinteistoController@delete_inventoringproject");
	Route::patch("/kiinteisto/{kiinteisto_id}/kuva/",					"Rak\KiinteistoController@updateKiinteistoImages");
	Route::get("/kiinteisto/{kiinteisto_id}/historia/",					"Rak\KiinteistoController@historia");

	/*
	 * KTJ Search
	 * Search entities from KTJ web service with given point on map
	 */
	Route::get("/ktj/kiinteistotjarakennukset/",	"KtjController@queryKiinteistotWithinPolygonWithRakennukset");
	Route::get("/ktj/kiinteisto/",					"KtjController@queryKiinteisto");
	Route::get("/ktj/rakennus/",					"KtjController@queryRakennus");
	Route::get('/ktj/osoite/',						"KtjController@queryOsoite");
	Route::get('/ktj/nimisto/',						"KtjController@queryNimisto");

	/*
	 * Kunta
	 */
	Route::get("/kunta/",							"KuntaController@index");
	Route::post("/kunta/",							"KuntaController@store");
	Route::get("/kunta/{kunta_id}/",				"KuntaController@show");
	Route::put("/kunta/{kunta_id}/",				"KuntaController@update");
	Route::delete("/kunta/{kunta_id}/",				"KuntaController@destroy");
	Route::get("/kunta/{kunta_id}/kyla/",			"KuntaController@villages");
	Route::get("/kunta/{kunta_id}/alue/",			"KuntaController@areas");
	Route::get("/kunta/{kunta_id}/arvoalue/",		"KuntaController@valueareas");
	Route::get("/kunta/{kunta_id}/rakennus/",		"KuntaController@buildings");
	Route::get("/kunta/{kunta_id}/kiinteisto/",		"KuntaController@estates");
	Route::get("/kunta/{kunta_id}/historia/",		"KuntaController@historia");

	/*
	 * Kuva
	 */
	Route::get("/kuva/",							"Rak\KuvaController@index");
	Route::post("/kuva/",							"Rak\KuvaController@store");
	Route::get("/kuva/{kuva_id}/",					"Rak\KuvaController@show");
	Route::post("/kuva/kori",         				"Rak\KuvaController@kori");
	Route::put("/kuva/{kuva_id}/",					"Rak\KuvaController@update");
	Route::delete("/kuva/{kuva_id}/",				"Rak\KuvaController@destroy");
	Route::post("/kuva/jarjestele",					"Rak\KuvaController@reorder");
	Route::put("/kuva/{kuva_id}/siirra/",			"Rak\KuvaController@siirra");
	Route::put("/kuvat/siirra/", 					"Rak\KuvaController@siirraUseita");

	/*
	 * ArkKuva
	 */
	Route::get("/ark_kuva/uniikkiluettelointinumero/", "Ark\ArkKuvaController@isLuettelointinumeroUnique");
	Route::get("/ark_kuva/",						"Ark\ArkKuvaController@index");
	Route::get("/ark_kuva/{kuva_id}/",				"Ark\ArkKuvaController@show");
	Route::post("/ark_kuva/",						"Ark\ArkKuvaController@store");
	Route::put("/ark_kuva/{kuva_id}/",				"Ark\ArkKuvaController@update");
	Route::delete("/ark_kuva/{kuva_id}/",			"Ark\ArkKuvaController@destroy");

	/*
	 * Ark kartta
	 */
	Route::get("/ark_kartta/uniikkikarttanumero/",  "Ark\ArkKarttaController@isKarttanumeroUnique");
	Route::get("/ark_kartta/seuraavakarttanumero",  "Ark\ArkKarttaController@seuraavaKarttanumero");
	Route::get("/ark_kartta/",						"Ark\ArkKarttaController@index");
	Route::get("/ark_kartta/{kartta_id}/",			"Ark\ArkKarttaController@show");
	Route::post("/ark_kartta/",						"Ark\ArkKarttaController@store");
	Route::put("/ark_kartta/{kartta_id}/",			"Ark\ArkKarttaController@update");
	Route::delete("/ark_kartta/{kartta_id}/",		"Ark\ArkKarttaController@destroy");

	/*
	 * Kyla
	 */
	Route::get("/kyla/",							"KylaController@index");
	Route::post("/kyla/",							"KylaController@store");
	Route::get("/kyla/{kyla_id}/",					"KylaController@show");
	Route::put("/kyla/{kyla_id}/",					"KylaController@update");
	Route::delete("/kyla/{kyla_id}/",				"KylaController@destroy");
	Route::get("/kyla/{kyla_id}/kiinteisto",		"KylaController@kiinteistot");
	Route::get("/kyla/{kyla_id}/rakennus",			"KylaController@rakennukset");
	Route::get("/kyla/{kyla_id}/alue",				"KylaController@alueet");
	Route::get("/kyla/{kyla_id}/arvoalue",			"KylaController@arvoalueet");
	Route::get("/kyla/{kyla_id}/historia/",			"KylaController@historia");

	/*
	 * Porrashuone
	 */
	Route::post("/porrashuone/",							"Rak\PorrashuoneController@store");
	Route::get("/porrashuone/",								"Rak\PorrashuoneController@index");
	Route::get("/porrashuone/{porrashuone_id}/",			"Rak\PorrashuoneController@show");
	Route::delete("/porrashuone/{porrashuone_id}/",			"Rak\PorrashuoneController@destroy");
	Route::put("/porrashuone/{porrashuone_id}/",			"Rak\PorrashuoneController@update");
	Route::get("/porrashuone/{porrashuone_id}/historia/",	"Rak\PorrashuoneController@historia");

	/*
	 * Rakennus
	 */
	Route::post("/rakennus/",												"Rak\RakennusController@store");
	Route::get("/rakennus/",												"Rak\RakennusController@index");
	Route::get("/rakennus/{rakennus_id}/",									"Rak\RakennusController@show");
	Route::post("/rakennus/kori",         				                    "Rak\RakennusController@kori");
	Route::delete("/rakennus/{rakennus_id}/",								"Rak\RakennusController@destroy");
	Route::put("/rakennus/{rakennus_id}/",									"Rak\RakennusController@update");
	Route::get("/rakennus/{rakennus_id}/porrashuone/",						"Rak\RakennusController@listStaircases");
	Route::get("/rakennus/{rakennus_id}/suunnittelija/",					"Rak\RakennusController@listDesigners");
	Route::post("/rakennus/{rakennus_id}/suunnittelija/",					"Rak\RakennusController@addDesigner");
	Route::delete("/rakennus/{rakennus_id}/suunnittelija/",					"Rak\RakennusController@deleteDesigner");
	Route::patch("/rakennus/{rakennus_id}/kuva/",							"Rak\RakennusController@updateRakennusImages");
	Route::get("/rakennus/{rakennus_id}/historia/",							"Rak\RakennusController@historia");
	Route::put("/rakennus/{rakennus_id}/siirra/",							"Rak\RakennusController@siirra");

	/*
	 * Rooli = Oikeus (EI ROOLI)
	 */
	Route::get("/rooli/{osio}/{entiteetti}/",								"RooliController@show");

	/* Oikeudet tietylle entiteetille, käytetään vain arkeologian puolella */
	Route::get("/oikeus/{osio}/{entiteetti}/{id}",							"RooliController@showPermissionsForEntity");

	/*
	 * Suunnittelija
	 */
	Route::post("/suunnittelija/",								"Rak\SuunnittelijaController@store");
	Route::get("/suunnittelija/",								"Rak\SuunnittelijaController@index");
	Route::get("/suunnittelija/{suunnittelija_id}/",			"Rak\SuunnittelijaController@show");
	Route::delete("/suunnittelija/{suunnittelija_id}/",			"Rak\SuunnittelijaController@destroy");
	Route::put("/suunnittelija/{suunnittelija_id}/",			"Rak\SuunnittelijaController@update");
	Route::get("/suunnittelija/{suunnittelija_id}/rakennus",	"Rak\SuunnittelijaController@rakennukset");
	Route::get("/suunnittelija/{suunnittelija_id}/historia/",	"Rak\SuunnittelijaController@historia");


	/*
	 * Matkaraportti
	 */
	Route::post("/matkaraportti/",								"Rak\MatkaraporttiController@store");
	Route::get("/matkaraportti/",								"Rak\MatkaraporttiController@index");
	Route::get("/matkaraportti/{matkaraportti_id}/",			"Rak\MatkaraporttiController@show");
	Route::delete("/matkaraportti/{matkaraportti_id}/",			"Rak\MatkaraporttiController@destroy");
	Route::put("/matkaraportti/{matkaraportti_id}/",			"Rak\MatkaraporttiController@update");
	Route::get("/matkaraportti/{matkaraportti_id}/historia/",	"Rak\MatkaraporttiController@historia");


	//Ark puolen tiedostojen käsittely
	Route::get("/tiedosto/ark/",								"ArkTiedostoController@index");
	Route::post("/tiedosto/ark/",							"ArkTiedostoController@store");
	Route::get("/tiedosto/ark/{tiedosto_id}/",					"ArkTiedostoController@show");
	Route::put("/tiedosto/ark/{tiedosto_id}/",					"ArkTiedostoController@update");
	Route::delete("/tiedosto/ark/{tiedosto_id}/",				"ArkTiedostoController@destroy");

	/*
	 * Tiedosto
	 */
	Route::get("/tiedosto/",								"TiedostoController@index");
	Route::post("/tiedosto/",								"TiedostoController@store");
	Route::get("/tiedosto/{tiedosto_id}/",					"TiedostoController@show");
	Route::put("/tiedosto/{tiedosto_id}/",					"TiedostoController@update");
	Route::delete("/tiedosto/{tiedosto_id}/",				"TiedostoController@destroy");

	/*
	 * Endpoints to list DROPDOWN list values
	 *
	 * Possible categories are:
	 * - arvotus
	 * - kate
	 * - kattotyyppi
	 * - kunto
	 * - käyttötarkoitus
	 * - nykyinen_tyyli
	 * - perustus
	 * - porrastyyppi
	 * - rakennustyyppi
	 * - rakentajalaji
	 * - rakentajatyyppi
	 * - runko
	 * - tilatyyppi
	 * - vuoraus
	 */
	Route::get("/valinta/",									"ValintaController@index");
	Route::get("/valinta/wms/",								"ValintaController@listWmsValues");
	Route::get("/valinta/kulttuurihistoriallinenarvo/",		"ValintaController@listCultureHistoricalValues");
	Route::get("/valinta/{kategoria}/",						"ValintaController@listCategoryValues");

	/*
	 * Tekijänoikeuslauseke
	 */
	Route::get("/tekijanoikeuslauseke/",                    "TekijanoikeuslausekeController@index");
	Route::get("/tekijanoikeuslauseke/{id}",                "TekijanoikeuslausekeController@show");
	Route::post("/tekijanoikeuslauseke/",                   "TekijanoikeuslausekeController@store");
	Route::put("/tekijanoikeuslauseke/{id}",                "TekijanoikeuslausekeController@update");
	Route::delete("/tekijanoikeuslauseke/{id}",                 "TekijanoikeuslausekeController@destroy");

	//Route used to log frontend errors
	Route::post("/log/", 									"LogController@log");

	// Käyttäjän sijainnin/reitin routet
	Route::get("/reitti/",                                  "ReittiController@index");
	Route::post("/reitti/",                                 "ReittiController@store");
	Route::get("/reitti/{entiteettiTyyppi}/{entiteettiId}", "ReittiController@show");
	Route::delete("/reitti/{id}",                           "ReittiController@destroy");

	/*
	 * Koritoiminnallisuus
	 */
	Route::get("/kori/",                                    "KoriController@index");
	Route::post("/kori/",                                   "KoriController@store");
	Route::get("/kori/{id}",                                "KoriController@show");
	Route::get("/kori/korityyppi/{taulu}",                  "KoriController@haeKorityyppi");
	Route::put("/kori/{id}",                                "KoriController@update");
	Route::delete("/kori/{id}",                             "KoriController@destroy");

	/*
	 * Rontgenkuva
	 */
	Route::get("/rontgenkuva/",								  "Ark\RontgenkuvaController@index");
	Route::post("/rontgenkuva/",						      "Ark\RontgenkuvaController@store");
	Route::get("/rontgenkuva/{id}/",						  "Ark\RontgenkuvaController@show");
	Route::put("/rontgenkuva/{id}/",                          "Ark\RontgenkuvaController@update");
	Route::delete("/rontgenkuva/{id}/",                       "Ark\RontgenkuvaController@destroy");


	/* ====================== ARKEOLOGIA ===========================*/

	/*
	 * Tutkimukset
	 */
	Route::get("/tutkimus/",								"Ark\TutkimusController@index");
	Route::post("/tutkimus/",								"Ark\TutkimusController@store");
	Route::get("tutkimus/aktiiviset_inventointitutkimukset", "Ark\TutkimusController@getAktiivisetInventointitutkimukset");
	Route::get("/tutkimus/{id}/",							"Ark\TutkimusController@show");
	Route::put("/tutkimus/{id}/",							"Ark\TutkimusController@update");
	Route::delete("/tutkimus/{id}/",						"Ark\TutkimusController@destroy");
	Route::get("/tutkimus/{tutkimus_id}/historia",			"Ark\TutkimusController@historia");
	Route::post("tutkimus/{id}/kayttaja",					"Ark\TutkimusController@muokkaaKayttajia");
	Route::get("/tutkimus/{id}/lukumaarat", 			"Ark\TutkimusController@lukumaarat");

	/*
	 * Tutkimusraportit
	 */
	Route::post("/tutkimusraportti",									"Ark\TutkimusraporttiController@store");
	Route::put("/tutkimusraportti/{id}",							"Ark\TutkimusraporttiController@update");
	Route::delete("/tutkimusraportti/{id}",					"Ark\TutkimusraporttiController@destroy");
	Route::get("/tutkimusraportti/{tutkimusId}", 			"Ark\TutkimusraporttiController@getSingleByTutkimusId");

	/*
	 * Tutkimusalueet
	 */
	Route::get("/tutkimusalueet", 							"Ark\TutkimusalueController@index");
	Route::post("/tutkimusalue/",							"Ark\TutkimusalueController@store");
	Route::get("/tutkimusalue/{id}/",						"Ark\TutkimusalueController@show");
	Route::put("/tutkimusalue/{id}/",						"Ark\TutkimusalueController@update");
	Route::delete("/tutkimusalue/{id}/",					"Ark\TutkimusalueController@destroy");
	Route::get("/tutkimusalue/{tutkimusalue_id}/historia",	"Ark\TutkimusalueController@historia");

	/*
	 * Tutkimusalueen yksiköt
	 */
	Route::get("/tutkimusalueen/yksikot/", 					"Ark\TutkimusalueYksikkoController@index");
	Route::post("/tutkimusalue/yksikko/",					"Ark\TutkimusalueYksikkoController@store");
	Route::get("/tutkimusalue/yksikko/{id}/",				"Ark\TutkimusalueYksikkoController@show");
	Route::put("/tutkimusalue/yksikko/{id}/",				"Ark\TutkimusalueYksikkoController@update");
	Route::delete("/tutkimusalue/yksikko/{id}/",			"Ark\TutkimusalueYksikkoController@destroy");
	Route::get("/tutkimusalue/yksikkonumero/{id}/", 	    "Ark\TutkimusalueYksikkoController@yksikkonumero");

	/*
	 * YksikkoTyyppi
	 */
	Route::get("/yksikkotyyppi/",							"Ark\YksikkoTyyppiController@index");
	Route::post("/yksikkotyyppi/",							"Ark\YksikkoTyyppiController@store");
	Route::put("/yksikkotyyppi/{id}/",						"Ark\YksikkoTyyppiController@update");
	Route::delete("/yksikkotyyppi/{id}/",					"Ark\YksikkoTyyppiController@destroy");

	/*
	 * Talteenottotapa
	 */
	Route::get("/talteenottotapa/",							"Ark\TalteenottotapaController@index");
	Route::post("/talteenottotapa/",						"Ark\TalteenottotapaController@store");
	Route::put("/talteenottotapa/{id}/",					"Ark\TalteenottotapaController@update");
	Route::delete("/talteenottotapa/{id}/",					"Ark\TalteenottotapaController@destroy");

	/*
	 * Yksikko
	 */
	Route::get("/yksikko/",									"Ark\YksikkoController@index");
	Route::post("/yksikko/",								"Ark\YksikkoController@store");
	Route::get("/yksikko/{id}/",							"Ark\YksikkoController@show");
	Route::put("/yksikko/{id}/",							"Ark\YksikkoController@update");
	Route::delete("/yksikko/{id}/",							"Ark\YksikkoController@destroy");

	/*
	 * YksikkoSijainti
	 */
	Route::get("/yksikko/{yksikko_id}/sijainti/",			"Ark\YksikkoController@showGeom"); 		// palauttaa kaikki yksikön alueet ja pisteet
	Route::delete("/yksikko/{yksikko_id}/sijainti/{id}/",	"Ark\YksikkoController@destroyGeom"); 	// poista yksittäinen alue/piste
	Route::post("/yksikko/{yksikko_id}/sijainti/",			"Ark\YksikkoController@storeGeom"); 	// lisää yksikölle alue tai piste

	/*
	 * YksikkoAsiasana
	 */
	Route::post("/yksikko/{yksikko_id}/asiasana/", 				"Ark\YksikkoController@storeAsiasana"); //lisää yksikolle asiasana
	Route::delete("/yksikko/{yksikko_id}/asiasana/{asiasana}/",	"Ark\YksikkoController@destroyAsiasana"); //poista asiasana yksikolta

	/*
	 * YksikkoTalteenottotapa
	 */
	Route::post("/yksikko/{yksikko_id}/talteenottotapa/",						"Ark\YksikkoController@storeTalteenottotapa"); //lisää yksikölle talteenottotapa
	Route::delete("yksikko/{yksikko_id}/talteenottotapa/{talteenottotapa_id}/", "Ark\YksikkoController@destroyTalteenottotapa");

	/*
	 * YksikonElinkaari
	 */
	Route::get("/yksikon_elinkaari/",						"Ark\YksikonElinkaariController@index");
	Route::post("/yksikon_elinkaari/",						"Ark\YksikonElinkaariController@store");
	Route::put("/yksikon_elinkaari/{id}/",					"Ark\YksikonElinkaariController@update");
	Route::delete("/yksikon_elinkaari/{id}/",				"Ark\YksikonElinkaariController@destroy");

	/*
	 * Kohde
	 */
	Route::get("/kohde/",							"Ark\KohdeController@index");
	Route::get("/kohde/{kohde_id}/",				"Ark\KohdeController@show");
	Route::post("/kohde/",							"Ark\KohdeController@store");
	Route::put("/kohde/{id}/",						"Ark\KohdeController@update");
	Route::delete("/kohde/{id}/",					"Ark\KohdeController@destroy");
	Route::get("/kohde/{id}/historia",			    "Ark\KohdeController@historia");
	Route::get("/kohde/kohteet/polygon/",	        "Ark\KohdeController@kohteetPolygon");

	/*
	 * Löytö
	 */
	Route::get("/loyto/",							    "Ark\LoytoController@index");
	Route::get("/loyto/materiaali/ensisijaiset/{id}",   "Ark\LoytoController@haeEnsisijaisetMateriaalit");
	Route::post("/loyto/kori",         				    "Ark\LoytoController@kori"); // löytöjen haku koriin
	Route::post("/loyto/kori/tilamuutos",               "Ark\LoytoController@koriTilamuutos"); // korin löytöjen tilan muutos
	Route::post("/loyto/",							    "Ark\LoytoController@store");
	Route::get("/loyto/{id}/",						    "Ark\LoytoController@show");
	Route::put("/loyto/{id}/",						    "Ark\LoytoController@update");
	Route::put("/loyto/luettelointinumero/{id}/",	    "Ark\LoytoController@vaihdaLuettelointinumero");
	Route::delete("/loyto/{id}/",					    "Ark\LoytoController@destroy");
  Route::get("/loyto/luettelointinumerohaku/{luettelointinumero}/",						    "Ark\LoytoController@haeLoytoLuettelointinumerolla");

	/*
	 * Kuntoraportit
	 */
	Route::get("/loyto/{id}/kuntoraportit",				"Ark\LoytoController@kuntoraportit");
	Route::post("/kuntoraportti",									"Ark\KuntoraporttiController@store");
	Route::put("/kuntoraportti/{id}",							"Ark\KuntoraporttiController@update");
	Route::delete("/kuntoraportti/{id}",					"Ark\KuntoraporttiController@destroy");


	/*
	 * Näyte
	 */
	Route::get("/nayte/",							    "Ark\NayteController@index");
	Route::get("/nayte/naytetyypit/{id}",               "Ark\NayteController@haeNaytetyypit");
	Route::post("/nayte/kori",         				    "Ark\NayteController@kori"); // näytteiden haku koriin
	Route::post("/nayte/kori/tilamuutos",               "Ark\NayteController@koriTilamuutos"); // korin näytteiden tilan muutos
	Route::post("/nayte/",							    "Ark\NayteController@store");
	Route::post("/nayte/alanumero/",   				    "Ark\NayteController@nayteAlanumero");
	Route::get("/nayte/{id}/",						    "Ark\NayteController@show");
	Route::put("/nayte/{id}/",						    "Ark\NayteController@update");
	Route::delete("/nayte/{id}/",					    "Ark\NayteController@destroy");
  Route::get("/nayte/luettelointinumerohaku/{luettelointinumero}/",						    "Ark\NayteController@haeNayteLuettelointinumerolla");

	/*
	 * Konservointi hallinta
	 */
	Route::get("/konservointi/hallinta/materiaalit/",	        "Ark\KonservointiMateriaaliController@index");
	Route::post("/konservointi/hallinta/materiaali/",	        "Ark\KonservointiMateriaaliController@store");
	Route::put("/konservointi/hallinta/materiaali/{id}/",	    "Ark\KonservointiMateriaaliController@update");
	Route::delete("/konservointi/hallinta/materiaali/{id}/",	"Ark\KonservointiMateriaaliController@destroy");

	Route::get("/konservointi/hallinta/toimenpiteet/",	        "Ark\KonservointiToimenpideController@index");
	Route::post("/konservointi/hallinta/toimenpide/",	        "Ark\KonservointiToimenpideController@store");
	Route::put("/konservointi/hallinta/toimenpide/{id}/",	    "Ark\KonservointiToimenpideController@update");
	Route::delete("/konservointi/hallinta/toimenpide/{id}/",	"Ark\KonservointiToimenpideController@destroy");

	Route::get("/konservointi/hallinta/menetelmat/",	        "Ark\KonservointiMenetelmaController@index");
	Route::post("/konservointi/hallinta/menetelma/",	        "Ark\KonservointiMenetelmaController@store");
	Route::put("/konservointi/hallinta/menetelma/{id}/",	    "Ark\KonservointiMenetelmaController@update");
	Route::delete("/konservointi/hallinta/menetelma/{id}/",	    "Ark\KonservointiMenetelmaController@destroy");

	/*
	 * Konservointi käsittelyt
	 */
	Route::get("/konservointi/kasittelyt/",           	        "Ark\KonservointiKasittelyController@index");
	Route::post("/konservointi/kasittely/",           	        "Ark\KonservointiKasittelyController@store");
	Route::get("/konservointi/kasittely/{id}/",				    "Ark\KonservointiKasittelyController@show");
	Route::put("/konservointi/kasittely/{id}/",           	    "Ark\KonservointiKasittelyController@update");
	Route::delete("/konservointi/kasittely/{id}/",            	"Ark\KonservointiKasittelyController@destroy");

	/*
	 * Konservointitoimenpiteet
	 */
	Route::get("/konservointi/toimenpiteet/",           	    "Ark\KonsToimenpiteetController@index");
	Route::post("/konservointi/toimenpide/",           	        "Ark\KonsToimenpiteetController@store");
	Route::get("/konservointi/toimenpide/{id}/",				"Ark\KonsToimenpiteetController@show");
	Route::put("/konservointi/toimenpide/{id}/",           	    "Ark\KonsToimenpiteetController@update");
	Route::delete("/konservointi/toimenpide/{id}/",            	"Ark\KonsToimenpiteetController@destroy");

	/*
	 * Asiasanasto
	 */
	Route::get("/asiasanasto/",								"Ark\AsiasanastoController@index");

	/*
	 * Asiasana
	 */
	Route::get("/asiasana/", 								"Ark\AsiasanaController@index");

	/*
	 * Finto querying
	 */
	Route::get("/finto/{vocab}/{lang}/{query}",				"Ark\FintoController@query");

	/*
	 * SOLR querying
	 */
	Route::get("/solr/",									"SolrController@query");

	/*
	 * LoytoKategoria
	 */
	Route::get("/loytokategoria/",							"Ark\LoytoKategoriaController@index");
	Route::post("/loytokategoria/",							"Ark\LoytoKategoriaController@store");
	Route::put("/loytokategoria/{id}/",						"Ark\LoytoKategoriaController@update");
	Route::delete("/loytokategoria/{id}/",					"Ark\LoytoKategoriaController@destroy");

	/*
	 * Keramiikkatyyppi
	 */
	Route::get("/keramiikkatyyppi/",						"Ark\KeramiikkatyyppiController@index");
	Route::post("/keramiikkatyyppi/",						"Ark\KeramiikkatyyppiController@store");
	Route::put("/keramiikkatyyppi/{id}/",					"Ark\KeramiikkatyyppiController@update");
	Route::delete("/keramiikkatyyppi/{id}/",				"Ark\KeramiikkatyyppiController@destroy");

	/*
	 * OCMLuokka
	 */
	Route::get("/ocmluokka/",								"Ark\OCMLuokkaController@index");
	Route::post("/ocmluokka/",								"Ark\OCMLuokkaController@store");
	Route::put("/ocmluokka/{id}/",							"Ark\OCMLuokkaController@update");
	Route::delete("/ocmluokka/{id}/",						"Ark\OCMLuokkaController@destroy");

	/*
	 * Laatu
	 */
	Route::get("/laatu/",									"Ark\LaatuController@index");
	Route::post("/laatu/",									"Ark\LaatuController@store");
	Route::put("/laatu/{id}/",								"Ark\LaatuController@update");
	Route::delete("/laatu/{id}/",							"Ark\LaatuController@destroy");

	/*
	 * LoytoTyyppi
	 */
	Route::get("/loytotyyppi/",								"Ark\LoytoTyyppiController@index");
	Route::post("/loytotyyppi/",							"Ark\LoytoTyyppiController@store");
	Route::put("/loytotyyppi/{id}/",						"Ark\LoytoTyyppiController@update");
	Route::delete("/loytotyyppi/{id}/",						"Ark\LoytoTyyppiController@destroy");

	/*
	 * Materiaali
	 */
	Route::get("/materiaali/",								"Ark\MateriaaliController@index");
	Route::post("/materiaali/",								"Ark\MateriaaliController@store");
	Route::put("/materiaali/{id}/",							"Ark\MateriaaliController@update");
	Route::delete("/materiaali/{id}/",						"Ark\MateriaaliController@destroy");

	/*
	 * KonservoinninPrioriteetti
	 */
	Route::get("/konservoinninprioriteetti/",				"Ark\KonservoinninPrioriteettiController@index");
	Route::post("/konservoinninprioriteetti/",				"Ark\KonservoinninPrioriteettiController@store");
	Route::put("/konservoinninprioriteetti/{id}/",			"Ark\KonservoinninPrioriteettiController@update");
	Route::delete("/konservoinninprioriteetti/{id}/",		"Ark\KonservoinninPrioriteettiController@destroy");

	/*
	 * KonservoinninLaatuluokka
	 */
	Route::get("/konservoinninlaatuluokka/",				"Ark\KonservoinninLaatuluokkaController@index");
	Route::post("/konservoinninlaatuluokka/",				"Ark\KonservoinninLaatuluokkaController@store");
	Route::put("/konservoinninlaatuluokka/{id}/",			"Ark\KonservoinninLaatuluokkaController@update");
	Route::delete("/konservoinninlaatuluokka/{id}/",		"Ark\KonservoinninLaatuluokkaController@destroy");

	/*
	 * KonservoinninKiireellisyys
	 */
	Route::get("/konservoinninkiireellisyys/",				"Ark\KonservoinninKiireellisyysController@index");
	Route::post("/konservoinninkiireellisyys/",				"Ark\KonservoinninKiireellisyysController@store");
	Route::put("/konservoinninkiireellisyys/{id}/",			"Ark\KonservoinninKiireellisyysController@update");
	Route::delete("/konservoinninkiireellisyys/{id}/",		"Ark\KonservoinninKiireellisyysController@destroy");

	/*
	 * Varasto
	 */
	Route::get("/varasto/",									"Ark\VarastoController@index");

	/*
	 * Museo
	 */
	Route::get("/museo/",									"Ark\MuseoController@index");

	/*
	 * Nayttely
	 */
	Route::get("/nayttely/",								"Ark\NayttelyController@index");

	/*
	 * Julkaisu
	 */
	Route::get("/julkaisu/",								"Ark\JulkaisuController@index");

	/*
	 * Kokoelma
	 */
	Route::get("/kokoelma/",								"Ark\KokoelmaController@index");

	/*
	 * Kyppi integraatio, muinaisjäännöksen haku tunnuksella
	 */
	Route::get("/kyppi/haemuinaisjaannos/{id}",				"Ark\KyppiController@muinaisjaannosHaku");

	/*
	 * Kyppi integraatio, muinaisjäännösten haku muutospäivän mukaan. Testaamiseen, varsinainen haku tehdään ajastetusti.
	 */
	Route::get("/kyppi/haemuinaisjaannokset/{hakupvm}",     "Ark\KyppiController@haeMuinaisjaannokset");

	/*
	 * Kyppi integraatio, muinaisjäännöksen haku tunnuksella. Testaamiseen.
	 */
	Route::get("/kyppi/haemuinaisjaannostest/{id}",				"Ark\KyppiController@muinaisjaannosHakuTest");

	/*
	 * Kyppi integraatio, muinaisjäännöksen lisääminen
	 */
	Route::get("/kyppi/lisaamuinaisjaannos/{id}",				"Ark\KyppiController@muinaisjaannosLisays");


	/*
	 * Kyppi integraatio, muinaisjäännöksen haku ja kohteen luonti MIP:iin.
	 */
	Route::get("/kyppi/tuokohde/{id}",			            "Ark\KyppiController@tuoKohde");

	/*
	* Kyppi integraatio, käynnistä yöllinen ajo käyttöliittymän admin menusta
	*/
	Route::get("/kyppi/paivitaMuinaisjaannokset/",				"Ark\KyppiController@muinaisjaannosPaivitys");

	//Muistot
	Route::get("/muistot/{muisto_id}/", "Muistot\MuistoController@show");
	Route::get("/muistot/", "Muistot\MuistoController@index");
	Route::get("/aiheet/{aihe_id}/", "Muistot\AiheController@show");
  Route::get("/aiheet/{aihe_id}/muistot/",	"Muistot\AiheController@get_memories_by_topic");
	Route::get("/aiheet/", "Muistot\AiheController@index");
	Route::get("/muistot_kuva/",						"Muistot\MuistotKuvaController@index");
	Route::get("/muistot_kuva/{kuva_id}/",				"Muistot\MuistotKuvaController@show");
  // Todo: would be best to have dedicated route for updating estates
  Route::put("/muistot/{muisto_id}/",      "Muistot\MuistoController@update_estates");
});
