<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Yksikön ja maalajien välitaulu pääsekoitteen osalta
 */
class YksikkoPaasekoitteet extends Model
{
    protected $table = "yksikko_paasekoitteet";
    protected $fillable = array('ark_tutkimusalue_yksikko_id', 'yksikko_paasekoite_id');
    public $timestamps = false;

    public static function byYksikkoId($yksikko_id) {
        return static::select('yksikko_paasekoitteet.*')
        ->where('ark_tutkimusalue_yksikko_id', '=', "$yksikko_id");
    }

    /**
     * Välitaulusta poistetaan maalajit ja lisätään muokatut
     */
    public static function paivita_paasekoitteet($yksikko_id, $maalajit) {
        if (!is_null($maalajit)) {
            // poista vanhat
            DB::table('yksikko_paasekoitteet')->where('ark_tutkimusalue_yksikko_id', $yksikko_id)->delete();

            foreach($maalajit as $maalaji) {
                $paasekoite = new YksikkoPaasekoitteet();
                $paasekoite->ark_tutkimusalue_yksikko_id = $yksikko_id;
                $paasekoite->yksikko_paasekoite_id = $maalaji['id'];

                $paasekoite->luoja = Auth::user()->id;
                $paasekoite->save();
            }
        }
    }
}
