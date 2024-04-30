<?php

namespace App\Http\Controllers\Muistot;

use App\Utils;
use App\Rak\Kuva;
use App\Muistot\Muistot_aihe;
use App\Muistot\Muistot_henkilo;
use App\Muistot\Muistot_kuva;
use App\Muistot\Muistot_kysymys;
use App\Muistot\Muistot_muisto;
use App\Muistot\Muistot_vastaus;
use App\Http\Controllers\Controller;
use App\Library\Gis\MipGis;
use App\Library\String\MipJson;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Carbon\Carbon;
use App\Kayttaja;
use Exception;

class MuistoController extends Controller {

    public function getMuisto($id) 
    {
        Log::debug("Muistot " . $id . "api " . env("prikka_api_key"));
        MipJson::addMessage("Muisto tallenneltu");
        return MipJson::getJson();
    }

    public function saveMuistot(Request $request) 
    {
        Log::channel('prikka')->info("saveMuistot " . $request . " recieved");

        $errorArray = array();
        foreach($request->muistot as $muisto)
        {
            try
            {
                DB::beginTransaction();
              
                if(!in_array('muisto_id', array_keys($muisto)))
                {
                    array_push($errorArray, 'No id in muisto');
                }
                else 
                {
                    $validationResult = $this->validateMuisto($muisto);
                    if(!empty($validationResult))
                    {
                        foreach($validationResult as $vresult)
                        {
                            array_push($errorArray, $vresult);
                        }
                    }
                    else if(Muistot_aihe::where('prikka_id', $muisto['aihe_id'])->get()->first() == null)
                    {
                        array_push($errorArray, $muisto['muisto_id'] . ' aihe does not exist');
                    }
                    else 
                    {
                        $muistoEntity = Muistot_muisto::find($muisto['muisto_id']);
                        if(!$muistoEntity)
                        {
                            $muistoEntity = new Muistot_muisto();
                        }
                        $henkiloEntity = Muistot_henkilo::find($muisto['henkiloid']);
                        if(!$henkiloEntity)
                        {
                            $henkiloEntity = new Muistot_henkilo();
                        }
                        foreach($muisto as $key=>$value) {
                            if($key == 'muisto_id')
                            {
                                 $muistoEntity->prikka_id = $value;
                            }
                            else if($key == 'aihe_id')
                            {
                                $muistoEntity->muistot_aihe_id = $value;
                            }
                            else if($key == 'vastaukset')
                            {
                                //do nothing for now
                            }
                            else if($key == 'valokuvat')
                            {
                                //do nothing for now
                            }
                            else if($key == 'henkiloid')
                            {
                                $henkiloEntity->prikka_id = $value;
                                $muistoEntity->muistot_henkilo_id = $value;
                            }
                            else if($key == 'etunimi')
                            {
                                $henkiloEntity->$key = $value;
                            }
                            else if($key == 'sukunimi')
                            {
                                $henkiloEntity->$key = $value;
                            }
                            else if($key == 'sahkoposti')
                            {
                                $henkiloEntity->$key = $value;
                            }
                            else if($key == 'syntymavuosi')
                            {   
                                $henkiloEntity->$key = $value;
                            }
                            else if($key == 'nimimerkki')
                            {
                                $henkiloEntity->$key = $value;
                            }
                            else if($key == 'tapahtumapaikka')
                            {
                                // MipGis assumes space between coordinates, Prikka uses semicolon
                                $formattedValue = str_replace(';', ' ', $value);
                                $geom = MipGis::getPointGeometryValue($formattedValue);
                                $muistoEntity->$key = $geom;
                            }
                            else
                            {
                                 $muistoEntity->$key = $value;
                            }
                        }

                        $henkiloEntity->save();
                        $muistoEntity->save();

                        if(!is_null($muisto['vastaukset']))
                        {
                            $res=Muistot_vastaus::where('muistot_muisto_id',$muistoEntity->prikka_id)->delete();

                            foreach($muisto['vastaukset'] as $vastaus)
                            {
                                $entityVastaus = new Muistot_vastaus();
                                foreach($vastaus as $key=>$value)
                                {
                                    if($key == 'kysymys_id')
                                    {
                                        $entityVastaus->muistot_kysymys_id = $value;
                                    }
                                    else
                                    {
                                        $entityVastaus->$key = $value;
                                    }
                                }
                                $entityVastaus->muistot_muisto_id = $muistoEntity->prikka_id;

                                $entityVastaus->save();
                            }
                        }

                        if(!is_null($muisto['valokuvat']))
                        {
                            // In case Muisto has previous images, all of them need to be deleted
                            $this->deleteAllImagesFromMuisto($muistoEntity->prikka_id);
                            foreach($muisto['valokuvat'] as $valokuva)
                            {
                                $entityKuva = new Muistot_kuva();
                                foreach($valokuva as $key=>$value)
                                {
                                    if($key == 'kuva_id')
                                    {
                                        $entityKuva->prikka_id = $value;
                                    }
                                    else if($key == 'kuvatiedosto')
                                    {
                                        // TODO: Tarkista että ok
                                        $imageOk = $this->saveImage($value, $entityKuva);
                                        if (!$imageOk) {
                                            // Logataan virhe
                                            Log::channel('prikka')->info("Kuvan tallennus epäonnistui");
                                        }
                                    }
                                    else 
                                    {
                                        $entityKuva->$key = $value;
                                    }

                                    $entityKuva->muistot_muisto_id = $muistoEntity->prikka_id;
                                }

                                $entityKuva->save();
                            }
                        }
                    }
                }
              
                DB::commit();
                
            }
            catch(Exception $e)
            {
                throw $e;
                array_push($errorArray, $muisto['muisto_id'] . ': failed to add');
                DB::rollback();
            }
        }
      
        $ret = (object) array('Errors' => $errorArray);
        Log::channel('prikka')->info("saveMuistot " . $ret . " success");
        return $ret;
    }

