<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class KoriKayttaja extends Model
{
    protected $table = "kori_kayttajat";

    protected $fillable = array(
        'kori_id', 'kayttaja_id'
    );

    public $timestamps = false;

    /**
     * Asetetaan käyttäjille oikeus jaettuun koriin. Poistetaan korin oikeudet ja luodaan uudet tilalle.
     */
    public static function lisaaKorinKayttajat($id, $kayttajat){
        Log::debug("Kayttajaat ". json_encode($kayttajat));
        DB::table('kori_kayttajat')->where('kori_id', $id)->delete();

        foreach($kayttajat as $kayttaja) {
            Log::debug("Käyttäjä " .$kayttaja);
            $kk = new KoriKayttaja();
            $kk->kori_id = $id;
            $kk->kayttaja_id = $kayttaja;

            $kk->save();
            Log::debug("KK " . json_encode($kk));
        }

    }

}