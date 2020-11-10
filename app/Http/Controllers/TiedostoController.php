<?php

namespace App\Http\Controllers;

use App\Kayttaja;
use App\Kunta;
use App\Tiedosto;
use App\Utils;
use App\Library\String\MipJson;
use App\Rak\Alue;
use App\Rak\Arvoalue;
use App\Rak\Kiinteisto;
use App\Rak\Porrashuone;
use App\Rak\Rakennus;
use App\Rak\Suunnittelija;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

class TiedostoController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.tiedosto.katselu')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    		"kiinteisto_id"				=> "numeric|exists:kiinteisto,id",
    		'rakennus_id'				=> 'numeric|exists:rakennus,id',
    		'alue_id'					=> 'numeric|exists:alue,id',
    		'porrashuone_id'			=> 'numeric|exists:porrashuone,id',
    		'arvoalue_id'				=> 'numeric|exists:arvoalue,id',
    		'kunta_id'					=> 'numeric|exists:kunta,id',
    	    'suunnittelija_id'			=> 'numeric|exists:suunnittelija,id'
    	]);

    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		foreach($validator->errors()->all() as $error) {
    			MipJson::addMessage($error);
    		}
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {
    		try {

    			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
    			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
    			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
    			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";


    			$entities = Tiedosto::orderBy($jarjestys_kentta, $jarjestys_suunta);


    			if($request->input("kiinteisto_id")) {
    				$entities->withEstateID($request->input("kiinteisto_id"));
    			}
    			if($request->input("rakennus_id")) {
    				$entities->withBuildingID($request->input("rakennus_id"));
    			}
    			if($request->input("alue_id")) {
    				$entities->withAlueID($request->input("alue_id"));
    			}
    			if($request->input("arvoalue_id")) {
    				$entities->withArvoalueID($request->input("arvoalue_id"));
    			}
    			if($request->input("porrashuone_id")) {
    				$entities->withPorrashuoneID($request->input("porrashuone_id"));
    			}
    			if($request->input("kunta_id")) {
    				$entities->withKuntaID($request->input("kunta_id"));
    			}
    			if($request->input("suunnittelija_id")) {
    			    $entities->withSuunnittelijaID($request->input("suunnittelija_id"));
    			}

    			$total_rows = Utils::getCount($entities);
    			$entities->withLimit($rivi, $riveja);
    			$entities = $entities->with(array('luoja', 'muokkaaja'))->get();


    			if(count($entities) <= 0) {
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('tiedosto.search_not_found'));
    			}
    			else  {
    				MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);

    				foreach ($entities as $entity) {
    					$entity->url = config('app.attachment_server')."/".config('app.attachment_server_baseurl').$entity->polku.$entity->nimi;
    					MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity->toArray());
    				}
    				MipJson::addMessage(Lang::get('tiedosto.found_count',["count" => count($entities)]));

    			}
    		}
    		catch(Exception $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('tiedosto.search_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.tiedosto.luonti')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$maxFileSize = config('app.max_file_size');

    	$validator = Validator::make($request->all(), [
    			"tiedosto"			=> "required|max:" . $maxFileSize,
    			'otsikko'			=> 'required',
    			'kuvaus'			=> 'nullable|string',
    			'entiteetti_tyyppi' => 'required|numeric|exists:entiteetti_tyyppi,id',
    			'entiteetti_id'		=> 'required|numeric'
    	]);
    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		foreach($validator->errors()->all() as $error)
    			MipJson::addMessage($error);
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {

    		// wrap the operations into a transaction
    		DB::beginTransaction();
    		Utils::setDBUser();

    		try {

    			if($request->hasFile("tiedosto")) {
    				$file 				= $request->file('tiedosto');
    				$file_extension 	= $file->getClientOriginalExtension();
    				$file_originalname 	= $file->getClientOriginalName();
    				$file_name			= Str::random(32);//.".".$file_extension;
    				$file_basepath		= storage_path()."/".config('app.attachment_upload_path');
    				$file_subpath		= Carbon::now()->format("Y/m/");
    				$file_path			= $file_basepath.$file_subpath;
    				$file_fullname		= $file_path.$file_name.".".$file_extension;
    				$user_id			= JWTAuth::toUser(JWTAuth::getToken())->id;

    				/*
    				 * Create the directory if it does not exist
    				 */
    				if(!File::exists($file_path)) {
    					File::makeDirectory($file_path, 0775, true);
    				}

    				//Make sure the name is unique
    				while ( File::exists ( $file_path . "/" . $file_name . "." . "$file_extension" ) ) {
    					$file_name = Str::random ( 32 );
    				}

    				/*
    				 * Move the uploaded file to its final destination
    				 */
    				$file->move($file_path, $file_name.".".$file_extension);

    				/*
    				 * Create the file and store it into DB and filesystem
    				 */
    				$entity = new Tiedosto($request->all());
    				$entity->nimi = $file_name.".".$file_extension;
    				$entity->alkuperainen_nimi = $file_originalname;
    				$entity->kayttaja_id = $user_id;
    				$entity->polku = $file_subpath;

    				$author_field = Tiedosto::CREATED_BY;
    				$entity->$author_field = Auth::user()->id;

    				$entity->save();


    				switch ($request->input('entiteetti_tyyppi')) {
    					case 1:
    						$kiinteisto = Kiinteisto::find($request->input('entiteetti_id'));
    						$kiinteisto->files()->attach($entity->id);
    						break;
    					case 2:
    						$rakennus = Rakennus::find($request->input('entiteetti_id'));
    						$rakennus->files()->attach($entity->id);
    						break;
    					case 3:
    						$porrashuone = Porrashuone::find($request->input('entiteetti_id'));
    						$porrashuone->files()->attach($entity->id);
    						break;
    					case 5:
    						$alue = Alue::find($request->input('entiteetti_id'));
    						$alue->files()->attach($entity->id);
    						break;
    					case 6:
    						$arvoalue = Arvoalue::find($request->input('entiteetti_id'));
    						$arvoalue->files()->attach($entity->id);
    						break;
    					case 7:
    						$kunta = Kunta::find($request->input('entiteetti_id'));
    						$kunta->files()->attach($entity->id);
    						break;
    					case 9:
    					    $suunnittelija = Suunnittelija::find($request->input('entiteetti_id'));
    					    $suunnittelija->files()->attach($entity->id);
    					    break;
    				}

    				DB::commit();

    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('tiedosto.save_success'));
    				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
    			}

    		} catch(Exception $e) {
    			DB::rollback();
    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('tiedosto.save_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.tiedosto.katselu')) {
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
    			$entity = Tiedosto::find($id)->with('luoja')->with('muokkaaja')->first();

    			if(!$entity) {
    				MipJson::setGeoJsonFeature();
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				MipJson::addMessage(Lang::get('tiedosto.search_not_found'));
    			}
    			else {
    				$entity->first();
    				$entity->url = config('app.attachment_server')."/".config('app.attachment_server_baseurl').$entity->polku.$entity->nimi;
    				MipJson::setGeoJsonFeature(null, $entity);
    				MipJson::addMessage(Lang::get('tiedosto.search_success'));
    			}
    		}
    		catch(QueryException $e) {
    			MipJson::addMessage(Lang::get('tiedosto.search_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.tiedosto.muokkaus', $id, 'tiedosto')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'otsikko'			=> 'required',
    			'kuvaus'			=> 'nullable|string',
    	]);

    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		foreach($validator->errors()->all() as $error)
    			MipJson::addMessage($error);
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {

    		// wrap the operations into a transaction
    		DB::beginTransaction();
    		Utils::setDBUser();
    		try {
    			$entity = Tiedosto::find($id);
    			if(!$entity){
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('tiedosto.search_not_found'));
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			}
    			else {
    				$entity->fill($request->all());
    				$author_field = Tiedosto::UPDATED_BY;
    				$entity->$author_field = Auth::user()->id;
    				$entity->update();
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('tiedosto.save_success'));
    				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
    			}

    			DB::commit();

    		} catch(Exception $e) {
    			DB::rollback();

    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('tiedosto.save_failed'));
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
    public function destroy(Request $request, $id) {
    	$validator = Validator::make($request->all(), [
    			'entiteetti_tyyppi' => 'required|numeric|exists:entiteetti_tyyppi,id',
    			'entiteetti_id' 	=> 'required|numeric'
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

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.tiedosto.poisto', $id, 'tiedosto')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$entity = Tiedosto::find($id);

    	if(!$entity) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('tiedosto.search_not_found'));
    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    		return MipJson::getJson();
    	}

    	try {

    		DB::beginTransaction();
    		Utils::setDBUser();

	    	$author_field = Tiedosto::DELETED_BY;
	    	$when_field = Tiedosto::DELETED_AT;
	    	$entity->$author_field = Auth::user()->id;
	    	$entity->$when_field = \Carbon\Carbon::now();
	    	$entity->save();
	    	switch($request->input('entiteetti_tyyppi')) {
	    		case 1:
	    			Kiinteisto::find($request->input('entiteetti_id'))->files()->detach($id);
	    			break;
	    		case 2:
	   				Rakennus::find($request->input('entiteetti_id'))->files()->detach($id);
	   				break;
	   			case 3:
	   				Porrashuone::find($request->input('entiteetti_id'))->files()->detach($id);
	   				break;
	   			case 5:
	   				Alue::find($request->input('entiteetti_id'))->files()->detach($id);
	   				break;
	   			case 6:
	   				Arvoalue::find($request->input('entiteetti_id'))->files()->detach($id);
	   				break;
	   			case 7:
	   				Kunta::find($request->input('entiteetti_id'))->files()->detach($id);
	   				break;
	   			case 9:
	   			    Suunnittelija::find($request->input('entiteetti_id'))->files()->detach($id);
	   			    break;
	    	}

	    	DB::commit();

	    	MipJson::addMessage(Lang::get('tiedosto.delete_success'));
	    	MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

    	} catch (Exception $e) {
    		DB::rollback();
    	 	MipJson::setGeoJsonFeature();
    	 	MipJson::addMessage(Lang::get('tiedosto.delete_failed'));
    	 	MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    	}

    	return MipJson::getJson();
    }
}
