<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Löydön asiasanat
 */
class LoytoAsiasanat extends Model
{
    protected $table = "ark_loyto_asiasanat";
    protected $fillable = array('ark_loyto_id', 'asiasana', 'kieli');
    public $timestamps = false;

    public static function byLoytoId($loyto_id) {
        return static::select('ark_loyto_asiasanat.*')
        ->where('ark_loyto_id', '=', "$loyto_id");
    }

    /**
     * Poistetaan löydön asiasanat ja lisätään muokatut
     */
    public static function paivita_asiasanat($loyto_id, $asiasanat, $kieli) {
        if (!is_null($asiasanat)) {
            // poista vanhat
            DB::table('ark_loyto_asiasanat')->where('ark_loyto_id', $loyto_id)->delete();

            foreach($asiasanat as $sana) {
                $loytoAsiasana = new LoytoAsiasanat();
                $loytoAsiasana->ark_loyto_id = $loyto_id;
                $loytoAsiasana->asiasana = $sana;
                $loytoAsiasana->kieli = $kieli;

                $loytoAsiasana->luoja = Auth::user()->id;
                $loytoAsiasana->save();
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
