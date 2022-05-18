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
        DB::table('kori_kayttajat')->where('kori_id', $id)->delete();

        foreach($kayttajat as $kayttaja) {
            $kk = new KoriKayttaja();
            $kk->kori_id = $id;
            $kk->kayttaja_id = $kayttaja;

            $kk->save();
        }

    }

    public static function getSingle($id) {
        return self::select('kori_kayttajat.*')->where('kori_id', '=', $id);
    }

    public static function getKoriKayttajat($id){
        return DB::table('kori_kayttajat AS kk')
        ->select('k.*')
        ->leftJoin('kayttaja AS k', 'k.id', '=', 'kk.kayttaja_id')
        ->where(function($query) use ($id)
        {
            $query->where('kk.kori_id', '=', $id);
        });
    }

}