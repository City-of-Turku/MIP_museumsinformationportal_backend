<?php

namespace App\Http\Controllers\Ark;

use App\Ark\ArkKuntoraportti;
use App\Http\Controllers\Controller;
use App\Kayttaja;
use App\Ark\ArkKuva;
use App\Ark\ArkKuvaAsiasana;
use App\Ark\Kohde;
use App\Ark\Loyto;
use App\Ark\Nayte;
use App\Ark\TutkimusalueYksikko;
use App\Ark\Tutkimus;
use App\Ark\Tutkimusalue;
use App\Library\String\MipJson;
use App\Utils;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Exception;
use Intervention\Image\Facades\Image;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use App\Ark\ArkKuvaLoyto;
use App\Ark\ArkKuvaNayte;
use App\Ark\ArkKuvaYksikko;
use App\Ark\ArkKuvaKohde;
use App\Ark\ArkKuvaKuntoraportti;
use App\Ark\ArkKuvaTutkimus;
use App\Ark\ArkKuvaTutkimusalue;
use Illuminate\Database\QueryException;
use App\Ark\Rontgenkuva;
use App\Ark\ArkKuvaRontgenkuva;


class ArkKuvaController extends Controller {
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
        if($request->input('ark_tutkimus_id')) {
            $hasPermission = Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_kuva.katselu', $request->input('ark_tutkimus_id'));
        }
        if($request->input('ark_kohde_id')) {
            $hasPermission = Kayttaja::hasPermission('arkeologia.ark_kohde.katselu');
        }
        //Jos tehdään hakuja, tarkastellaan oikeudet jokaiselle kuvalle erikseen myöhemmin.
        if($request->input('searchPage')) {
            $hasPermission = true;
        }

        if(!$hasPermission) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all(), [
            "ark_kohde_id"				    => "numeric|exists:ark_kohde,id",
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

//          try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
            $jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "ark_kuva.id";
            $jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

            $entities = ArkKuva::getAll()->with(
                array('luoja',
                    'muokkaaja',
                    'asiasanat',
                    'konservointivaihe',
                    'kuvayksikot.yksikko',
                    'kuvaloydot.loyto',
                    'kuvanaytteet.nayte',
                    'kuvakohteet.kohde',
                    'tutkimukset'
                ))->orderBy($jarjestys_kentta, $jarjestys_suunta);

            if($request->input("ark_loyto_id") && $request->input("ark_loyto_id") !== 'null') {
                $entities->withLoytoId($request->input("ark_loyto_id"));
            }
            if($request->input("ark_nayte_id") && $request->input("ark_nayte_id") !== 'null') {
                $entities->withNayteId($request->input("ark_nayte_id"));
            }
            if($request->input("ark_yksikko_id") && $request->input("ark_yksikko_id") !== 'null') {
                $entities->withYksikkoId($request->input("ark_yksikko_id"));
            } else if($request->input("yksikkotunnus")) {
                $entities->withYksikkoTunnus($request->input("yksikkotunnus"));
            }
            if($request->input("ark_tutkimus_id") && ($request->input("ark_tutkimusalue_id") == 'null' || $request->input("ark_tutkimusalue_id") == null)) {
                $entities->withTutkimusId($request->input("ark_tutkimus_id"));
            } else if($request->input("ark_tutkimus_id") && $request->input("ark_tutkimusalue_id") != 'null') {
                $entities->withTutkimusalueId($request->input("ark_tutkimusalue_id"));
            }
            if($request->input("ark_kohde_id")) {
                $entities->withKohdeId($request->input("ark_kohde_id"));
            }
            if($request->input("muinaisjaannostunnus")) {
                $entities->withKohdeMjTunnus($request->input("muinaisjaannostunnus"));
            }
            if($request->input("kohdelajit")) {
                $entities->withKohdeLajit($request->input("kohdelajit"));
            }
            if($request->input("tutkimus_lyhenne")) {
                $entities->withTutkimusLyhenne($request->input("tutkimus_lyhenne"));
            }
            if($request->input("asiasanat")) {
                $entities->withAsiasanat($request->input("asiasanat"));
            }
            if($request->input("luettelointinumero")) {
                $entities->withLuettelointinumero($request->input("luettelointinumero"));
            }
            if($request->input("otsikko")) {
                $entities->withOtsikko($request->input("otsikko"));
            }
            if($request->input("ark_rontgenkuva_id") && $request->input("ark_rontgenkuva_id") !== 'null') {
                $entities->withRontgenKuva($request->input("ark_rontgenkuva_id"));
            }
            if($request->input("ark_kuntoraportti_id") && $request->input("ark_kuntoraportti_id") !== 'null') {
                $entities->withKuntoraportti($request->input("ark_kuntoraportti_id"));
            }

            $total_rows = Utils::getCount($entities);
            $entities->withLimit($rivi, $riveja);
            $entities = $entities->get();

            if(count($entities) <= 0) {
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('kuva.search_not_found'));
                return MipJson::getJson();
            }

