<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Konservoinnin toimenpiteiden ja menetelmien välitaulun model
 *
 */
class KonsToimenpideMenetelma extends Model
{
    protected $table = "ark_kons_toimenpide_menetelma";
    protected $fillable = array('toimenpide_id', 'menetelma_id');
    public $timestamps = false;

    /*
     * Toimenpiteen menetelmien poisto ja uudelleen luonti
     */
    public static function toimenpiteen_menetelmat($toimenpide_id, $menetelmat) {
        if (!is_null($menetelmat)) {
            // poistetaan toimenpiteen menetelmat
            DB::table('ark_kons_toimenpide_menetelma')->where('toimenpide_id', $toimenpide_id)->delete();

            foreach($menetelmat as $menetelma) {
                $ktm = new KonsToimenpideMenetelma();
                $ktm->toimenpide_id = $toimenpide_id;
                $ktm->menetelma_id = $menetelma['id'];

                $ktm->luoja = Auth::user()->id;
                $ktm->save();
            }
        }
    }

    /*
     * Menetelmän toimenpiteiden poisto ja uudelleen luonti
     */
    public static function menetelman_toimenpiteet($menetelma_id, $toimenpiteet) {

        if (!is_null($toimenpiteet)) {
            // poistetaan menetelmän toimenpiteet
            DB::table('ark_kons_toimenpide_menetelma')->where('menetelma_id', $menetelma_id)->delete();

            foreach($toimenpiteet as $toimenpide) {
                $ktm = new KonsToimenpideMenetelma();
                $ktm->menetelma_id = $menetelma_id;
                $ktm->toimenpide_id = $toimenpide['id'];

                $ktm->luoja = Auth::user()->id;
                $ktm->save();
            }
        }
    }

    public function toimenpiteet() {
        return $this->hasMany('App\Ark\KonservointiToimenpide', 'id', 'toimenpide_id');
    }
    public function menetelmat() {
        return $this->hasMany('App\Ark\KonservointiMenetelma', 'id', 'menetelma_id');
    }

}
