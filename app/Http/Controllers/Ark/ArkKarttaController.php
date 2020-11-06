<?php

namespace App\Http\Controllers\Ark;

use App\Kayttaja;
use App\Utils;
use App\Ark\ArkKartta;
use App\Ark\ArkKarttaAsiasana;
use App\Ark\ArkKarttaLoyto;
use App\Ark\ArkKarttaNayte;
use App\Ark\ArkKarttaTutkimusalue;
use App\Ark\ArkKarttaYksikko;
use App\Ark\Loyto;
use App\Ark\Nayte;
use App\Ark\Tutkimusalue;
use App\Ark\TutkimusalueYksikko;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;


class ArkKarttaController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {

        /*
         * Role check
         */
        $hasPermission = false;
        if($request->input("ark_tutkimus_id")) {
            $hasPermission = Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_kartta.katselu', $request->input('ark_tutkimus_id'));
        }
        //Hakusivu käytössä, tarkastellaan jokaisen kartan oikeudet erikseen ennen palautusta.
        if($request->input("searchPage")) {
            $hasPermission = true;
        }

        if(!$hasPermission) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all(), [
            "ark_loyto_id"				    => "numeric|exists:ark_loyto,id",
            "ark_nayte_id"				    => "numeric|exists:ark_nayte,id",
            "ark_yksikko_id"				=> "numeric|exists:ark_tutkimusalue_yksikko,id",
            "ark_tutkimus_id"               => "numeric|exists:ark_tutkimus,id"
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
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
            $jarjestys_kentta = "ark_kartta.karttanumero"; //(isset($request->jarjestys)) ? $request->jarjestys : "ark_kuva.id";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $entities = ArkKartta::getAll()->with(
                array('luoja',
                    'muokkaaja',
                    'asiasanat',
                    'mittakaava',
                    'koko',
                    'karttatyyppi',
                    'karttayksikot.yksikko',
                    'karttaloydot.loyto',
                    'karttanaytteet.nayte',
                    'tutkimukset.tutkimuslaji'
                )
                )->orderBy($jarjestys_kentta, $jarjestys_suunta);
            if($request->input("ark_loyto_id")) {
                $entities->withLoytoId($request->input("ark_loyto_id"));
            } else if($request->input("ark_yksikko_id")) {
                $entities->withYksikkoId($request->input("ark_yksikko_id"));
            } else if($request->input("ark_nayte_id")) {
                $entities->withNayteId($request->input("ark_nayte_id"));
            }

            if($request->input("ark_tutkimus_id")) {
                $entities->withTutkimusId($request->input("ark_tutkimus_id"));
            }
            if($request->input("tutkimuslajit")) {
                $entities->withTutkimusTyyppi($request->input("tutkimuslajit"));
            }
            if($request->input("tutkimus_nimi")) {
                $entities->withTutkimusNimi($request->input("tutkimus_nimi"));
            }
            if($request->input("tutkimus_lyhenne")) {
                $entities->withTutkimusLyhenne($request->input("tutkimus_lyhenne"));
            }
            if($request->input("tutkimus_paanumero")) {
                $entities->withTutkimusPaanumero($request->input("tutkimus_paanumero"));
            }
            if($request->input("tutkimus_klkoodi")) {
                $entities->withTutkimusKlKoodi($request->input("tutkimus_klkoodi"));
            }
            if($request->input("karttatyyppi")) {
                $entities->withKarttatyyppi($request->input("karttatyyppi"));
            }
            if($request->input("karttanumero")) {
                $entities->withKarttanumero($request->input("karttanumero"));
            }
            if($request->input("yksikkotunnus")) {
                $entities->withYksikkotunnus($request->input("yksikkotunnus"));
            }
            if($request->input("kuvaus")) {
                $entities->withKuvaus($request->input("kuvaus"));
            }
            if($request->input("asiasanat")) {
                $entities->withAsiasanat($request->input("asiasanat"));
            }
            if($request->input("loyto_luettelointinumero")) {
                $entities->withLoytoLuettelointinumero($request->input("loyto_luettelointinumero"));
            }
            if($request->input("nayte_luettelointinumero")) {
                $entities->withNayteLuettelointinumero($request->input("nayte_luettelointinumero"));
            }
            if($request->input("piirtaja")) {
                $entities->withPiirtaja($request->input("piirtaja"));
            }
            if($request->input("mittakaava")) {
                $entities->withMittakaava($request->input("mittakaava"));
            }
            if($request->input("alkup_karttanumero")) {
                $entities->withAlkupKarttanumero($request->input("alkup_karttanumero"));
            }
            if($request->input("alkup_karttanumero")) {
                $entities->withAlkupKarttanumero($request->input("alkup_karttanumero"));
            }

            $entities->withLimit($rivi, $riveja);
            $entities = $entities->get();

            $returnEntities = [];