            if($request->input("searchPage") != 'true') {
                // Luetteloitujen ja luetteloimattomien suodatus
                if($request->input("luetteloitu") == 'true'){
                    $luetteloitu = true;
                }else{
                    $luetteloitu = false;
                }

                $this->suodataLuetteloidut($entities, $luetteloitu, $request);
            }

            $returnEntities = [];

            foreach($entities as $entity) {
                //Tarkastetaan oikeudet - ark_tutkimus_id löytyy kaikilta tutkimuksiin liittyviltä kuvilta
                if($entity->ark_tutkimus_id) {
                    $entity->oikeudet = Kayttaja::getArkTutkimusSubPermissions($entity->ark_tutkimus_id);
                } else {
                    //Kohteeseen liitetty kuva - ei ark_tutkimus_idtä
                    //Kohteisiin liitetyt oikeudet tulevat suoraan kannasta.
                    $entity->oikeudet = Kayttaja::getPermissionsByEntityOnly('ark_kohde');
                }
                //Ei palauteta kuvia joihin ei ole oikeutta
                if($entity->oikeudet && $entity->oikeudet['katselu'] == true) {
                    array_push($returnEntities, $entity);
                }
            }

            MipJson::initGeoJsonFeatureCollection(count($returnEntities), $total_rows);

            //Hoidetaan palautettaville kuville urlit ja relaatiot mukaan
            foreach ($returnEntities as $entity) {
                $images = ArkKuva::getImageUrls($entity->polku.$entity->tiedostonimi);
                $entity->url = $images->original;
                $entity->url_tiny = $images->tiny;
                $entity->url_small = $images->small;
                $entity->url_medium = $images->medium;
                $entity->url_large = $images->large;

                //Fiksataan myös löydöt ja yksiköt
                $tmpLoydot = [];
                foreach($entity->kuvaloydot as $kl) {
                    array_push($tmpLoydot, $kl->loyto);
                }
                $tmpNaytteet = [];
                foreach($entity->kuvanaytteet as $kn) {
                    array_push($tmpNaytteet, $kn->nayte);
                }
                $tmpYksikot = [];
                foreach($entity->kuvayksikot as $ky) {
                    array_push($tmpYksikot, $ky->yksikko);
                }
                $entity->loydot = $tmpLoydot;
                $entity->naytteet = $tmpNaytteet;
                $entity->yksikot = $tmpYksikot;
                unset($entity->kuvaloydot);
                unset($entity->kuvanaytteet);
                unset($entity->kuvayksikot);

                // Deserialisoidaan JSON string kannasta
                $entity->migraatiodata = json_decode($entity->migraatiodata, true);

                MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $entity);
            }
            MipJson::addMessage(Lang::get('kuva.found_count',["count" => count($returnEntities)]));

