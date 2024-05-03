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

class AiheController extends Controller {

    /**
    * Save aiheet
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function saveAiheet(Request $request) 
    {
        Log::channel('prikka')->info("saveAiheet " . $request . " recieved");
        $errorArray = array();
        foreach($request->aiheet as $aihe)
        {
            try
            {
                DB::beginTransaction();
              
                if(!in_array('aihe_id', array_keys($aihe)))
                {
                    array_push($errorArray, 'No id in aihe');
                }
                else 
                {
                    $validationResult = $this->validateAihe($aihe);
                    if(!empty($validationResult))
                    {
                        foreach($validationResult as $vresult)
                        {
                            array_push($errorArray, $vresult);
                        }
                    }
                    else 
                    {
                        $aiheEntity = Muistot_aihe::find($aihe['aihe_id']);
                        if(!$aiheEntity)
                        {
                            $aiheEntity = new Muistot_aihe();
                        }

                        foreach($aihe as $key=>$value) 
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

                        if(!is_null($aihe['kysymykset']) && !empty($aihe['kysymykset']))
                        {
                            $muistot=Muistot_muisto::where('muistot_aihe_id',$aiheEntity->prikka_id)->get();
                            if(!$muistot->isEmpty())
                            {
                                array_push($errorArray, $aihe['aihe_id'] . ': Cannot add questions, topic already has memories');
                            }
                            else
                            {
                                $res=Muistot_kysymys::where('muistot_aihe_id',$aiheEntity->prikka_id)->delete();

                                foreach($aihe['kysymykset'] as $kysymys)
                                {
                                    $entityKysymys = new Muistot_kysymys();
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
                            }
                        }
                    }

                }

                DB::commit();
            }
            catch(Exception $e)
            {
                array_push($errorArray, $aihe['aihe_id'] . ': failed to add');
                Log::channel('prikka')->info("saveMuistot failed");
                DB::rollback();
            }
        }

        $ret = (object) array('Errors' => $errorArray);
        Log::channel('prikka')->info("saveAiheetForce " . count((array) $ret) . " success");
        return $ret;
    }

    /**
    * Save aiheet forced
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function saveAiheetForce(Request $request) 
    {
        Log::channel('prikka')->info("saveAiheetForce " . $request . " recieved");
        $errorArray = array();
        foreach($request->aiheet as $aihe)
        {
            try
            {
                DB::beginTransaction();
              
                if(!in_array('aihe_id', array_keys($aihe)))
                {
                    array_push($errorArray, 'No id in aihe');
                }
                else 
                {
                    $validationResult = $this->validateAihe($aihe);
                    if(!empty($validationResult))
                    {
                        foreach($validationResult as $vresult)
                        {
                            array_push($errorArray, $vresult);
                        }
                    }
                    else 
                    {
                        $aiheEntity = Muistot_aihe::find($aihe['aihe_id']);
                        if(!$aiheEntity)
                        {
                            $aiheEntity = new Muistot_aihe();
                        }

                        foreach($aihe as $key=>$value) 
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

                        if(!is_null($aihe['kysymykset']) && !empty($aihe['kysymykset']))
                        {
                            $muistot=Muistot_muisto::where('muistot_aihe_id',$aiheEntity->prikka_id)->get();
                            if(!$muistot->isEmpty())
                            {
                                foreach($muistot as $muisto)
                                {
                                    $this->deleteAllImagesFromMuisto($muisto->prikka_id);
                                    $vastaukset=Muistot_vastaus::where('muistot_muisto_id',$muisto->prikka_id)->delete();
                                }
                                $muistot=Muistot_muisto::where('muistot_aihe_id',$aiheEntity->prikka_id)->delete();
                            }
                            else
                            {
                                $res=Muistot_kysymys::where('muistot_aihe_id',$aiheEntity->prikka_id)->delete();

                                foreach($aihe['kysymykset'] as $kysymys)
                                {
                                    $entityKysymys = new Muistot_kysymys();
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
                            }
                        }
                    }

                }

                DB::commit();
            }
            catch(Exception $e)
            {
                array_push($errorArray, $aihe['aihe_id'] . ': failed to add');
                Log::channel('prikka')->info("saveAiheetForce failed");
                DB::rollback();
            }
        }

        $ret = (object) array('Errors' => $errorArray);
        Log::channel('prikka')->info("saveAiheetForce " . count((array) $ret) . " success");
        return $ret;
    }

    /**
     * Validate aihe
     * @param array $aihe
     * @return array
     */
    private function validateAihe($aihe) {
        $errorArray = array();
        $requiredValues = array('aukeaa', 'sulkeutuu', 'aihe_fi', 'esittely_fi', 'aiheen_vari');

        if($aihe['aihe_id'] == null)
        {
            array_push($errorArray, 'id value null');
            return $errorArray;
        }

        foreach ($aihe as $key => $value) {
            if(in_array($key, $requiredValues) && ($value != false && ($value == null || $value == '')))
            {
                array_push($errorArray, $aihe['aihe_id'] . ' ' . $key . ' value null');
            }
        }

        foreach($requiredValues as $rvalue)
        {
            if(!in_array($rvalue, array_keys($aihe)))
            {
                array_push($errorArray, $aihe['aihe_id'] . ' ' . $rvalue . ' value null');
            }
        }

        foreach($aihe['kysymykset'] as $kysymys)
        {
            if(!in_array('kysymys_id', array_keys($kysymys)) || $kysymys['kysymys_id'] == null)
            {
                array_push($errorArray, $aihe['aihe_id'] . ' kysymys_id value null');
            }
        }

        return $errorArray;
    }