    // Save image. Returns true if the image was saved successfully, otherwise false.
    private function saveImage($imageStringBase64, Muistot_kuva &$kuvaEntity) : bool {

        // Check it's not empty
        if (empty($imageStringBase64)) {
            Log::channel('prikka')->info("Kuvaa ei ole");
                return false;
        }
    
        // Kuvan koko pitäisi tarkistaa, mutta varsinaisen kuvatiedoston kokoa on vaikea päätellä,
        // ennen kuin kuva on talletettu tiedostoon.
        // Base64-koodattu kuvadata ei kerro kuvan tavukokoa. 
        //  Käytännössä ongelmaa ei pitäisi olla, 
        // koska Prikka rajoittaa kuvakoon 10 megatavuun, joka on MIPin rajoitusta pienempi.
        //Log::channel('prikka')->info("Kuvaa on, pituus " . strlen($imageStringBase64) . " merkkiä");

        // Decode the base64 string to a binary string and save to file
        $decodedString = base64_decode($imageStringBase64);
        //Log::channel('prikka')->info("isImage function decodedString pituus: " . strlen($decodedString));

        $image = Image::make($decodedString);

        // Check if the string contains a valid image
        if (!$image) {
            Log::channel('prikka')->info("Kuvatiedosto ei sisällä kuvaa");
            return false;
        }

        // Determine the image format, and file extension based on that
        $imageFormat = $image->mime();
        $file_extension = $this->GetFileExtensionFromImageFormat($imageFormat);

        if (empty($file_extension)) {
            return false;
        }
            
        /*
            * Save image to file on the server.
            */
        $file_name			= Str::random(32);
        $file_basepath		= storage_path()."/".config('app.image_upload_path');
        $file_subpath		= Carbon::now()->format("Y/m/");
        $file_path			= $file_basepath.$file_subpath;
        $file_fullname		= $file_path.$file_name.".".$file_extension;
        // $user_id			= JWTAuth::toUser(JWTAuth::getToken())->id;

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

        // Now we have correct filename, let's save
        try {

            $image->save($file_fullname);
            if (File::exists($file_fullname)) {


            // File is saved.    
            $fileSize = filesize($file_fullname);
           // Log::channel('prikka')->info("saveImage function saved image file to: " . $file_fullname . " size: " . $fileSize . " bytes");
            }
            else {
                //Log::channel('prikka')->info("saveImage function failed to save image to file: " . $file_fullname);
                return false;
            }

            // Save the file path to the entity
            $kuvaEntity->nimi = $file_name.".".$file_extension;
            $kuvaEntity->polku = $file_subpath;

            // ***** THUMBNAILS
            $this->createThumbnailImages($file_fullname, $file_path, $file_name);

            return true;

        }            
        catch (Exception $e) {
            //Log::channel('prikka')->info("saveImage function failed to save image to file: " . $e->getMessage());
            return false;
        }            

    }

