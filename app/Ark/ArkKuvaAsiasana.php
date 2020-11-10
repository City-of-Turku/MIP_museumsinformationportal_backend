<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArkKuvaAsiasana extends Model
{
    protected $table = "ark_kuva_asiasana";
    protected $fillable = array('ark_kuva_id', 'asiasana', 'kieli');
    public $timestamps = false;
    
    public static function byLoytoId($loyto_id) {
        return static::select('ark_loyto_asiasanat.*')
        ->where('ark_loyto_id', '=', "$loyto_id");
    }
    
    /**
     * Poistetaan löydön asiasanat ja lisätään muokatut
     */
    public static function paivita_asiasanat($kuva_id, $asiasanat, $kieli = 'fi') {
        if (!is_null($asiasanat)) {
            // poista vanhat
            DB::table('ark_kuva_asiasana')->where('ark_kuva_id', $kuva_id)->delete();
            
            foreach($asiasanat as $sana) {
                $kuvaAsiasana = new ArkKuvaAsiasana();
                $kuvaAsiasana->ark_kuva_id = $kuva_id;
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
