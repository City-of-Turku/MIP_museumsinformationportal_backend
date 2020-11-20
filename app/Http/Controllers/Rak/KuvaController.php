<?php

namespace App\Http\Controllers\Rak;

use App\Http\Controllers\Controller;
use App\Kayttaja;
use App\Rak\Kuva;
use App\Rak\Kiinteisto;
use App\Rak\Rakennus;
use App\Rak\Arvoalue;
use App\Rak\Porrashuone;
use App\Rak\Alue;
use App\Kyla;
use App\Rak\Suunnittelija;
use App\Library\String\MipJson;
use App\Utils;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use App\Rak\KuvaKiinteisto;
use App\Rak\KuvaAlue;
use App\Rak\KuvaArvoalue;
use App\KuvaKyla;
use App\Rak\KuvaPorrashuone;
use App\Rak\KuvaRakennus;
use App\Rak\KuvaSuunnittelija;


class KuvaController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.kuva.katselu')) {
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
    			'kyla_id'					=> 'numeric|exists:kyla,id',
    	        'suunnittelija_id'			=> 'numeric|exists:suunnittelija,id'
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

    			$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
    			$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
    			$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "id";
    			$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

    			$entities = Kuva::orderBy($jarjestys_kentta, $jarjestys_suunta);

    			if($request->input("kiinteisto_id")) {
    				$entities->withEstateID($request->input("kiinteisto_id"));
    			}
    			if($request->input("rakennus_id")) {
    				$entities->withBuildingID($request->input("rakennus_id"));
    			}
    			if($request->input("alue_id")) {
    				$entities->withAreaID($request->input("alue_id"));
    			}
    			if($request->input("arvoalue_id")) {
    				$entities->withValueareaID($request->input("arvoalue_id"));
    			}
    			if($request->input("porrashuone_id")) {
    				$entities->withStaircaseID($request->input("porrashuone_id"));
    			}
    			if($request->input("kyla_id")) {
    				$entities->withVillageID($request->input("kyla_id"));
    			}
    			if($request->input("suunnittelija_id")) {
    			    $entities->withSuunnittelijaID($request->input("suunnittelija_id"));
    			}

    			$total_rows = Utils::getCount($entities);
    			$entities->withLimit($rivi, $riveja);
    			$entities = $entities->with(array('luoja', 'muokkaaja'))->get();

    			if(count($entities) <= 0) {
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('kuva.search_not_found'));
    			}
    			else  {
    				MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
    				foreach ($entities as $entity) {

    					$images = Kuva::getImageUrls($entity->polku.$entity->nimi);
    					$entity->url = $images->original;
    					$entity->url_tiny = $images->tiny;
    					$entity->url_small = $images->small;
    					$entity->url_medium = $images->medium;
    					$entity->url_large = $images->large;

    					MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
    				}
    				MipJson::addMessage(Lang::get('kuva.found_count',["count" => count($entities)]));
    			}
    		}
    		catch(Exception $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('kuva.search_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.kuva.luonti')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$maxImageSize = config('app.max_image_size');

    	//Tiedosto validation removed as frontend doesn't post 'tiedosto' anymore
    	$validator = Validator::make($request->all(), [
    			"tiedosto"			=> "required|max:" . $maxImageSize . "|mimes:jpg,jpeg,gif,tif,tiff,png",
    			"entiteetti_tyyppi" => "required|numeric|exists:entiteetti_tyyppi,id",
    			"entiteetti_id"		=> "required|numeric",
    			'otsikko'			=> 'required',
    			'kuvaus'			=> 'nullable|string',
    			'kuvaaja'			=> 'nullable|string',
    			"julkinen"			=> "boolean"
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
	    			$file_basepath		= storage_path()."/".config('app.image_upload_path');
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
	    			$entity = new Kuva($request->all());
	    			$entity->nimi = $file_name.".".$file_extension;
	    			$entity->alkuperainen_nimi = $file_originalname;
	    			$entity->kayttaja_id = $user_id;
	    			$entity->polku = $file_subpath;

	    			$author_field = Kuva::CREATED_BY;
	    			$entity->$author_field = Auth::user()->id;

	    			//Add the jarjestys

	    			$entity->save();

	    			$thumb_extension = 'jpg';


    				/*
	    			 * Create thumbnails
	    			 */
	    			//Large
	    			$img = Image::make($file_fullname)->encode('jpg');
	    			$img_large = Kuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_large'))[0]));
	    			$img_large->save($file_path.$file_name."_LARGE.".$thumb_extension);

	    			//Medium
	    			$img = Image::make($file_fullname)->encode('jpg');
	    			$img_medium = Kuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_medium'))[0]));
	    			$img_medium->save($file_path.$file_name."_MEDIUM.".$thumb_extension);

	    			//Small
	    			$img = Image::make($file_fullname)->encode('jpg');
    				$img_small = Kuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_small'))[0]));
    				$img_small->save($file_path.$file_name."_SMALL.".$thumb_extension);

    				//Tiny
    				$img = Image::make($file_fullname)->encode('jpg');
    				$img_tiny = Kuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_tiny'))[0]));
    				$img_tiny->save($file_path.$file_name."_TINY.".$thumb_extension);



    				//Refer to table entiteetti_tyyppi
    				switch ($request->input('entiteetti_tyyppi')) {
    				case 1:
    					$maxJarjestys = DB::table('kuva_kiinteisto')->where('kiinteisto_id', '=', $request->input('entiteetti_id'))->max('jarjestys')+1;
    					$kiinteisto = Kiinteisto::find($request->input('entiteetti_id'));
    					$kiinteisto->images()->attach($entity->id, ['jarjestys' => $maxJarjestys]);

    					//If the kiinteisto is julkinen and the image is 1. -> set it as julkinen
    					if($kiinteisto->julkinen == true && $maxJarjestys == 1) {
    						$kuva = Kuva::getSingle($entity->id)->first();
    						$kuva->julkinen = true;
    						$kuva->update();
    					}
    					break;
    				case 2:
    					$maxJarjestys = DB::table('kuva_rakennus')->where('rakennus_id', '=', $request->input('entiteetti_id'))->max('jarjestys')+1;
    					$rakennus = Rakennus::find($request->input('entiteetti_id'));
    					$rakennus->images()->attach($entity->id, ['jarjestys' => $maxJarjestys]);
    					break;
    				case 3:
    					$maxJarjestys = DB::table('kuva_porrashuone')->where('porrashuone_id', '=', $request->input('entiteetti_id'))->max('jarjestys')+1;
    					$porrashuone = Porrashuone::find($request->input('entiteetti_id'));
    					$porrashuone->images()->attach($entity->id, ['jarjestys' => $maxJarjestys]);
    					break;
    				case 5:
    					$maxJarjestys = DB::table('kuva_alue')->where('alue_id', '=', $request->input('entiteetti_id'))->max('jarjestys')+1;
    					$alue = Alue::find($request->input('entiteetti_id'));
    					$alue->images()->attach($entity->id, ['jarjestys' => $maxJarjestys]);
    					if($maxJarjestys == 1) {
    						$kuva = Kuva::getSingle($entity->id)->first();
    						$kuva->julkinen = true;
    						$kuva->update();
    					}
    					break;
    				case 6:
    					$maxJarjestys = DB::table('kuva_arvoalue')->where('arvoalue_id', '=', $request->input('entiteetti_id'))->max('jarjestys')+1;
    					$arvoalue = Arvoalue::find($request->input('entiteetti_id'));
    					$arvoalue->images()->attach($entity->id, ['jarjestys' => $maxJarjestys]);
    					if($maxJarjestys == 1) {
    						$kuva = Kuva::getSingle($entity->id)->first();
    						$kuva->julkinen = true;
    						$kuva->update();
    					}
    					break;
    				case 8:
    					$maxJarjestys = DB::table('kuva_kyla')->where('kyla_id', '=', $request->input('entiteetti_id'))->max('jarjestys')+1;
    					$kyla = Kyla::find($request->input('entiteetti_id'));
    					$kyla->images()->attach($entity->id, ['jarjestys' => $maxJarjestys]);
    					break;
    				case 9:
    				    $maxJarjestys = DB::table('kuva_suunnittelija')->where('suunnittelija_id', '=', $request->input('entiteetti_id'))->max('jarjestys')+1;
    				    $suunnittelija = Suunnittelija::find($request->input('entiteetti_id'));
    				    $suunnittelija->images()->attach($entity->id, ['jarjestys' => $maxJarjestys]);
    				    break;
    				}

		    		MipJson::setGeoJsonFeature();
		    		MipJson::addMessage(Lang::get('kuva.save_success'));
	    			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
    			}

    			// And commit the transaction as all went well
    			DB::commit();

    		} catch(Exception $e) {
    			DB::rollback();

    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('kuva.save_failed'));
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
    	if(!Kayttaja::hasPermission('rakennusinventointi.kuva.katselu')) {
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

    			$entity = Kuva::getSingle($id)->with('luoja')->with('muokkaaja')->first();
    			if(!$entity) {
    				MipJson::setGeoJsonFeature();
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    				MipJson::addMessage(Lang::get('kuva.search_not_found'));
    			}
    			else {
    				$entity->first();

    				$images = Kuva::getImageUrls($entity->polku.$entity->nimi);
    				$entity->url = $images->original;
    				$entity->url_tiny = $images->tiny;
    				$entity->url_small = $images->small;
    				$entity->url_medium = $images->medium;
    				$entity->url_large = $images->large;

    				MipJson::setGeoJsonFeature(null, $entity);
    				MipJson::addMessage(Lang::get('kuva.search_success'));
    			}
    		}
    		catch(QueryException $e) {
    			MipJson::addMessage(Lang::get('kuva.search_failed'));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
    }


    public function viewSmall($id) {

    	if(!is_numeric($id)) {
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    		return;
    	}

    	try {

    		$entity = Kuva::getSingle($id)->with('luoja')->first();
    		if(!$entity) {
    			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			return;
    		}

    		$images = Kuva::getImageUrls($entity->polku.$entity->nimi);
    		$url = $images->medium;

    		if ($url) {
    			return redirect( $url );
    		} else {
    			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			return;
    		}
    	} catch(QueryException $e) {
    		MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		return;
    	}
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
    	 * The user has permission to delete images he/she has uploaded even if the user has role inventojia, tutkija or ulkopuolinen tutkija
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.kuva.muokkaus', $id, 'kuva')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'otsikko'			=> 'required',
    			'kuvaus'			=> 'nullable|string',
    			'kuvaaja'			=> 'nullable|string',
    			"julkinen"			=> "boolean"
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
    			$entity = Kuva::find($id);
    			if(!$entity){
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('kuva.search_not_found'));
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			}
    			else {
    				$entity->fill($request->all());

    				$author_field = Kuva::UPDATED_BY;
    				$entity->$author_field = Auth::user()->id;

    				$entity->save();

    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('kuva.save_success'));
    				MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
    			}

    			DB::commit();

    		} catch(Exception $e) {
    			DB::rollback();
    			MipJson::setGeoJsonFeature();
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    			MipJson::addMessage(Lang::get('kuva.save_failed'));
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
    			"entiteetti_tyyppi" => "required|numeric|exists:entiteetti_tyyppi,id",
    			"entiteetti_id"		=> "required|numeric",

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
    	 * The user has permission to delete images he/she has uploaded even if the user has role inventojia, tutkija or ulkopuolinen tutkija
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.kuva.poisto', $id, 'kuva')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$entity = Kuva::find($id);

    	if(!$entity) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('kuva.search_not_found'));
    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    		return MipJson::getJson();
    	}

   		/*
   		 * delete file(s) from filesystem
   		 * --> do not delete files, just mark the file as "deleted" in db
   		 *
   		$file_path		= storage_path()."/".getenv('IMAGE_UPLOAD_PATH').$entity->polku.explode(".", $entity->nimi)[0];
   		$file_extension = explode(".", $entity->nimi)[1];
   		if(File::exists($file_path.".".$file_extension))
   			File::delete($file_path.".".$file_extension);
   		if(File::exists($file_path."_LARGE.".$file_extension))
   			File::delete($file_path."_LARGE.".$file_extension);
   		if(File::exists($file_path."_MEDIUM.".$file_extension))
   			File::delete($file_path."_MEDIUM.".$file_extension);
   		if(File::exists($file_path."_SMALL.".$file_extension))
   			File::delete($file_path."_SMALL.".$file_extension);
   		if(File::exists($file_path."_TINY.".$file_extension))
   			File::delete($file_path."_TINY.".$file_extension);
   		*/

   		try {

   			DB::beginTransaction();
   			Utils::setDBUser();

	   		$author_field = Kuva::DELETED_BY;
	   		$when_field = Kuva::DELETED_AT;
	   		$entity->$author_field = Auth::user()->id;
	   		$entity->$when_field = \Carbon\Carbon::now();
	   		$entity->save();

	   		// NO DELETION REQUIRED, UPDATE ABOVE DOES IT
	   		// $deleted_rows = $entity->delete();

	   		//Refer to table entiteetti_tyyppi
	   		switch ($request->input('entiteetti_tyyppi')) {
	   			case 1:
	   				Kiinteisto::find($request->input('entiteetti_id'))->images()->detach($id);

	   				//Update the next image to be the public one
	   				$minJarjestys = DB::table('kuva_kiinteisto')->where('kiinteisto_id', '=', $request->input('entiteetti_id'))->min('jarjestys');
	   				$kuva_kiinteisto = KuvaKiinteisto::where('kiinteisto_id', '=', $request->input('entiteetti_id'))->where('jarjestys', '=', $minJarjestys)->first();

	   				// Järjestetään jos kuva löytyy
	   				if($kuva_kiinteisto){
	   				    $kuva_kiinteisto->updateJarjestys(1, $kuva_kiinteisto->kuva_id, $kuva_kiinteisto->kiinteisto_id);

	   				    $kuva = Kuva::getSingle($kuva_kiinteisto->kuva_id)->first();//DB::table('kuva')->where('id', '=', $kuva_kiinteisto->kuva_id)->get();
	   				    $kiinteisto = Kiinteisto::getSingle($request->input('entiteetti_id'))->first();
	   				    //If the kiinteisto is julkinen and the image is 1. -> set it as julkinen
	   				    if($kiinteisto->julkinen == true) {
	   				        $kuva->julkinen = true;
	   				        $kuva->update();
	   				    }
	   				}

	   				break;
	   			case 2:
	   				Rakennus::find($request->input('entiteetti_id'))->images()->detach($id);
	   				break;
	   			case 3:
	   				Porrashuone::find($request->input('entiteetti_id'))->images()->detach($id);
	   				break;
	   			case 5:
	   				Alue::find($request->input('entiteetti_id'))->images()->detach($id);

	   				//Update the next image to be the public one
	   				$minJarjestys = DB::table('kuva_alue')->where('alue_id', '=', $request->input('entiteetti_id'))->min('jarjestys');
	   				$kuva_alue = KuvaAlue::where('alue_id', '=', $request->input('entiteetti_id'))->where('jarjestys', '=', $minJarjestys)->first();

	   				// Järjestetään jos kuva löytyy
	   				if($kuva_alue){
	   				    $kuva_alue->updateJarjestys(1, $kuva_alue->kuva_id, $kuva_alue->alue_id);

	   				    $kuva = Kuva::getSingle($kuva_alue->kuva_id)->first();
	   				    $kuva->julkinen = true;
	   				    $kuva->update();
	   				}

	   				break;
	   			case 6:
	   				Arvoalue::find($request->input('entiteetti_id'))->images()->detach($id);

	   				//Update the next image to be the public one
	   				$minJarjestys = DB::table('kuva_arvoalue')->where('arvoalue_id', '=', $request->input('entiteetti_id'))->min('jarjestys');
	   				$kuva_arvoalue = KuvaArvoalue::where('arvoalue_id', '=', $request->input('entiteetti_id'))->where('jarjestys', '=', $minJarjestys)->first();

	   				// Järjestetään jos kuva löytyy
	   				if($kuva_arvoalue){
	   				    $kuva_arvoalue->updateJarjestys(1, $kuva_arvoalue->kuva_id, $kuva_arvoalue->arvoalue_id);

	   				    $kuva = Kuva::getSingle($kuva_arvoalue->kuva_id)->first();
	   				    $kuva->julkinen = true;
	   				    $kuva->update();
	   				}

	   				break;
	   			case 8:
	   				Kyla::find($request->input('entiteetti_id'))->images()->detach($id);
	   				break;
	   			case 9:
	   			    Suunnittelija::find($request->input('entiteetti_id'))->images()->detach($id);
	   			    break;
	   		}

	   		//TODO: if there are more images, set the next one as #1 and public
	   		//for kiinteistö if kiinteisto is public
	   		//for alue and arvoalue.

	   		DB::commit();

	   		MipJson::addMessage(Lang::get('kuva.delete_success'));
	   		MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

   		} catch(Exception $e) {
   			DB::rollback();

   			MipJson::setGeoJsonFeature();
   			MipJson::addMessage(Lang::get('kuva.delete_failed'));
   			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
   		}

   		return MipJson::getJson();
   }

   public function reorder(Request $request) {

   	/*
   	 * Reordering kiinteisto images requires modify permission for kiinteisto. Reordering rakennus images requires modify permission for rakennus, and so on.
   	 * We could rely on the edit permission of the related entity, but this is just to make sure the user has the permission.
   	 */

   	//Refer to table entiteetti_tyyppi
   	$noPermission = false;

   	switch ($request->input('entiteetti_tyyppi_id')) {
   		case 1:
   			if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.muokkaus')) {
   				$noPermission = true;
   			}
   			break;
   		case 2:
   			if(!Kayttaja::hasPermission('rakennusinventointi.rakennus.muokkaus')) {
   				$noPermission = true;
   			}
   			break;
   		case 3:
   			if(!Kayttaja::hasPermission('rakennusinventointi.porrashuone.muokkaus')) {
   				$noPermission = true;
   			}
   			break;
   		case 5:
   			if(!Kayttaja::hasPermission('rakennusinventointi.alue.muokkaus')) {
   				$noPermission = true;
   			}

   			break;
   		case 6:
   			if(!Kayttaja::hasPermission('rakennusinventointi.arvoalue.muokkaus')) {
   				$noPermission = true;
   			}
   			break;
   		case 8:
   			if(!Kayttaja::hasPermission('rakennusinventointi.kyla.muokkaus')) {
   				$noPermission = true;
   			}
   			break;
   		case 9:
   		    if(!Kayttaja::hasPermission('rakennusinventointi.suunnittelija.muokkaus')) {
   		        $noPermission = true;
   		    }
   		    break;
   	}

   	/*
   	 * Role check - If the user doesn't have a permission to edit the entity the image is attached to - do not allow reordering.
   	 */
   	if($noPermission == true) {
   		MipJson::setGeoJsonFeature();
   		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
   		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
   		return MipJson::getJson();
   	}

   	$validator = Validator::make ( $request->all (), [
   			"idt" => "required",
   			"entiteetti_tyyppi_id" => "required|numeric|exists:entiteetti_tyyppi,id",
   			"entiteetti_id" => "required|numeric"
   	] );
   	if ($validator->fails ()) {
   		MipJson::setGeoJsonFeature ();
   		MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
   		foreach ( $validator->errors ()->all () as $error )
   			MipJson::addMessage ( $error );
   			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
   	} else {

   		try {
   			$order = 1;

   			try {
   				DB::beginTransaction();
   				Utils::setDBUser();

	   			//Refer to table entiteetti_tyyppi
	   			switch ($request->input('entiteetti_tyyppi_id')) {
	   				case 1:
	   					$kiinteisto = Kiinteisto::getSingle($request->input('entiteetti_id'))->first();
	   					foreach($request->idt as $id) {
	   						$img = KuvaKiinteisto::whereImageIdAndKiinteistoId($id, $request->input('entiteetti_id'))->first();
	   						$img->updateJarjestys($order, $img->kuva_id, $img->kiinteisto_id);

	   						//Set the first kuva to public and others as not public
	   						//But only if the kiinteisto itself is julkinen.
	   						if($order == 1 && $kiinteisto->julkinen == true){
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = true;
	   							$kuva->update();
	   						} else {
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = false;
	   							$kuva->update();
	   						}

	   						$order++;
	   					}
	   					break;
	   				case 2:
	   					$rakennus = Rakennus::getSingle($request->input('entiteetti_id'))->first();
	   					foreach($request->idt as $id) {
	   						$img = KuvaRakennus::whereImageIdAndRakennusId($id, $request->input('entiteetti_id'))->first();
	   						$img->updateJarjestys($order, $img->kuva_id, $img->rakennus_id);

	   						//Set the first kuva to public and others as not public
	   						if($order == 1){
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = true;
	   							$kuva->update();
	   						} else {
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = false;
	   							$kuva->update();
	   						}

	   						$order++;
	   					}
	   					break;
	   				case 3:
	   					$porrashuone = Porrashuone::getSingle($request->input('entiteetti_id'))->first();
	   					foreach($request->idt as $id) {
	   						$img = KuvaPorrashuone::whereImageIdAndPorrashuoneId($id, $request->input('entiteetti_id'))->first();
	   						$img->updateJarjestys($order, $img->kuva_id, $img->porrashuone_id);

	   						//Set the first kuva to public and others as not public
	   						if($order == 1){
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = true;
	   							$kuva->update();
	   						} else {
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = false;
	   							$kuva->update();
	   						}

	   						$order++;
	   					}
	   					break;
	   				case 5:
	   					$alue = Alue::getSingle($request->input('entiteetti_id'))->first();
	   					foreach($request->idt as $id) {
	   						$img = KuvaAlue::whereImageIdAndAlueId($id, $request->input('entiteetti_id'))->first();
	   						$img->updateJarjestys($order, $img->kuva_id, $img->alue_id);

	   						//Set the first kuva to public and others as not public
	   						if($order == 1){
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = true;
	   							$kuva->update();
	   						} else {
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = false;
	   							$kuva->update();
	   						}

	   						$order++;
	   					}
	   					break;
	   				case 6:
	   					$Arvoalue = Arvoalue::getSingle($request->input('entiteetti_id'))->first();
	   					foreach($request->idt as $id) {
	   						$img = KuvaArvoalue::whereImageIdAndArvoalueId($id, $request->input('entiteetti_id'))->first();
	   						$img->updateJarjestys($order, $img->kuva_id, $img->arvoalue_id);

	   						//Set the first kuva to public and others as not public
	   						if($order == 1){
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = true;
	   							$kuva->update();
	   						} else {
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = false;
	   							$kuva->update();
	   						}

	   						$order++;
	   					}
	   					break;
	   				case 8:
	   					$kyla = Kyla::getSingle($request->input('entiteetti_id'))->first();
	   					foreach($request->idt as $id) {
	   						$img = KuvaKyla::whereImageIdAndKylaId($id, $request->input('entiteetti_id'))->first();
	   						$img->updateJarjestys($order, $img->kuva_id, $img->kyla_id);

	   						//Set the first kuva to public and others as not public
	   						if($order == 1){
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = true;
	   							$kuva->update();
	   						} else {
	   							$kuva = Kuva::getSingle($id)->first();
	   							$kuva->julkinen = false;
	   							$kuva->update();
	   						}

	   						$order++;
	   					}
	   					break;
	   				case 9:
	   				    //$suunnittelija = Rakennus::getSingle($request->input('entiteetti_id'))->first();
	   				    foreach($request->idt as $id) {
	   				        $img = KuvaSuunnittelija::whereImageIdAndSuunnittelijaId($id, $request->input('entiteetti_id'))->first();
	   				        $img->updateJarjestys($order, $img->kuva_id, $img->suunnittelija_id);

	   				        //Set the first kuva to public and others as not public
	   				        if($order == 1){
	   				            $kuva = Kuva::getSingle($id)->first();
	   				            $kuva->julkinen = true;
	   				            $kuva->update();
	   				        } else {
	   				            $kuva = Kuva::getSingle($id)->first();
	   				            $kuva->julkinen = false;
	   				            $kuva->update();
	   				        }

	   				        $order++;
	   				    }
	   				    break;
	   			}

	   			DB::commit();
   			} catch (Exception $e) {
   				DB::rollback();
   				throw $e;
   			}

   			MipJson::addMessage ( Lang::get ( 'kuva.reoder_success' ) );
   		} catch ( Exception $e ) {
   			MipJson::setGeoJsonFeature ();
   			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
   			MipJson::addMessage ( Lang::get ( 'kuva.reorder_failed' ) );
   		}
   	}
   	return MipJson::getJson ();
   }

   /*
    * Siirrä kuva toiseen kiinteistöön / rakennukseen.
    */
   public function siirra(Request $request, $id) {

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
   			'kiinteistotunnus'				=> 'required|exists:kiinteisto,kiinteistotunnus',
   			'vanha_entiteetti_tyyppi_id'	=> 'required',
   			'vanha_entiteetti_id'			=> 'required'
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

   		$kuva = Kuva::getSingle($id)->first();

   		if(!$kuva){
   			//error, entity not found
   			MipJson::setGeoJsonFeature();
   			MipJson::addMessage(Lang::get('kuva.search_not_found'));
   			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);

   			return MipJson::getJson();
   		}

   		//Kiinteistötunnus -> siirretään kiinteistöön
   		//Kiinteistötunnus & palstanumero -> siirretään kiinteistöön
   		//Kiinteistötunnus & inventointinumero -> siirretään rakennukseen
   		//Kiinteistötunnus & palstanumero & inventointinumero -> siirretään rakennukseen
   		$rakennus = null;
   		$kiinteisto = null;

   		if($request->kiinteistotunnus && !$request->palstanumero){
   		    $kiinteisto = Kiinteisto::where('kiinteistotunnus', '=', $request->kiinteistotunnus)->whereNull('palstanumero')->get();
   		}elseif ($request->kiinteistotunnus && $request->palstanumero){
   		    $kiinteisto = Kiinteisto::where('kiinteistotunnus', '=', $request->kiinteistotunnus)->where('palstanumero', '=', $request->palstanumero)->get();
   		}

   		if(count($kiinteisto) == 0) {
   			MipJson::setGeoJsonFeature();
   			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
   			MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
   			return MipJson::getJson();
   		} else if(count($kiinteisto) > 1 && $request->inventointinumero == null) {
   			MipJson::setGeoJsonFeature();
   			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
   			MipJson::addMessage(Lang::get('kiinteisto.too_many_found'));
   			return MipJson::getJson();
   		}

   		$kiinteisto = $kiinteisto[0];

   		// Vain rakennuksille siirrettäessä tulee inventointinumero
   		if($request->inventointinumero) {
   		    $rakennus = Rakennus::where('kiinteisto_id', '=', $kiinteisto->id)->where('inventointinumero', '=', $request->inventointinumero)->get();
	   		if(count($rakennus) == 0) {
	   			MipJson::setGeoJsonFeature();
	   			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
	   			MipJson::addMessage(Lang::get('rakennus.search_not_found'));
	   			return MipJson::getJson();
	   		} else if(count($rakennus) > 1) {
	   			MipJson::setGeoJsonFeature();
	   			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
	   			MipJson::addMessage(Lang::get('rakennus.too_many_found'));
	   			return MipJson::getJson();
	   		}

	   		$rakennus = $rakennus[0];
   		}

   		// wrap the operations into a transaction
   		DB::beginTransaction();
   		Utils::setDBUser();

   		$vanha_kiinteisto = null;

   		try {

   			//DETACH alkuperäisestä
   			switch ($request->input('vanha_entiteetti_tyyppi_id')) {
   				case 1:
   					$vanha_kiinteisto = Kiinteisto::find($request->input('vanha_entiteetti_id'))->images()->detach($id);
   					break;
   				case 2:
   					Rakennus::find($request->input('vanha_entiteetti_id'))->images()->detach($id);
   					break;
   			}

   			if(!$rakennus) {
   				//Liitetään kiinteistöön
   				$maxJarjestys = DB::table('kuva_kiinteisto')->where('kiinteisto_id', '=', $kiinteisto->id)->max('jarjestys')+1;
   				$kiinteisto->images()->attach($kuva->id, ['jarjestys' => $maxJarjestys]);

   				//Jos kiinteisto on julkinen ja kuva on 1., tee kuvasta julkinen
   				if($kiinteisto->julkinen == true && $maxJarjestys == 1) {
   					$kuva->julkinen = true;
   				}

   			} else {
   				//Liitetään rakennukseen
   				$maxJarjestys = DB::table('kuva_rakennus')->where('rakennus_id', '=', $rakennus->id)->max('jarjestys')+1;
   				$rakennus->images()->attach($kuva->id, ['jarjestys' => $maxJarjestys]);

   				//Ainoastaan kiinteistöllä on julkisia kuvia, päivitetään kuvan julkisuus falseksi
   				$kuva->julkinen = false;

   				//Jos siirrettiin rakennukselta kiinteistölle, kiinteistö tarvitsee uuden julkisen kuvan.
   				if($request->input('vanha_entiteetti_tyyppi_id') == 1) {

   					//Kuva_kiinteisto taulun pienimmän kuvan jarjestys muutetaan 1.
   					//Kuva taulussa kuva_kiinteisto taulun pienimmän kuvan kuvan julkisuus asetetaan trueksi.

   					//Tee kiinteistön pienimmän järjestyksen omaavasta kuvasta jarjestys 1.
   					$min_jarjestys = DB::table('kuva_kiinteisto')->where('kiinteisto_id', '=', $request->input('vanha_entiteetti_id'))->min('jarjestys');
   					$uusi_julkinen_kuva_ref = DB::table('kuva_kiinteisto')->where('kiinteisto_id', '=', $request->input('vanha_entiteetti_id'))->where('jarjestys', '=', $min_jarjestys);
   					$uusi_julkinen_kuva_ref->update(array('jarjestys' => 1));

   					//Haetaan kuva-taulusta vastaava rivi. Laravellin kummallisuuksien takia toteutus on tällainen, sen voisi varmaan tehdä järkevämminkin.
   					$uusi_julkinen_kuva_ref = DB::table('kuva_kiinteisto')->where('kiinteisto_id', '=', $request->input('vanha_entiteetti_id'))->where('jarjestys', '=', 1);
   					$uusi = $uusi_julkinen_kuva_ref->first();

   					if($uusi) {
   						$uusi_julkinen_kuva_kuva = DB::table('kuva')->where('id', '=', $uusi->kuva_id);
   						$uusi_julkinen_kuva_kuva->update(array('julkinen' => true));
   					}
   				}

   			}

   			$author_field = Rakennus::UPDATED_BY;
   			$kuva->$author_field = Auth::user()->id;

   			$kuva->update();

   		} catch(Exception $e) {
   			// Exception, always rollback the transaction
   			DB::rollback();
   			throw $e;
   		}

   		// And commit the transaction as all went well
   		DB::commit();

   		if($rakennus) {
   			MipJson::setGeoJsonFeature(null, array("id" => $kuva->id, "rakennus_id" => $rakennus->id, "kiinteisto_id" => $kiinteisto->id));
   		}else {
   			MipJson::setGeoJsonFeature(null, array("id" => $kuva->id, "kiinteisto_id" => $kiinteisto->id));
   		}

   		MipJson::addMessage(Lang::get('rakennus.save_success'));
   		MipJson::setResponseStatus(Response::HTTP_OK);

   	} catch(Exception $e) {
   		MipJson::setGeoJsonFeature();
   		MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
   		MipJson::addMessage(Lang::get('rakennus.save_failed'));
   	}

   	return MipJson::getJson();
   }

   /*
    * Siirrä useita kuvia toiseen kiinteistöön / rakennukseen.
    * TODO: Palastele koodit pienempiin metodeihin ja muuta siirraUseita() ja siirra() metodit hyödyntämään samaa koodia.
    */
   public function siirraUseita(Request $request) {

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
   			'kiinteistotunnus'				=> 'required|exists:kiinteisto,kiinteistotunnus',
   			'vanha_entiteetti_tyyppi_id'	=> 'required',
   			'vanha_entiteetti_id'			=> 'required'
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
   		foreach($request->kuvaIdt as $kuvaId) {
	   		$kuva = Kuva::getSingle($kuvaId)->first();

	   		if(!$kuva){
	   			//error, entity not found
	   			MipJson::setGeoJsonFeature();
	   			MipJson::addMessage(Lang::get('kuva.search_not_found'));
	   			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);

	   			return MipJson::getJson();
	   		}

	   		//Kiinteistötunnus -> siirretään kiinteistöön
	   		//Kiinteistötunnus & palstanumero -> siirretään kiinteistöön
	   		//Kiinteistötunnus & inventointinumero -> siirretään rakennukseen
	   		//Kiinteistötunnus & palstanumero & inventointinumero -> siirretään rakennukseen
	   		$rakennus = null;
	   		$kiinteisto = null;

	   		if($request->palstanumero) {
	   			$kiinteisto = Kiinteisto::where('palstanumero', $request->palstanumero)->get();
	   		} else {
	   			$kiinteisto = Kiinteisto::where('kiinteistotunnus', $request->kiinteistotunnus)->whereNull('palstanumero')->get();
	   		}

	   		if(count($kiinteisto) == 0) {
	   			MipJson::setGeoJsonFeature();
	   			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
	   			MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
	   			return MipJson::getJson();
	   		} else if(count($kiinteisto) > 1 && $request->inventointinumero == null) {
	   			MipJson::setGeoJsonFeature();
	   			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
	   			MipJson::addMessage(Lang::get('kiinteisto.too_many_found'));
	   			return MipJson::getJson();
	   		}

	   		$kiinteisto = $kiinteisto[0];

	   		if($request->inventointinumero) {
	   			$rakennus = Rakennus::where('kiinteisto_id', $kiinteisto->id)->where('inventointinumero', $request->inventointinumero)->get();
	   			if(count($rakennus) == 0) {
	   				MipJson::setGeoJsonFeature();
	   				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
	   				MipJson::addMessage(Lang::get('rakennus.search_not_found'));
	   				return MipJson::getJson();
	   			} else if(count($rakennus) > 1) {
	   				MipJson::setGeoJsonFeature();
	   				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
	   				MipJson::addMessage(Lang::get('rakennus.too_many_found'));
	   				return MipJson::getJson();
	   			}

	   			$rakennus = $rakennus[0];
	   		}

	   		// wrap the operations into a transaction
	   		DB::beginTransaction();
	   		Utils::setDBUser();

	   		$vanha_kiinteisto = null;

	   		try {

	   			//DETACH alkuperäisestä
	   			switch ($request->input('vanha_entiteetti_tyyppi_id')) {
	   				case 1:
	   					$vanha_kiinteisto = Kiinteisto::find($request->input('vanha_entiteetti_id'))->images()->detach($kuvaId);
	   					break;
	   				case 2:
	   					Rakennus::find($request->input('vanha_entiteetti_id'))->images()->detach($kuvaId);
	   					break;
	   			}

	   			if(!$rakennus) {
	   				//Liitetään kiinteistöön
	   				$maxJarjestys = DB::table('kuva_kiinteisto')->where('kiinteisto_id', '=', $kiinteisto->id)->max('jarjestys')+1;
	   				$kiinteisto->images()->attach($kuva->id, ['jarjestys' => $maxJarjestys]);

	   				//Jos kiinteisto on julkinen ja kuva on 1., tee kuvasta julkinen
	   				if($kiinteisto->julkinen == true && $maxJarjestys == 1) {
	   					$kuva->julkinen = true;
	   				}

	   			} else {
	   				//Liitetään rakennukseen
	   				$maxJarjestys = DB::table('kuva_rakennus')->where('rakennus_id', '=', $rakennus->id)->max('jarjestys')+1;
	   				$rakennus->images()->attach($kuva->id, ['jarjestys' => $maxJarjestys]);

	   				//Ainoastaan kiinteistöllä on julkisia kuvia, päivitetään kuvan julkisuus falseksi
	   				$kuva->julkinen = false;

	   				//Jos siirrettiin rakennukselta kiinteistölle, kiinteistö tarvitsee uuden julkisen kuvan.
	   				if($request->input('vanha_entiteetti_tyyppi_id') == 1) {

	   					//Kuva_kiinteisto taulun pienimmän kuvan jarjestys muutetaan 1.
	   					//Kuva taulussa kuva_kiinteisto taulun pienimmän kuvan kuvan julkisuus asetetaan trueksi.

	   					//Tee kiinteistön pienimmän järjestyksen omaavasta kuvasta jarjestys 1.
	   					$min_jarjestys = DB::table('kuva_kiinteisto')->where('kiinteisto_id', '=', $request->input('vanha_entiteetti_id'))->min('jarjestys');
	   					$uusi_julkinen_kuva_ref = DB::table('kuva_kiinteisto')->where('kiinteisto_id', '=', $request->input('vanha_entiteetti_id'))->where('jarjestys', '=', $min_jarjestys);
	   					$uusi_julkinen_kuva_ref->update(array('jarjestys' => 1));

	   					//Haetaan kuva-taulusta vastaava rivi. Laravellin kummallisuuksien takia toteutus on tällainen, sen voisi varmaan tehdä järkevämminkin.
	   					$uusi_julkinen_kuva_ref = DB::table('kuva_kiinteisto')->where('kiinteisto_id', '=', $request->input('vanha_entiteetti_id'))->where('jarjestys', '=', 1);
	   					$uusi = $uusi_julkinen_kuva_ref->first();

	   					if($uusi) {
	   						$uusi_julkinen_kuva_kuva = DB::table('kuva')->where('id', '=', $uusi->kuva_id);
	   						$uusi_julkinen_kuva_kuva->update(array('julkinen' => true));
	   					}
	   				}

	   			}

	   			$author_field = Rakennus::UPDATED_BY;
	   			$kuva->$author_field = Auth::user()->id;

	   			$kuva->update();

	   		} catch(Exception $e) {
	   			// Exception, always rollback the transaction
	   			DB::rollback();
	   			throw $e;
	   		}

	   		// And commit the transaction as all went well
	   		DB::commit();

   		}

   		if($rakennus) {
   			MipJson::setGeoJsonFeature(null, array("id" => $request->kuvaIdt, "rakennus_id" => $rakennus->id, "kiinteisto_id" => $kiinteisto->id));
   		}else {
   			MipJson::setGeoJsonFeature(null, array("id" => $request->kuvaIdt, "kiinteisto_id" => $kiinteisto->id));
   		}

   		MipJson::addMessage(Lang::get('rakennus.save_success'));
   		MipJson::setResponseStatus(Response::HTTP_OK);

   	} catch(Exception $e) {
   		MipJson::setGeoJsonFeature();
   		MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
   		MipJson::addMessage(Lang::get('rakennus.save_failed'));
   	}

   	return MipJson::getJson();
   }
}
