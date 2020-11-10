<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Löydön ja merkintöjen välitaulu
 */
class LoytoMerkinnat extends Model
{
    protected $table = "ark_loyto_merkinnat";
    protected $fillable = array('ark_loyto_id', 'ark_loyto_merkinta_id');
    public $timestamps = false;

    public static function byLoytoId($loyto_id) {
        return static::select('ark_loyto_merkinnat.*')
        ->where('ark_loyto_id', '=', "$loyto_id");
    }

    /**
     * Välitaulusta poistetaan löydön merkinnät ja lisätään muokatut
     */
    public static function paivita_merkinnat($loyto_id, $merkinnat) {
        if (!is_null($merkinnat)) {
            // poista vanhat
            DB::table('ark_loyto_merkinnat')->where('ark_loyto_id', $loyto_id)->delete();

            foreach($merkinnat as $merkinta) {
                $loytoMerkinta = new LoytoMerkinnat();
                $loytoMerkinta->ark_loyto_id = $loyto_id;
                $loytoMerkinta->ark_loyto_merkinta_id = $merkinta['id'];

                $loytoMerkinta->luoja = Auth::user()->id;
                $loytoMerkinta->save();
            }
        }
    }
}