    /**
     * Get file extension based on image format
     * @param string $imageFormat
     * @return string
     */
    private function GetFileExtensionFromImageFormat($imageFormat) {
        //Log::channel('prikka')->info("Image format: " . $imageFormat);
        switch ($imageFormat) {
            case 'image/jpeg':
                return 'jpg';
            case 'image/png':
                return 'png';
            case 'image/webp' :
                return 'webp';
            // Add more cases for other image formats if needed
            default:
                // error_log("Unknown image format: " . $imageFormat);
                Log::channel('prikka')->info("Unknown image format: " . $imageFormat);
                return '';
        }
    }

    /**
     * Create thumbnail images
     * Koodi on kopioitu suoraan KuvaController-luokasta, ei muutoksia.
     * @param string $file_fullname
     * @param string $file_path
     * @param string $file_name
     */
    private function createThumbnailImages($file_fullname, $file_path, $file_name )
    {
    
        /*
        * Create thumbnails
            * TODO: Tee järkevämmäksi.
        */
    
        $thumb_extension = 'jpg';
        //Large
        $img = Image::make($file_fullname)->encode('jpg');
            try {
                $img->orientate();
            } catch (Exception $e) {
                // Log for details
                Log::debug($e);
            }
        $img_large = Kuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_large'))[0]));
        $img_large->save($file_path.$file_name."_LARGE.".$thumb_extension);

        //Medium
        $img = Image::make($file_fullname)->encode('jpg');
            try {
                $img->orientate();
            } catch (Exception $e) {
                // Log for details
                Log::debug($e);
            }
        $img_medium = Kuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_medium'))[0]));
        $img_medium->save($file_path.$file_name."_MEDIUM.".$thumb_extension);

        //Small
        $img = Image::make($file_fullname)->encode('jpg');
            try {
                $img->orientate();
            } catch (Exception $e) {
                // Log for details
                Log::debug($e);
            }
        $img_small = Kuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_small'))[0]));
        $img_small->save($file_path.$file_name."_SMALL.".$thumb_extension);

        //Tiny
        $img = Image::make($file_fullname)->encode('jpg');
            try {
                $img->orientate();
            } catch (Exception $e) {
                // Log for details
                Log::debug($e);
            }
        $img_tiny = Kuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_tiny'))[0]));
        $img_tiny->save($file_path.$file_name."_TINY.".$thumb_extension);
    
    
                
    }

    /**
     * Delete all images related to one Muisto.
     * Also all related image files are deleted, including the created tumbnails.
     * @param $muistoId
     */
    private function deleteAllImagesFromMuisto($muistoId) {
        // Retrieve the rows
        $muistotKuvas = Muistot_kuva::where('muistot_muisto_id', $muistoId)->get();
    
        foreach ($muistotKuvas as $muistotKuva) {
    
            // delete file(s) from filesystem
   	        $file_path		= storage_path()."/".getenv('IMAGE_UPLOAD_PATH').$muistotKuva->polku.explode(".", $muistotKuva->nimi)[0];
   	        $file_extension = explode(".", $muistotKuva->nimi)[1];
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

        }
    
        // Delete the rows
        $res = Muistot_kuva::where('muistot_muisto_id', $muistoId)->delete();
    }

