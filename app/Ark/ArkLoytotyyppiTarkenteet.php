<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Löydön tyyppien tarkenteiden välitaulu.
 */
class ArkLoytotyyppiTarkenteet extends Model
{
    protected $table = "ark_loyto_tyyppi_tarkenteet";
    protected $fillable = array('ark_loyto_id', 'ark_loyto_tyyppi_tarkenne_id');
    public $timestamps = false;

    public static function byLoytoId($loyto_id) {
        return static::select('ark_loyto_tyyppi_tarkenteet.*')
        ->where('ark_loyto_id', '=', "$loyto_id");
    }

    /**
     * Välitaulusta poistetaan löydön tyypin tarkenteet ja lisätään muokatut
     */
    public static function paivita_loydon_tarkenteet($loyto_id, $tarkenteet) {
        if (!is_null($tarkenteet)) {
            // poista vanhat
            DB::table('ark_loyto_tyyppi_tarkenteet')->where('ark_loyto_id', $loyto_id)->delete();

            foreach($tarkenteet as $tyyppitarkenne) {

                $tarkenne = new ArkLoytotyyppiTarkenteet();
                $tarkenne->ark_loyto_id = $loyto_id;
                $tarkenne->ark_loyto_tyyppi_tarkenne_id = $tyyppitarkenne['id'];

                $tarkenne->luoja = Auth::user()->id;
                $tarkenne->save();
            }
        }
    }
}