//        } catch(Exception $e) {
//           MipJson::setGeoJsonFeature();
//           MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
//           MipJson::addMessage(Lang::get('kuva.search_failed'));
//        }
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
        if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_kuva.luonti', $request->input('ark_tutkimus_id'))) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $maxImageSize = config('app.max_image_size');

        //Tiedosto validation removed as frontend doesn't post 'tiedosto' anymore
        $validator = Validator::make($request->all(), [
            "tiedosto"			=> "required|max:" . $maxImageSize . "|mimes:jpg,jpeg,gif,tif,tiff,png",
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
            $entity = new ArkKuva($request->all());

            //PHP / Laravel castaa sisään tulevan null arvon "null" arvoksi
            //Korjataan tämä
            if($entity->ark_tutkimus_id == "null") {
                $entity->ark_tutkimus_id = null;
            }
            $entity->tiedostonimi = $file_name.".".$file_extension;
            $entity->alkup_tiedostonimi = $file_originalname;
            $entity->polku = $file_subpath;
            $entity->julkinen = false;
            $author_field = ArkKuva::CREATED_BY;
            $entity->$author_field = Auth::user()->id;

            //Luodaan luettelointinumero jos luetteloi on true
            if($request->luetteloi == "true") {
                $this->createLuettelointinumero($request, $entity);
            }

            $entity->save();

            /*
             * Create thumbnails
             */
            $this->createThumbnails($file_path, $file_name, $file_fullname);

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
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_kuva.katselu', $id)) {
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
            $entity = ArkKuva::getSingle($id)->with('luoja')->with('muokkaaja')->first();
            if(!$entity) {
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                MipJson::addMessage(Lang::get('kuva.search_not_found'));

                return MipJson::getJson();
            }

            $entity->first();
            $images = ArkKuva::getImageUrls($entity->polku.$entity->tiedostonimi);

            $entity->url = $images->original;
            $entity->url_tiny = $images->tiny;
            $entity->url_small = $images->small;
            $entity->url_medium = $images->medium;
            $entity->url_large = $images->large;

            MipJson::setGeoJsonFeature(null, $entity);
            MipJson::addMessage(Lang::get('kuva.search_success'));
        }
        catch(QueryException $e) {
            MipJson::addMessage(Lang::get('kuva.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
    }


    public function viewSmall($id) {
        if(!is_numeric($id)) {
            MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
            return;
        }

        try {

            $entity = ArkKuva::getSingle($id)->with('luoja')->first();
            if(!$entity) {
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                return;
            }

            $images = ArkKuva::getImageUrls($entity->polku.$entity->tiedostonimi);
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
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_kuva.muokkaus', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $validator = Validator::make($request->all(), [
            'kuvaus'			=> 'nullable|string',
            'kuvaaja'			=> 'nullable|string',
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
            $entity = ArkKuva::find($id);

            //Onko kuva luetteloitu aikaisemmin
            $luetteloitu = strlen($entity->luettelointinumero) > 0 ? true : false;

            if(!$entity){
                DB::rollback();
                MipJson::setGeoJsonFeature();
                MipJson::addMessage(Lang::get('kuva.search_not_found'));
                MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
                return MipJson::getJson();
            }
            $entity->fill($request->all());

            //Jos kuvalla on ollut luettelointinumero, tarkasta sen uniikkius ja tallenna
            if($luetteloitu) {
                if(!$request->luettelointinumero) {
                    DB::rollback();
                    //Virhe, luettelointinumero pitää olla!
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage("Luettelointinumero puuttuu");
                    MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
                    return MipJson::getJson();
                }
                $isUnique = ArkKuva::isLuettelointinumeroUnique($request->luettelointinumero, $entity->id);
                if($isUnique) {
                    $entity->luettelointinumero = $request->luettelointinumero;
                } else {
                    DB::rollback();
                    //Virhe, luettelointinumero pitää olla!
                    MipJson::setGeoJsonFeature();
                    MipJson::addMessage("Luettelointinumero ei ole uniikki");
                    MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
                    return MipJson::getJson();
                }
            }

            if($entity->tunnistekuva) {
                ArkKuva::updateTunnistekuva($request->loydot[0]['id']);
            }

            $author_field = ArkKuva::UPDATED_BY;
            $entity->$author_field = Auth::user()->id;

            $entity->save();

            //Tallennetaan asiasanat
            ArkKuvaAsiasana::paivita_asiasanat($entity->id, $request->asiasanat);

            //Linkitetään muihin
            //Tallennetaan linkattavat löydöt ja yksikot
            ArkKuva::linkita_loydot($entity->id, $request->loydot);
            ArkKuva::linkita_naytteet($entity->id, $request->naytteet);
            ArkKuva::linkita_yksikot($entity->id, $request->yksikot);

            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('kuva.save_success'));
            MipJson::setGeoJsonFeature(null, array("id" => $entity->id, "loydot" => $request->loydot, "naytteet" => $request->naytteet, "yksikot" => $request->yksikot));

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
        if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_kuva.poisto', $id)) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        $entity = ArkKuva::find($id);

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

             $author_field = ArkKuva::DELETED_BY;
             $when_field = ArkKuva::DELETED_AT;
             $entity->$author_field = Auth::user()->id;
             $entity->$when_field = \Carbon\Carbon::now();
             $entity->save();

             //Poistetaan linkitykset
             ArkKuva::linkita_loydot($entity->id, []);
             ArkKuva::linkita_naytteet($entity->id, []);
             ArkKuva::linkita_yksikot($entity->id, []);

             // Poistetaan mahdollinen kohde, tutkimus tai tutkimusalue kuva.
             // Poisto tehdään näin koska yhdistelmä PK tyylin modelissa ei tunnu laravel toimivan, kuten normi Id PK mallissa.
             switch ($request->input('entiteetti_tyyppi')) {
                 case 14: // Tutkimus
                     // Haetaan mahdollinen tutkimuksen kuva
                     $tutkimusKuva = ArkKuvaTutkimus::where('ark_kuva_id', '=', $id)->first();
                     if($tutkimusKuva){
                         DB::table('ark_kuva_tutkimus')->where('ark_kuva_id', '=', $id)->delete();
                     }

                     break;
                 case 15: //Kohde
                     // Haetaan mahdollinen kohteen kuva
                     $kohdeKuva = ArkKuvaKohde::where('ark_kuva_id', '=', $id)->first();
                     if($kohdeKuva){
                         DB::table('ark_kuva_kohde')->where('ark_kuva_id', '=', $id)->delete();
                     }

                     break;
                 case 16: // Tutkimusalue
                     // Haetaan mahdollinen tutkimusalueen kuva
                     $tutkimusalueKuva = ArkKuvaTutkimusalue::where('ark_kuva_id', '=', $id)->first();
                     if($tutkimusalueKuva){
                         DB::table('ark_kuva_tutkimusalue')->where('ark_kuva_id', '=', $id)->delete();
                     }

                     break;
             }

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
    public function isLuettelointinumeroUnique(Request $request) {
        try {
            $isUnique = ArkKuva::isLuettelointinumeroUnique($request->luettelointinumero, $request->kuvaId);
            MipJson::setGeoJsonFeature(null, $isUnique);
        }
        catch(Exception $e) {
            MipJson::addMessage("Luettelointinumeron tarkastus epäonnistui");
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return MipJson::getJson();
    }

    /*
     * Esimerkiksi jos päänumero on 2015:25 -> luettelointinumero on DT2015:25:1
     */
    private function createLuettelointinumero($request, $entity) {

        $ln = "DT";
        if($request->entiteetti_tyyppi == 17) {
            //Löytö - varmistetaan tutkimuksen löytyminen myös irtolöytö tyypeillä
            if($entity->ark_tutkimus_id){

                //Päänumero tutkimukselta
                $tutkimus = Tutkimus::getSingle($entity->ark_tutkimus_id)->first();
                $ln .= $tutkimus->digikuva_paanumero;
                $viimeisinLuettelointinumero = Tutkimus::getViimeisinLuettelointinumero($entity->ark_tutkimus_id);
            }else{
                $l = Loyto::getSingle($request->input('entiteetti_id'))->with('yksikko.tutkimusalue.tutkimus')->first();
                //Päänumero tutkimukselta
                $ln .= $l->yksikko->tutkimusalue->tutkimus->digikuva_paanumero;
                $viimeisinLuettelointinumero = Tutkimus::getViimeisinLuettelointinumero($l->yksikko->tutkimusalue->tutkimus->id);
            }

            if($viimeisinLuettelointinumero) {
                //Otetaan luettelointinumeron viimeinen osio viimeisimmän : merkin jälkeen:
                $startIndex = strrpos($viimeisinLuettelointinumero, ':');
                $viimeisinJuoksevaNumero = substr($viimeisinLuettelointinumero, $startIndex+1);
                $next = intval($viimeisinJuoksevaNumero)+1;
                $ln .= ":" . $next;

                $entity->luettelointinumero = $ln;

            }else{
                // ensimmäinen lisäys
                $ln .= ":" . 1;
                $entity->luettelointinumero = $ln;
            }
        } else if($request->entiteetti_tyyppi == 18){
            // Näyte
            if($entity->ark_tutkimus_id){
                //Päänumero tutkimukselta
                $tutkimus = Tutkimus::getSingle($entity->ark_tutkimus_id)->first();
                $ln .= $tutkimus->digikuva_paanumero;

                $nayte = Nayte::getSingle($request->input('entiteetti_id'));

                $viimeisinLuettelointinumero = Tutkimus::getViimeisinLuettelointinumero($entity->ark_tutkimus_id);
                //Otetaan luettelointinumeron viimeinen osio viimeisimmän : merkin jälkeen:
                $startIndex = strrpos($viimeisinLuettelointinumero, ':');
                $viimeisinJuoksevaNumero = substr($viimeisinLuettelointinumero, $startIndex+1);
                $next = intval($viimeisinJuoksevaNumero)+1;
                $ln .= ":" . $next;

                $entity->luettelointinumero = $ln;

            }
        } else if($request->entiteetti_tyyppi == 13) {
            //Yksikko - haetaan tutkimukselta päänumero
            $y = TutkimusalueYksikko::getSingle($request->input('entiteetti_id'))->with('tutkimusalue.tutkimus')->first();
            if($y) {
                //Päänumero tutkimukselta
                $ln .= $y->tutkimusalue->tutkimus->digikuva_paanumero;

                $viimeisinLuettelointinumero = Tutkimus::getViimeisinLuettelointinumero($y->tutkimusalue->tutkimus->id);
                //Otetaan luettelointinumeron viimeinen osio viimeisimmän : merkin jälkeen:
                $startIndex = strrpos($viimeisinLuettelointinumero, ':');
                $viimeisinJuoksevaNumero = substr($viimeisinLuettelointinumero, $startIndex+1);
                $next = intval($viimeisinJuoksevaNumero)+1;
                $ln .= ":" . $next;

                $entity->luettelointinumero = $ln;
            }
        } else if($request->entiteetti_tyyppi == 14) {
            //Tutkimus - haetaan tutkimukselta päänumero
            $t = Tutkimus::getSingle($request->input('entiteetti_id'))->first();

            if($t) {
                //Päänumero tutkimukselta
                $ln .= $t->digikuva_paanumero;

                $viimeisinLuettelointinumero = Tutkimus::getViimeisinLuettelointinumero($t->id);

                //Otetaan luettelointinumeron viimeinen osio viimeisimmän : merkin jälkeen:
                $startIndex = strrpos($viimeisinLuettelointinumero, ':');
                $viimeisinJuoksevaNumero = substr($viimeisinLuettelointinumero, $startIndex+1);
                $next = intval($viimeisinJuoksevaNumero)+1;
                $ln .= ":" . $next;

                $entity->luettelointinumero = $ln;
            }
        } else if($request->entiteetti_tyyppi == 16) {
            //Tutkimusalue - haetaan tutkimukselta päänumero
            $tk = Tutkimus::getSingle($entity->ark_tutkimus_id)->first();

            if($tk) {
                //Päänumero tutkimukselta
                $ln .= $tk->digikuva_paanumero;

                $viimeisinLuettelointinumero = Tutkimus::getViimeisinLuettelointinumero($tk->id);
                //Otetaan luettelointinumeron viimeinen osio viimeisimmän : merkin jälkeen:
                $startIndex = strrpos($viimeisinLuettelointinumero, ':');
                $viimeisinJuoksevaNumero = substr($viimeisinLuettelointinumero, $startIndex+1);
                $next = intval($viimeisinJuoksevaNumero)+1;
                $ln .= ":" . $next;

                $entity->luettelointinumero = $ln;
            }
        }
    }

    private function linkToItem($entity, $request) {
        //Linkataan oletuksena itemiin josta kuva lisättiin
        switch ($request->get('entiteetti_tyyppi')) {
            case 17:
                $maxJarjestys = DB::table('ark_kuva_loyto')->where('ark_loyto_id', '=', $request->get('entiteetti_id'))->max('jarjestys')+1;
                $loyto = Loyto::find($request->get('entiteetti_id'));
                $kuvaLoyto = new ArkKuvaLoyto();
                $kuvaLoyto->luoja = Auth::user()->id;
                $kuvaLoyto->ark_kuva_id = $entity->id;
                $kuvaLoyto->ark_loyto_id = $loyto->id;
                $kuvaLoyto->jarjestys = $maxJarjestys;
                $kuvaLoyto->save();
                break;
            case 18:
                $maxJarjestys = DB::table('ark_kuva_nayte')->where('ark_nayte_id', '=', $request->get('entiteetti_id'))->max('jarjestys')+1;
                $nayte = Nayte::find($request->get('entiteetti_id'));
                $kuvaNayte = new ArkKuvaNayte();
                $kuvaNayte->luoja = Auth::user()->id;
                $kuvaNayte->ark_kuva_id = $entity->id;
                $kuvaNayte->ark_nayte_id = $nayte->id;
                $kuvaNayte->jarjestys = $maxJarjestys;
                $kuvaNayte->save();
                break;
            case 13:
                $maxJarjestys = DB::table('ark_kuva_yksikko')->where('ark_yksikko_id', '=', $request->get('entiteetti_id'))->max('jarjestys')+1;
                $yksikko = TutkimusalueYksikko::find($request->get('entiteetti_id'));
                $kuvaYksikko = new ArkKuvaYksikko();
                $kuvaYksikko->luoja = Auth::user()->id;
                $kuvaYksikko->ark_kuva_id = $entity->id;
                $kuvaYksikko->ark_yksikko_id = $yksikko->id;
                $kuvaYksikko->jarjestys = $maxJarjestys;
                $kuvaYksikko->save();
                break;
            case 14: // Tutkimus
                $maxJarjestys = DB::table('ark_kuva_tutkimus')->where('ark_tutkimus_id', '=', $request->get('entiteetti_id'))->max('jarjestys')+1;
                $tutkimus = Tutkimus::find($request->get('entiteetti_id'));
                $kuvaTutkimus = new ArkKuvaTutkimus();
                $kuvaTutkimus->luoja = Auth::user()->id;
                $kuvaTutkimus->ark_kuva_id = $entity->id;
                $kuvaTutkimus->ark_tutkimus_id = $tutkimus->id;
                $kuvaTutkimus->jarjestys = $maxJarjestys;
                $kuvaTutkimus->save();
                break;
            case 15: //Kohde
                $maxJarjestys = DB::table('ark_kuva_kohde')->where('ark_kohde_id', '=', $request->get('entiteetti_id'))->max('jarjestys')+1;
                $kohde = Kohde::find($request->get('entiteetti_id'));
                $kuvaKohde = new ArkKuvaKohde();
                $kuvaKohde->luoja = Auth::user()->id;
                $kuvaKohde->ark_kuva_id = $entity->id;
                $kuvaKohde->ark_kohde_id = $kohde->id;
                $kuvaKohde->jarjestys = $maxJarjestys;
                $kuvaKohde->save();
                break;
            case 16: // Tutkimusalue
                $maxJarjestys = DB::table('ark_kuva_tutkimusalue')->where('ark_tutkimusalue_id', '=', $request->get('entiteetti_id'))->max('jarjestys')+1;
                $tutkimusalue = Tutkimusalue::find($request->get('entiteetti_id'));
                $kuvaTutkimusalue = new ArkKuvaTutkimusalue();
                $kuvaTutkimusalue->luoja = Auth::user()->id;
                $kuvaTutkimusalue->ark_kuva_id = $entity->id;
                $kuvaTutkimusalue->ark_tutkimusalue_id = $tutkimusalue->id;
                $kuvaTutkimusalue->jarjestys = $maxJarjestys;
                $kuvaTutkimusalue->save();
                break;
            case 19: // Röntgenkuva
                $maxJarjestys = DB::table('ark_kuva_rontgenkuva')->where('ark_rontgenkuva_id', '=', $request->get('entiteetti_id'))->max('jarjestys')+1;
                $rontgenkuva = Rontgenkuva::find($request->get('entiteetti_id'));
                $kuvaRontgen = new ArkKuvaRontgenkuva();
                $kuvaRontgen->luoja = Auth::user()->id;
                $kuvaRontgen->ark_kuva_id = $entity->id;
                $kuvaRontgen->ark_rontgenkuva_id = $rontgenkuva->id;
                $kuvaRontgen->jarjestys = $maxJarjestys;
                $kuvaRontgen->save();
                break;
            case 22: // Kuntoraportti
                $maxJarjestys = DB::table('ark_kuva_kuntoraportti')->where('ark_kuntoraportti_id', '=', $request->get('entiteetti_id'))->max('jarjestys')+1;
                $kuntoraportti = ArkKuntoraportti::find($request->get('entiteetti_id'));
                $kuvaKuntoraportti = new ArkKuvaKuntoraportti();
                $kuvaKuntoraportti->luoja = Auth::user()->id;
                $kuvaKuntoraportti->ark_kuva_id = $entity->id;
                $kuvaKuntoraportti->ark_kuntoraportti_id = $kuntoraportti->id;
                $kuvaKuntoraportti->jarjestys = $maxJarjestys;
                $kuvaKuntoraportti->save();
                break;
        }
    }

    private function createThumbnails($file_path, $file_name, $file_fullname) {
        $thumb_extension = 'jpg';
        //Large
        $img = Image::make($file_fullname)->encode('jpg');
        $img_large = ArkKuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_large'))[0]));
        $img_large->save($file_path.$file_name."_LARGE.".$thumb_extension);

        //Medium
        $img = Image::make($file_fullname)->encode('jpg');
        $img_medium = ArkKuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_medium'))[0]));
        $img_medium->save($file_path.$file_name."_MEDIUM.".$thumb_extension);

        //Small
        $img = Image::make($file_fullname)->encode('jpg');
        $img_small = ArkKuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_small'))[0]));
        $img_small->save($file_path.$file_name."_SMALL.".$thumb_extension);

        //Tiny
        $img = Image::make($file_fullname)->encode('jpg');
        $img_tiny = ArkKuva::createThumbnail($img, intval(explode(",",config('app.image_thumb_tiny'))[0]));
        $img_tiny->save($file_path.$file_name."_TINY.".$thumb_extension);
    }

    /*
     * Suodatetaan luetteloitu vs luetteloimattomat kuvat
     */
    private function suodataLuetteloidut($entities, $luetteloitu, $request)
    {
        for ($i = sizeOf($entities) - 1; $i >= 0; $i --) {

            if ($luetteloitu) {
                // Poistetaan listasta kuvat jotka ovat luetteloimattomia
                if (strlen($entities[$i]->luettelointinumero) === 0) {
                    unset($entities[$i]);
                }
            } else {
                // Poistetaan listasta kuvat jotka ovat luetteloituja
                if (strlen($entities[$i]->luettelointinumero) > 0) {
                    unset($entities[$i]);
                }
            }
        }

        // Löytöjen kuvista tutkitaan konservointivaihe erikseen
        for ($j = sizeOf($entities) - 1; $j >= 0; $j --) {

            if ($luetteloitu && $request->input("loyto") == 'true') {

                // Poistetaan listasta kuvat jotka ovat luetteloituja
                if (strlen($entities[$j]->luettelointinumero) > 0) {
                    unset($entities[$j]);
                } else {

                    // Poistetaan listasta kuvat joita ei näytetä löydön sivulla
                    if (strlen($entities[$j]->konservointivaihe) > 0) {
                        // Paitsi tunnistekuva
                        if (! $entities[$j]->tunnistekuva) {
                            unset($entities[$j]);
                        }
                    }
                }
            }
        }
    }

}
