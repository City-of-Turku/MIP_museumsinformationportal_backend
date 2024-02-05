<?php

namespace App\Http\Controllers;


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

    public function getMuisto($id) {
        Log::debug("Muistoot " . $id . "api " . env("prikka_api_key"));
        MipJson::addMessage("Muisto tallenneltu");
        return MipJson::getJson();
    }

    public function saveMuisto(Request $request) {
        Log::debug("Muistoot " . json_encode($request->muistot[0]));
        Log::channel('prikka')->info("Prikkalokia ". $request->muistot[0]["prikka_id"]);
        MipJson::addMessage("Muisto tallenneltu, prikka_id ". $request->muistot[0]["prikka_id"]);
        return MipJson::getJson();
    }

}