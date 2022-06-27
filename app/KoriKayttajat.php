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

        $museo_query = DB::table('kori_kayttajat AS kk')
        ->select('kk.museon_kori')
        ->where('kori_id', $id)
        ->get();
        $museon_kori = false;

        if ($museo_query != null && count($museo_query) > 0){
            $museon_kori = $museo_query->first()->museon_kori;
        }

        $query = DB::table('kayttaja AS k')
        ->select('k.*')
        ->leftJoin('kori_kayttajat AS kk' , function($join) {
            $join->whereIn(DB::raw('k.id::text'), function($subquery){
                $subquery->select(DB::raw('jsonb_array_elements_text(kk.kayttaja_id_lista)'))
                ->from('kori_kayttajat');
            });
        })
        ->where('kori_id', $id);

        return array('kayttajat' => $query->get(), 'museon_kori' => $museon_kori);
    }

    public static function getKayttajat($kayttajalista){
        $query = DB::table('kayttaja AS k')
        ->select('k.*')
        ->whereIn('k.id', json_decode($kayttajalista));

        return $query->get();
    }
}