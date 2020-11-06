<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Konservointitoimenpiteiden materiaali välitaulu
 *
 */
class KonsToimenpideMateriaalit extends Model
{
    protected $table = "ark_kons_toimenpide_materiaalit";
    protected $fillable = array('ark_kons_toimenpiteet_id', 'ark_kons_materiaali_id');
    public $timestamps = false;

    /*
     * Konservointitoimenpiteiden materiaalien poisto ja uudelleen luonti
     */
    public static function paivita_materiaalit($id, $materiaalit) {
        if (!is_null($materiaalit)) {
            // poistetaan
            DB::table('ark_kons_toimenpide_materiaalit')->where('ark_kons_toimenpiteet_id', $id)->delete();

            foreach($materiaalit as $materiaali) {
                $uusiMat = new KonsToimenpideMateriaalit();
                $uusiMat->ark_kons_toimenpiteet_id = $id;
                $uusiMat->ark_kons_materiaali_id = $materiaali['id'];

                $uusiMat->luoja = Auth::user()->id;
                $uusiMat->save();
            }
        }
    }


    public function materiaalit() {
        return $this->hasMany('App\Ark\KonservointiMateriaali', 'id', 'ark_kons_materiaali_id');
    }
}
