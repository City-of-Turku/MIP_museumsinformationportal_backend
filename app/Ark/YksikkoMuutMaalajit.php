<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Yksikön ja maalajien välitaulu muiden maalajien osalta
 */
class YksikkoMuutMaalajit extends Model
{
    protected $table = "yksikko_muut_maalajit";
    protected $fillable = array('ark_tutkimusalue_yksikko_id', 'yksikko_muu_maalaji_id');
    public $timestamps = false;

    public static function byYksikkoId($yksikko_id) {
        return static::select('yksikko_muut_maalajit.*')
        ->where('ark_tutkimusalue_yksikko_id', '=', "$yksikko_id");
    }

    /**
     * Välitaulusta poistetaan maalajit ja lisätään muokatut
     */
    public static function paivita_muut_maalajit($yksikko_id, $maalajit) {
        if (!is_null($maalajit)) {
            // poista vanhat
            DB::table('yksikko_muut_maalajit')->where('ark_tutkimusalue_yksikko_id', $yksikko_id)->delete();

            foreach($maalajit as $maalaji) {
                $muuMaalaji = new YksikkoMuutMaalajit();
                $muuMaalaji->ark_tutkimusalue_yksikko_id = $yksikko_id;
                $muuMaalaji->yksikko_muu_maalaji_id = $maalaji['id'];

                $muuMaalaji->luoja = Auth::user()->id;
                $muuMaalaji->save();
            }
        }
    }
}
