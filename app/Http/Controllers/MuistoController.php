<?php

namespace App\Http\Controllers;

use App\Utils;
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
                $newError = array($aiheArray['aihe_id'], 'Lisäys epäonnistui');
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
                        /*$geom = MipGis::getPointGeometryValue($value);
                        $muistoEntity->$key = $geom;*/
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
                            if($key == 'kuvatiedosto')
                            {
                                //TODO: handle file, save filepath to $entityKuva->tiedostopolku etc.
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
                $newError = array($muisto['muisto_id'], 'Lisäys epäonnistui');
                $errorArray = $errorArray + $newError;
                DB::rollback();
            }
        }

        MipJson::addMessage(json_encode($errorArray));

        return MipJson::getJson();
    }

}