    /**
     * Validate muisto
     * @param $muisto
     * @return array
     */
    private function validateMuisto($muisto) {
        $errorArray = array();
        $requiredValues = array('aihe_id', 'luotu', 'paivitetty', 'alkaa', 'loppuu', 'julkinen', 'kieli', 'henkiloid', 'nimimerkki', 'etunimi', 'sukunimi', 'sahkoposti', 'syntymavuosi');

        if($muisto['muisto_id'] == null)
        {
            array_push($errorArray, 'id value null');
            return $errorArray;
        }

        foreach ($muisto as $key => $value) {
            if(in_array($key, $requiredValues) && ($value != false && ($value == null || $value == '')))
            {
                array_push($errorArray, $muisto['muisto_id'] . ' ' . $key . ' value null');
            }
        }

        foreach($requiredValues as $rvalue)
        {
            if(!in_array($rvalue, array_keys($muisto)))
            {
                array_push($errorArray, $muisto['muisto_id'] . ' ' . $rvalue . ' value null');
            }
        }

        foreach($muisto['vastaukset'] as $vastaus)
        {
            if(!in_array('kysymys_id', array_keys($vastaus)) || $vastaus['kysymys_id'] == null)
            {
                array_push($errorArray, $muisto['muisto_id'] . ' vastaus kysymys_id value null');
            }
        }

        foreach($muisto['valokuvat'] as $kuva)
        {
            if(!in_array('kuva_id', array_keys($kuva)) || $kuva['kuva_id'] == null)
            {
                array_push($errorArray, $muisto['muisto_id'] . ' kuva kuva_id value null');
            }

            if(!in_array('kuvatiedosto', array_keys($kuva)) || $kuva['kuvatiedosto'] == null)
            {
                array_push($errorArray, $muisto['muisto_id'] . ' kuva ' . $kuva['$kuva_id'] . ' Kuvatiedosto value null');
            }
        }

        return $errorArray;
    }
    
    /**
     * Get all memories from the database
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {

        /*
         * Role check
         */

