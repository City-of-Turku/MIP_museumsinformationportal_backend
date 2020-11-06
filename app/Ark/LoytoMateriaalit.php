<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Löydön ja materiaalien välitaulu
 */
class LoytoMateriaalit extends Model
{
    protected $table = "ark_loyto_materiaalit";
    protected $fillable = array('ark_loyto_id', 'ark_loyto_materiaali_id');
    public $timestamps = false;

    public static function byLoytoId($loyto_id) {
        return static::select('ark_loyto_materiaalit.*')
        ->where('ark_loyto_id', '=', "$loyto_id");
    }

    /**
     * Välitaulusta poistetaan löydön materiaalit ja lisätään muokatut
     */
    public static function paivita_muut_materiaalit($loyto_id, $muut_materiaalit) {
        if (!is_null($muut_materiaalit)) {
            // poista vanhat
            DB::table('ark_loyto_materiaalit')->where('ark_loyto_id', $loyto_id)->delete();

            foreach($muut_materiaalit as $materiaali) {
                $loytoMateriaali = new LoytoMateriaalit();
                $loytoMateriaali->ark_loyto_id = $loyto_id;
                $loytoMateriaali->ark_loyto_materiaali_id = $materiaali['id'];

                $loytoMateriaali->luoja = Auth::user()->id;
                $loytoMateriaali->save();
            }
        }
    }
}