    /**
     * Get all topics from the database
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
            "id"				=> "numeric|exists:muistot_aihe,prikka_id",
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

            $aiheet = Muistot_aihe::getAll();

            /*
             * If ANY search terms are given limit results by them
             */
            if($request->id) {
                $aiheet->withPrikkaId($request->id);
            }
            if($request->aukeaa) {
                $aiheet->withAukeaa($request->aukeaa);
            }
            if($request->sulkeutuu) {
                $aiheet->withSulkeutuu($request->sulkeutuu);
            }
            if($request->aihe) {
                $aiheet->withAihe($request->aihe);
            }

            // order the results by given parameters
            //$aiheet->withOrderBy($request->aluerajaus, $jarjestys_kentta, $jarjestys_suunta);

            // calculate the total rows of the search results
            $total_rows = Utils::getCount($aiheet);//count($kiinteistot->get());

            // ID:t listaan ennen rivien rajoitusta
            $kori_id_lista = Utils::getPrikkaIdList($aiheet);

            // limit the results rows by given params
            $aiheet->withLimit($rivi, $riveja);

            // Execute the query
            $aiheet = $aiheet->get();

            MipJson::initGeoJsonFeatureCollection(count($aiheet), $total_rows);
            foreach ($aiheet as $aihe)  {
				MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $aihe);
			}

            /*
             * Koritoiminnallisuus. Palautetaan id:t listana.
             */
            MipJson::setIdList($kori_id_lista);

            MipJson::addMessage(Lang::get('muistot_aihe.search_success'));

        } catch(Exception $e) {
            throw $e;
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('muistot_aihe.search_failed'));
        }

        return MipJson::getJson();
    }

    /**
     * Get single topic from the database
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
                if(Auth::user()->rooli == 'katselija') {
                    $aihe = Muistot_aihe::getSingle($id)
                      ->with(array('Muistot_kysymys'))->first();
                } else {
                    $aihe = Muistot_aihe::getSingle($id)
                      ->with(array('Muistot_kysymys'))->first();
                }

                if($aihe) {
                    $properties = clone($aihe);
                    unset($properties['sijainti']);
                    MipJson::setGeoJsonFeature(json_decode($aihe->sijainti), $properties);

                    MipJson::addMessage(Lang::get('muistot_aihe.search_success'));
                }
                else {
                    MipJson::setGeoJsonFeature();
                    MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                    MipJson::addMessage(Lang::get('muistot_aihe.search_not_found'));
                }
            }
            catch(QueryException $e) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('muistot_aihe.search_failed'));
                MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return MipJson::getJson();
    }

    /**
     * Get memories by topic
     * @param $aihe_id int Topic id
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_memories_by_topic($aihe_id) {
        /*
         * Role check
         */
        if(!Kayttaja::hasPermission('muistot.muisto.katselu')) {
          MipJson::setGeoJsonFeature();
          MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
          MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
          return MipJson::getJson();
      }

      if(!is_numeric($aihe_id)) {
          MipJson::setGeoJsonFeature();
          MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
          MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
      }
      else {
          try {
              $aihe = Muistot_aihe::getSingle($aihe_id)->first();
              if($aihe) {

                  $query = $aihe->muistot();
                  $query->where('poistettu', false);
                  $query->where('ilmiannettu', false);
                
                  if(!Kayttaja::hasPermission('muistot.yksityinen_muisto.katselu')) {
                      $query->where('julkinen', true);
                      $query->with('muistot_henkilo_filtered');
                  } else {
                      $query->with('muistot_henkilo');
                  }

                  $muistot = $query->orderby('prikka_id')->get();
                  
                  $total_rows = ($muistot) ? count($muistot) : 0;

                  if($total_rows > 0) {
                      MipJson::initGeoJsonFeatureCollection(count($muistot), $total_rows);
                      
                      foreach ($muistot as $muisto) {
                          if($muisto->muistot_henkilo_filtered)
                          {
                              $muisto->muistot_henkilo = $muisto->muistot_henkilo_filtered;
                          }
        
                          /*
                           * clone $muisto so we can handle the "properties" separately
                           * -> remove "sijainti" from props
                           */
                          $properties = clone($muisto);
                          unset($properties['sijainti']);
                          $sijainti = (array) json_decode($muisto->sijainti);
                          $sijainti['coordinates'] = array_reverse($sijainti['coordinates']);
                          MipJson::addGeoJsonFeatureCollectionFeaturePoint($sijainti, $properties);
                      }
                  }
                  MipJson::addMessage(Lang::get('muistot.search_success'));
              }
              else {
                  MipJson::setGeoJsonFeature();
                  MipJson::addMessage(Lang::get('muistot.search_not_found'));
                  MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
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
}