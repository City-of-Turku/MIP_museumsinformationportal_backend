<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

class KoriKayttajat extends Model
{
    protected $table = "kori_kayttajat";

    protected $fillable = array(
        'kori_id', 'kayttaja_id_lista', 'museon_kori'
    );

    protected $casts = ['kayttaja_id_lista' => 'array'];

    public $timestamps = false;

    /**
     * Asetetaan käyttäjille oikeus jaettuun koriin. Poistetaan korin oikeudet ja luodaan uudet tilalle.
     */
    public static function lisaaKorinKayttajat($id, $kayttajat, $museon_kori){
        DB::table('kori_kayttajat')->where('kori_id', $id)->delete();

        if ($kayttajat == null){
            $kayttajat = [];
        }

        $kk = new KoriKayttajat();
        $kk->kori_id = $id;
        $kk->kayttaja_id_lista = $kayttajat;
        $kk->museon_kori = $museon_kori;

        $kk->save();
    }

    public static function getKoriKayttajat($id){
        $query = DB::table('kori_kayttajat AS kk')
        ->select('kk.museon_kori', 'k.*')
        ->leftJoin('kayttaja AS k' , function($join) use($id){
            $join->whereIn(DB::raw('k.id::text'), function($subquery){
                $subquery->select(DB::raw('jsonb_array_elements_text(kk.kayttaja_id_lista)'))
                ->from('kori_kayttajat');
            });
        })
        ->where('kori_id', $id);

        return $query->get();
    }

}