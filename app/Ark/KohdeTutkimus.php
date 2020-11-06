<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Model kohteen ja tutkimuksen välitaululle
 */
class KohdeTutkimus extends Model
{
    protected $table = "ark_kohde_tutkimus";
    protected $fillable = array('ark_kohde_id', 'ark_tutkimus_id');
    public $timestamps = false;

    public static function paivita_kohde_tutkimus($ark_tutkimus_id, $kohde_id) {

        // poistetaan vanha
        DB::table('ark_kohde_tutkimus')->where('ark_tutkimus_id', $ark_tutkimus_id)->delete();

            // Jos kohde annettu luodaan uusi rivi
        if (!is_null($kohde_id)) {
                $kohdeTutkimus = new KohdeTutkimus();
                $kohdeTutkimus->ark_kohde_id = $kohde_id;
                $kohdeTutkimus->ark_tutkimus_id = $ark_tutkimus_id;
                $kohdeTutkimus->save();
            }
    }

    public function tutkimus() {
        return $this->belongsTo('App\Ark\Tutkimus', 'id', 'ark_tutkimus_id');
    }

}