            foreach($entities as $entity) {
                //Tarkastetaan oikeudet - ark_tutkimus_id löytyy kaikilta tutkimuksiin liittyviltä kuvilta
                if($entity->ark_tutkimus_id) {
                    $entity->oikeudet = Kayttaja::getArkTutkimusSubPermissions($entity->ark_tutkimus_id);
                }
                //Ei palauteta kuvia joihin ei ole oikeutta
                if($entity->oikeudet && $entity->oikeudet['katselu'] == true) {
                    array_push($returnEntities, $entity);
                }
            }

            $total_rows = sizeof($returnEntities);

            MipJson::initGeoJsonFeatureCollection(count($returnEntities), $total_rows);

            foreach ($returnEntities as $entity) {
                $entity->url = config('app.attachment_server')."/".config('app.attachment_server_baseurl').$entity->polku.$entity->tiedostonimi;

                //Fiksataan myös löydöt ja yksiköt
                $tmpLoydot = [];
                foreach($entity->karttaloydot as $kl) {
                    array_push($tmpLoydot, $kl->loyto);
                }
                $tmpYksikot = [];
                foreach($entity->karttayksikot as $ky) {
                    array_push($tmpYksikot, $ky->yksikko);
                }
                $tmpNaytteet = [];
                foreach($entity->karttanaytteet as $kn) {
                    array_push($tmpNaytteet, $kn->nayte);
                }
                $entity->loydot = $tmpLoydot;
                $entity->yksikot = $tmpYksikot;
                $entity->naytteet = $tmpNaytteet;
                unset($entity->karttaloydot);
                unset($entity->karttayksikot);
                unset($entity->karttanaytteet);
                
                // Deserialisoidaan JSON string kannasta
                $entity->migraatiodata = json_decode($entity->migraatiodata, true);

                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
            }
            MipJson::addMessage(Lang::get('kuva.found_count',["count" => count($returnEntities)]));
        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('kuva.search_failed'));
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
        if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_kartta.luonti', $request->input('ark_tutkimus_id'))) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $maxFileSize = config('app.max_file_size');

        //Tiedosto validation removed as frontend doesn't post 'tiedosto' anymore
        $validator = Validator::make($request->all(), [
            "tiedosto"			=> "required|max:" . $maxFileSize . "|mimes:pdf, 3pdf",
            "entiteetti_tyyppi" => "required|numeric", //|exists:entiteetti_tyyppi,id
            "entiteetti_id"		=> "required|numeric"
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

        // wrap the operations into a transaction
        DB::beginTransaction();
        Utils::setDBUser();
        try {
            $file 				= $request->file('tiedosto');
            $file_extension 	= $file->getClientOriginalExtension();
            $file_originalname 	= $file->getClientOriginalName();
            $file_name			= Str::random(32); //.".".$file_extension;//$request->luettelointinumero;
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
            $entity = new ArkKartta($request->all());
            $entity->tiedostonimi = $file_name.".".$file_extension;
            $entity->alkup_tiedostonimi = $file_originalname;
            $entity->polku = $file_subpath;
            $entity->julkinen = false;
            $author_field = ArkKartta::CREATED_BY;
            $entity->$author_field = Auth::user()->id;

            //$this->createKarttanumero($request, $entity);

            $entity->save();

            //Linkataan itemiin josta kuva lisättiin
            $this->linkToItem($entity, $request);

            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kuva.save_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $entity->id));

            // And commit the transaction as all went well
            DB::commit();
         } catch(Exception $e) {
            DB::rollback();

            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('kuva.save_failed'));
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
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_kartta.katselu', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        if(!is_numeric($id)) {
            MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            return MipJson::getJson();
        }
        try {
            $entity = ArkKartta::getSingle($id)->with('luoja')->with('muokkaaja')->first();
            if(!$entity) {
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                MipJson::addMessage(Lang::get('kuva.search_not_found'));

                return MipJson::getJson();
            }

            MipJson::setGeoJsonFeature(null, $entity);
            MipJson::addMessage(Lang::get('kuva.search_success'));
        }
        catch(QueryException $e) {
            MipJson::addMessage(Lang::get('kuva.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
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
         * The user has permission to delete images he/she has uploaded even if the user has role inventojia, tutkija or ulkopuolinen tutkija
         */
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_kartta.muokkaus', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all(), [
            'kuvaus'			=> 'nullable|string',
            'piirtaja'			=> 'nullable|string',
            "julkinen"			=> "nullable|boolean"
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

        // wrap the operations into a transaction
        DB::beginTransaction();
        Utils::setDBUser();

        try {
            $entity = ArkKartta::find($id);

            if(!$entity){
                DB::rollback();
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('kuva.search_not_found'));
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                return MipJson::getJson();
            }
            $entity->fill($request->all());
            $entity->mittakaava = $request->mittakaava['id'];
            $entity->koko = $request->koko['id'];
            $entity->tyyppi = $request->karttatyyppi['id'];

            if(!$request->karttanumero) {
                DB::rollback();
                //Virhe, luettelointinumero pitää olla!
                MipJson::setGeoJsonFeature();
                MipJson::addMessage("Karttanumero puuttuu");
                MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
                return MipJson::getJson();
            }
            $isUnique = ArkKartta::isKarttanumeroUnique($request->karttanumero, $entity->id, $entity->ark_tutkimus_id, $entity->tyyppi);
            if($isUnique) {
                $entity->karttanumero = $request->karttanumero;
            } else {
                DB::rollback();
                //Virhe, luettelointinumero pitää olla!
                MipJson::setGeoJsonFeature();
                MipJson::addMessage("Karttanumero ei ole uniikki");
                MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
                return MipJson::getJson();
            }


            $author_field = ArkKartta::UPDATED_BY;
            $entity->$author_field = Auth::user()->id;

            $entity->save();

            //Tallennetaan asiasanat
            ArkKarttaAsiasana::paivita_asiasanat($entity->id, $request->asiasanat);

            //Linkitetään muihin
            //Tallennetaan linkattavat löydöt ja yksikot
            ArkKartta::linkita_loydot($entity->id, $request->loydot);
            ArkKartta::linkita_yksikot($entity->id, $request->yksikot);
            ArkKartta::linkita_naytteet($entity->id, $request->naytteet);

            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kuva.save_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $entity->id, "loydot" => $request->loydot, "yksikot" => $request->yksikot, "naytteet" => $request->naytteet));

            DB::commit();

        } catch(Exception $e) {
            DB::rollback();
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('kuva.save_failed'));
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

        /*
         * Role check
         */
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_kartta.poisto', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $entity = ArkKartta::find($id);

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

             $author_field = ArkKartta::DELETED_BY;
             $when_field = ArkKartta::DELETED_AT;
             $entity->$author_field = Auth::user()->id;
             $entity->$when_field = \Carbon\Carbon::now();
             $entity->save();

             //Poistetaan linkitykset
             ArkKartta::linkita_loydot($entity->id, []);
             ArkKartta::linkita_yksikot($entity->id, []);
             ArkKartta::linkita_naytteet($entity->id, []);

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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function isKarttanumeroUnique(Request $request) {
        try {
            $isUnique = ArkKartta::isKarttanumeroUnique($request->karttanumero, $request->karttaId, $request->ark_tutkimus_id, $request->tyyppi);
            MipJson::setGeoJsonFeature(null, $isUnique);
        }
        catch(Exception $e) {
            MipJson::addMessage("Karttanumeron tarkastus epäonnistui");
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
    }

    public function seuraavaKarttanumero(Request $request) {
        try {
            $kn = ArkKartta::where('ark_tutkimus_id', '=', $request->ark_tutkimus_id)
            ->whereNull('poistettu')->where('tyyppi', '=', $request->karttatyyppi)->max('karttanumero');

            MipJson::setGeoJsonFeature(null, ['karttanumero' => $kn+1]);
        }
        catch(Exception $e) {
            MipJson::addMessage("Karttanumeron haku");
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
    }

    private function createKarttanumero($request, $entity) {
        $maxKarttaNumero = ArkKartta::where('ark_tutkimus_id', '=', $entity->ark_tutkimus_id)
        ->whereNull('poistettu')->where('tyyppi', '=', $entity->tyyppi)->max('karttanumero');
        $kn = $maxKarttaNumero+1;
        $entity->karttanumero = $kn;
    }

    private function linkToItem($entity, $request) {
        // Log::debug("ENTITEETTI:");
        // Log::debug($entity);
        //Linkataan oletuksena itemiin josta kuva lisättiin
        switch ($request->get('entiteetti_tyyppi')) {
            case 17:
                $loyto = Loyto::find($request->get('entiteetti_id'));
                $karttaLoyto = new ArkKarttaLoyto();
                $karttaLoyto->luoja = Auth::user()->id;
                $karttaLoyto->ark_kartta_id = $entity->id;
                $karttaLoyto->ark_loyto_id = $loyto->id;
                $karttaLoyto->save();
                break;
            case 18:
                $nayte = Nayte::find($request->get('entiteetti_id'));
                $karttaNayte = new ArkKarttaNayte();
                $karttaNayte->luoja = Auth::user()->id;
                $karttaNayte->ark_kartta_id = $entity->id;
                $karttaNayte->ark_nayte_id = $nayte->id;
                $karttaNayte->save();
                break;
            case 13:
                $yksikko = TutkimusalueYksikko::find($request->get('entiteetti_id'));
                $karttaYksikko = new ArkKarttaYksikko();
                $karttaYksikko->luoja = Auth::user()->id;
                $karttaYksikko->ark_kartta_id = $entity->id;
                $karttaYksikko->ark_yksikko_id = $yksikko->id;
                $karttaYksikko->save();
                break;
            case 16:
                $tutkimusalue = Tutkimusalue::find($request->get('entiteetti_id'));
                $karttaTa = new ArkKarttaTutkimusalue();
                $karttaTa->luoja = Auth::user()->id;
                $karttaTa->ark_kartta_id = $entity->id;
                $karttaTa->ark_tutkimusalue_id = $tutkimusalue->id;
                $karttaTa->save();
                break;
        }
    }
}
