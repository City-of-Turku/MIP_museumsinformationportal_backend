<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArkKarttaAsiasana extends Model
{
    protected $table = "ark_kartta_asiasana";
    protected $fillable = array('ark_kartta_id', 'asiasana', 'kieli');
    public $timestamps = false;

    public static function byKarttaId($kartta_id) {
        return static::select('ark_kartta_asiasanat.*')
        ->where('ark_kartta_id', '=', "$kartta_id");
    }

    /**
     * Poistetaan  asiasanat ja lisätään muokatut
     */
    public static function paivita_asiasanat($kartta_id, $asiasanat, $kieli = 'fi') {
        if (!is_null($asiasanat)) {
            // poista vanhat
            DB::table('ark_kartta_asiasana')->where('ark_kartta_id', $kartta_id)->delete();

            foreach($asiasanat as $sana) {
                $kuvaAsiasana = new ArkKarttaAsiasana();
                $kuvaAsiasana->ark_kartta_id = $kartta_id;
                $kuvaAsiasana->asiasana = $sana;
                $kuvaAsiasana->kieli = $kieli;

                $kuvaAsiasana->luoja = Auth::user()->id;
                $kuvaAsiasana->save();
            }
        }
    }

    /**
     * Relaatiot
     */
    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }
}
