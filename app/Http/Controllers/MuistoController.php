<?php

namespace App\Http\Controllers;

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
use Exception;

class MuistoController extends Controller {

    public function getMuisto($id) 
    {
        Log::debug("Muistoot " . $id . "api " . env("prikka_api_key"));
        MipJson::addMessage("Muisto tallenneltu");
        return MipJson::getJson();
    }

    public function saveAiheet(Request $request) 
    {
        $errorArray = array();
        foreach($request->aiheet as $aiheArray)
        {
            try
            {
                DB::beginTransaction();

                $aiheEntity = Muistot_aihe::find($aiheArray['aihe_id']);
                if(!$aiheEntity)
                {
                    $aiheEntity = new Muistot_aihe();
                }
                foreach($aiheArray as $key=>$value) 
                {
                    if($key == 'aihe_id')
                    {
                        $aiheEntity->prikka_id = $value;
                    }
                    else if($key == 'kysymykset')
                    {
                        //do nothing for now
                    }
                    else
                    {
                         $aiheEntity->$key = $value;
                    }
                }

                $aiheEntity->save();

                foreach($aiheArray['kysymykset'] as $kysymys)
                {
                    $entityKysymys = Muistot_kysymys::find($kysymys['kysymys_id']);
                    if(!$entityKysymys)
                    {
                        $entityKysymys = new Muistot_kysymys();
                    }
                    foreach($kysymys as $kysymKey=>$kysymValue)
                    {
                        if($kysymKey == 'kysymys_id')
                        {
                            $entityKysymys->prikka_id = $kysymValue;
                        }
                        else
                        {
                            $entityKysymys->$kysymKey = $kysymValue;
                        }
                    }
                    $entityKysymys->muistot_aihe_id = $aiheEntity->prikka_id;

                    $entityKysymys->save();
                }

                DB::commit();
            }
            catch(Exception $e)
            {
                $newError = array($aiheArray['aihe_id'], 'Lis�ys ep�onnistui');
                $errorArray = $errorArray + $newError;
                DB::rollback();
            }
        }

        MipJson::addMessage(json_encode($errorArray));

        return MipJson::getJson();
    }

    public function saveMuistot(Request $request) 
    {
        $errorArray = array();
        foreach($request->muistot as $muisto)
        {
            try
            {
                DB::beginTransaction();

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

                DB::commit();
                
            }
            catch(Exception $e)
            {
                throw $e;
                $newError = array($muisto['muisto_id'], 'Lis�ys ep�onnistui');
                $errorArray = $errorArray + $newError;
                DB::rollback();
            }
        }

        MipJson::addMessage(json_encode($errorArray));

        return MipJson::getJson();
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
    

}