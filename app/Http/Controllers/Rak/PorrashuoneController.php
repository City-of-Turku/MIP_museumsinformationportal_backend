<?php

namespace App\Http\Controllers\Rak;

use App\Kayttaja;
use App\Utils;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use App\Rak\InventointiprojektiKiinteisto;
use App\Rak\Porrashuone;
use App\Rak\PorrashuoneMuutoshistoria;
use App\Rak\Porrashuonetyyppi;
use App\Rak\Rakennus;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Exception;

class PorrashuoneController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.porrashuone.katselu')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'rivi'				=> 'numeric',
    			'rivit'				=> 'numeric',
    			'jarjestys'			=> 'string',
    			'jarjestys_suunta'	=> 'string',
    			'nimi'				=> 'string',
    			'id'				=> 'numeric',
    			'suunnittelija'		=> 'string',
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
   			 * Initialize the variables that needs to be "reformatted"
   			 */
   			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
   			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
   			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
   			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

   			/*
   			 * By default, initialize the query to get ALL entities
   			 */
   			$entities = Porrashuone::getAll()->with(['rakennus', 'rakennus.rakennustyypit', 'rakennusOsoitteet', 'luoja']);
   			//OSOITTEET: Rakennuksen osoitteet saa mukaan sanomalla yläpuolella 'rakennus.osoitteet', mutta
   			//tämä ei mahdollista sorttausta alkuunkaan

   			/*
   			 * If ANY search terms are given limit results by them
   			 * --> no search options available at this point...
   			 */
   			if($request->id) {
   				$entities->withID($request->id);
   			}
   			if($request->palstanumero) {
   			    $entities->withPalstanumero($request->palstanumero);
   			}
   			if($request->suunnittelija) {
   				$entities->withDesigner($request->suunnittelija);
   			}
   			if($request->kunta) {
   				$entities->withKuntaNimi($request->kunta);
   			}

   			if($request->kuntaId) {
   			    $entities->withKuntaId($request->kuntaId);
   			}
   			if($request->kuntanumero) {
   				$entities->withKuntaNumero($request->kuntanumero);
   			}
   			if($request->kyla) {
   				$entities->withKylaNimi($request->kyla);
   			}
   			if($request->kylaId) {
   			    $entities->withKylaId($request->kylaId);
   			}
   			if($request->kylanumero) {
   				$entities->withKylaNumero($request->kylanumero);
   			}
   			if($request->kiinteisto_nimi) {
   				$entities->withKiinteistoNimi($request->kiinteisto_nimi);
   			}
   			if($request->kiinteistotunnus) {
   				$entities->withKiinteistotunnus($request->kiinteistotunnus);
   			}
   			if($request->kiinteisto_osoite) {
   				$entities->withKiinteistoOsoite($request->kiinteisto_osoite);
   			}
   			if($request->tunnus) {
   				$entities->withTunnus($request->tunnus);
   			}
   			if($request->rakennus_inventointinumero) {
   				$entities->withRakennusInventointinumero($request->rakennus_inventointinumero);
   			}
   			if($request->rakennus_tyyppi){
   			    $entities->withBuildingTypeId($request->rakennus_tyyppi);
   			}
   			if($request->porrashuonetyyppi){
   				$entities->withPorrashuonetyyppiId($request->porrashuonetyyppi);
   			}

   			if($request->luoja) {
   				$entities->withLuoja($request->luoja);
   			}

   			// calculate the total rows of the search results
   			$total_rows = Utils::getCount($entities);

   			$entities->withOrder($jarjestys_kentta, $jarjestys_suunta);

   			// limit the results rows by given params
   			$entities->withLimit($rivi, $riveja);

   			// Execute the query
   			$entities = $entities->get();

			MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
			foreach ($entities as $entity) {
   				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
   			}
   			MipJson::addMessage(Lang::get('porrashuone.search_success'));

   		} catch(Exception $e) {
   			MipJson::setGeoJsonFeature();
   			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
   			MipJson::addMessage(Lang::get('porrashuone.search_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.porrashuone.luonti')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'rakennus_id'				=> 'required|numeric|exists:rakennus,id',
    			'huoneistojen_maara'		=> 'required|numeric',
    			'porrashuonetyyppi.id'		=> 'required|numeric|exists:porrashuonetyyppi,id',
    			'porrashuoneen_tunnus'		=> 'required|string'
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
    			try {
    				DB::beginTransaction();
    				Utils::setDBUser();

	    			$entity = new Porrashuone($request->all());
	    			$entity->hissi = boolval($request->hissi);
	    			$entity->kattoikkuna = boolval($request->kattoikkuna);

	    			$author_field = Porrashuone::CREATED_BY;
	    			$entity->$author_field = Auth::user()->id;

		    		$porrashuonetyyppi = new Porrashuonetyyppi($request->input('porrashuonetyyppi'));
		    		$entity->porrashuonetyyppi_id = $porrashuonetyyppi->id;

	    			$entity->save();


	    			//Update the related kiinteisto inventointiprojekti
	    			if($request->inventointiprojekti) {
	    				$rakennus = Rakennus::getSingle($request->rakennus_id)->first();
	    				InventointiprojektiKiinteisto::insertInventointitieto($request->inventointiprojekti, $rakennus->kiinteisto_id);
	    			}

	    			DB::commit();
    			} catch(Exception $e) {
    				DB::rollback();
    				throw $e;
    			}

    			MipJson::addMessage(Lang::get('porrashuone.save_success'));
    			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
    			MipJson::setResponseStatus(Response::HTTP_OK);
    		} catch(Exception $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('porrashuone.save_failed'));
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
    public function show($id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.porrashuone.katselu')) {
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
    			$entity = Porrashuone::getSingle($id)->with('luoja')->with('muokkaaja')->with('porrashuonetyyppi')->first();

    			if(!isset($entity->id)) {
    				MipJson::setGeoJsonFeature();
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				MipJson::addMessage(Lang::get('porrashuone.search_not_found'));
    			}
    			else {
    				MipJson::addMessage(Lang::get('porrashuone.search_success'));
    				MipJson::setGeoJsonFeature(null, $entity);
    			}
    		}
    		catch(QueryException $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::addMessage(Lang::get('porrashuone.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.porrashuone.muokkaus')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'rakennus_id'				=> 'required|numeric|exists:rakennus,id',
    			'huoneistojen_maara'		=> 'required|numeric',
    			'porrashuonetyyppi.id'		=> 'required|numeric|exists:porrashuonetyyppi,id',
    			'porrashuoneen_tunnus'		=> 'required|string'
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
    			$entity = Porrashuone::find($id);
    			if(!$entity){
    				//error, entity not found
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('porrashuone.search_not_found'));
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			}
    			else {
    				try {
    					DB::beginTransaction();
    					Utils::setDBUser();

	    				$entity->fill($request->all());
	    				$entity->hissi = boolval($request->hissi);
	    				$entity->kattoikkuna = boolval($request->kattoikkuna);

		    			$porrashuonetyyppi = new Porrashuonetyyppi($request->input('porrashuonetyyppi'));
		    			$entity->porrashuonetyyppi_id = $porrashuonetyyppi->id;

	    				$author_field = Porrashuone::UPDATED_BY;
	    				$entity->$author_field = Auth::user()->id;

	    				//Update the related kiinteisto inventointiprojekti
	    				if($request->inventointiprojekti) {
	    					$rakennus = Rakennus::getSingle($request->rakennus_id)->first();
	    					InventointiprojektiKiinteisto::insertInventointitieto($request->inventointiprojekti, $rakennus->kiinteisto_id);
	    				}

	    				$entity->update();

	    				DB::commit();
    				} catch(Exception $e) {
    					DB::rollback();
    					throw $e;
    				}

    				MipJson::addMessage(Lang::get('porrashuone.save_success'));
    				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
    				MipJson::setResponseStatus(Response::HTTP_OK);
    			}
    		} catch(Exception $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('porrashuone.save_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.porrashuone.poisto')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$entity = Porrashuone::find($id);
    	if(!$entity) {
    		// return: not found
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('porrashuone.search_not_found'));
    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    		return MipJson::getJson();
    	}

    	try {
    		DB::beginTransaction();
    		Utils::setDBUser();

	    	$author_field = Porrashuone::DELETED_BY;
	    	$when_field = Porrashuone::DELETED_AT;
	    	$entity->$author_field = Auth::user()->id;
	    	$entity->$when_field = \Carbon\Carbon::now();

	    	// DO NOT USE DELETE, IT DOES NOT UPDATE THE DELETED BY
	    	$entity->save();

    		DB::commit();

    		MipJson::addMessage(Lang::get('porrashuone.delete_success'));
    		MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

    	} catch (Exception $e) {
    		DB::rollback();

    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('porrashuone.delete_failed'));
    		MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		return MipJson::getJson();
    	}

    	return MipJson::getJson();
    }

    public function historia($id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.porrashuone.katselu')) {
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

    	return PorrashuoneMuutoshistoria::getById($id);

    }
}