        if(!Kayttaja::hasPermission('muistot.muisto.katselu')) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all(), [
            "id"				=> "numeric|exists:muistot_muisto,prikka_id",
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
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "prikka_id";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $muistot = Muistot_muisto::getAll();

            if(Kayttaja::hasPermission('muistot.yksityinen_muisto.katselu'))
            {
                $muistot = $muistot->with('muistot_henkilo');
            }
            else
            {
                $muistot = $muistot->with('muistot_henkilo_filtered');
            }
            $muistot = $muistot->with('muistot_aihe');

            /*
             * If ANY search terms are given limit results by them
             */
            if($request->id) {
                $muistot->withPrikkaId($request->id);
            }
            if($request->aihe) {
                $muistot->withAihe($request->aihe);
            }
            if($request->henkilo) {
                $muistot->withHenkilo($request->henkilo);
            }
            if($request->alkaa) {
                $muistot->withAlkaa($request->alkaa);
            }
            if($request->loppuu) {
                $muistot->withLoppuu($request->loppuu);
            }
            if($request->polygonrajaus) {
                $muistot->withPolygon($request->polygonrajaus);
            }
            if($request->aluerajaus) {
                $muistot->withBoundingBox($request->aluerajaus);
            }

            if(!Kayttaja::hasPermission('muistot.yksityinen_muisto.katselu'))
            {
                $muistot->withJulkinen('true');
            }

            // order the results by given parameters
            $muistot->withOrderBy($request->aluerajaus, $jarjestys_kentta, $jarjestys_suunta);

            // calculate the total rows of the search results
            $total_rows = Utils::getCount($muistot);//count($kiinteistot->get());

            // ID:t listaan ennen rivien rajoitusta
            $kori_id_lista = Utils::getPrikkaIdList($muistot);

            // limit the results rows by given params
            $muistot->withLimit($rivi, $riveja);

            // Execute the query
            $muistot = $muistot->get();

            MipJson::initGeoJsonFeatureCollection(count($muistot), $total_rows);
            foreach ($muistot as $muisto) {
                //TODO: Leaves artefact in the return list
                if($muisto->muistot_henkilo_filtered)
                {
                    $muisto->muistot_henkilo = $muisto->muistot_henkilo_filtered;
                }

                /*
                * clone $kiinteisto so we can handle the "properties" separately
                * -> remove "sijainti" from props
                */
                $properties = clone($muisto);
                unset($properties['sijainti']);
                //$properties->rakennukset = $buildings;
                //$properties->test = $kiinteisto->test;
                $sijainti = (array) json_decode($muisto->sijainti);
                $sijainti['coordinates'] = array_reverse($sijainti['coordinates']);
                MipJson::addGeoJsonFeatureCollectionFeaturePoint($sijainti, $properties);
            }

            /*
             * Koritoiminnallisuus. Palautetaan id:t listana.
             */
            MipJson::setIdList($kori_id_lista);

            MipJson::addMessage(Lang::get('muistot.search_success'));

        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('muistot.search_failed'));
        }

        return MipJson::getJson();
    }

    /**
     * Get single memory from the database
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id) {

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

                if(Kayttaja::hasPermission('muistot.yksityinen_muisto.katselu'))
                {
                    $muisto = Muistot_muisto::getSingle($id)
                    ->with( array(
                        'Muistot_vastaus',
                        'Muistot_henkilo',
                        'Muistot_vastaus.Muistot_kysymys',
                        'Muistot_aihe',
                        'Kiinteistot'
                    ))->first();
                }

                if($muisto) {
                    //TODO: Leaves artefact in the return list
                    if($muisto->muistot_henkilo_filtered)
                    {
                        $muisto->muistot_henkilo = $muisto->muistot_henkilo_filtered;
                    }
                    $properties = clone($muisto);
                    unset($properties['sijainti']);

                    $sijainti = (array) json_decode($muisto->sijainti);
                    $sijainti['coordinates'] = array_reverse($sijainti['coordinates']);
                    MipJson::setGeoJsonFeature($sijainti, $properties);
                    MipJson::addMessage(Lang::get('muistot.search_success'));
                }
                else {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('muistot.search_not_found'));
                }
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('muistot.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

  // TODO: Not final!
  public function update_estates(Request $request, $muistoId)
  {
      Log::channel('prikka')->info("update_estates");
      /*
       * Käyttöoikeustarkistus
       */
      if(Auth::user()->rooli != 'tutkija' && Auth::user()->rooli != 'pääkäyttäjä') {
          MipJson::setGeoJsonFeature();
          MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
          MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
          return MipJson::getJson();
      }

      $validator = Validator::make($request->all()['properties'], [
          // tämä oln kommenteissa myös toimenpidecontrollerissa
          // josta tämä on kopioitu
          // 'muisto_id'		=> 'required|string'
      ]);

      if ($validator->fails()) {
        Log::channel('prikka')->info("update_topics validator fails");
          MipJson::setGeoJsonFeature();
          MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
          foreach($validator->errors()->all() as $error) {
              MipJson::addMessage($error);
          }
          MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
          return MipJson::getJson();
      }

      try {

          $muisto = Muistot_muisto::find($muistoId);

          if(!$muisto){
            Log::channel('prikka')->info("muisto not found");
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('konservointiHallinta.operation_search_not_found'));
            MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
            return MipJson::getJson();
          }

          Log::channel('prikka')->info("muisto found");
          try {
            DB::beginTransaction();
            Utils::setDBUser();

                if($request->input('properties.kiinteistot')){
                  // The request should contain an array of Kiinteisto objects
                  $kiinteistoObjects = $request->input('properties.kiinteistot');

                  Log::channel('prikka')->info("kiinteistoObjects: " . json_encode($kiinteistoObjects));
              
                  $kiinteistoIds = array_map(function ($kiinteisto) {
                      return $kiinteisto['id'];
                  }, $kiinteistoObjects);

                 Log::channel('prikka')->info("updateKiinteistot " . $muistoId . " kiinteistoIds " . json_encode($kiinteistoIds));
              
                  // Sync the Kiinteisto items
                 $muisto->kiinteistot()->sync($kiinteistoIds);
              }   
              else {
                  // If the request does not contain Kiinteisto objects, remove all Kiinteisto items
                  Log::channel('prikka')->info("updateKiinteistot " . $muistoId . " kiinteistoIds null");
              }     

            DB::commit();
          } catch(Exception $e) {
            DB::rollback();
            Log::channel('prikka')->info("updateKiinteistot " . $muistoId . " failed " . $e->getMessage());
            throw $e;
          }

          Log::channel('prikka')->info("updateKiinteistot " . $muistoId . " success");
          MipJson::addMessage(Lang::get('konservointiHallinta.operation_save_success'));
          MipJson::setGeoJsonFeature(null, $muisto);
          MipJson::setResponseStatus(Response::HTTP_OK);

      } catch(Exception $e) {

        Log::channel('prikka')->info("updateKiinteistot " . $muistoId . " failed " . $e->getMessage());
      MipJson::setGeoJsonFeature();
      MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
      MipJson::addMessage(Lang::get('konservointiHallinta.operation_save_failed'));
    }

    return MipJson::getJson();
  }

}