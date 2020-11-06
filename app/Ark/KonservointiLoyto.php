<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KonservointiLoyto extends Model
{
    protected $table = "ark_kons_loyto";
    protected $fillable = array('ark_kons_toimenpiteet_id', 'ark_loyto_id', 'ark_kons_kasittely_id', 'paattyy');
    public $timestamps = false;

    /*
     * Konservoinnin löytöjen poisto ja uudelleen luonti
     */
    public static function paivita_loydot($id, $loydot, $kasittely_id) {
        if (!is_null($loydot)) {
            // poistetaan
            DB::table('ark_kons_loyto')->where('ark_kons_toimenpiteet_id', $id)->delete();

            foreach($loydot as $loyto) {
                $uusiLoyto = new KonservointiLoyto();
                $uusiLoyto->ark_kons_toimenpiteet_id = $id;
                $uusiLoyto->ark_loyto_id = $loyto['ark_loyto_id'];
                $uusiLoyto->paattyy = $loyto['paattyy'];

                if(!is_null($kasittely_id)){
                    $uusiLoyto->ark_kons_kasittely_id = $kasittely_id;
                }

                $uusiLoyto->luoja = Auth::user()->id;
                $uusiLoyto->save();
            }
        }
    }

    /*
     * Konservoinnin löytöjen päätöspäivän asetus, jos päätöspäivää ei vielä ole.
     * Tätä käytetään kun käsittelylle asetetaan päätöspäivä.
     */
    public static function paivita_kasittelyn_paatospaiva($id, $kasittely_id, $loyto_id, $paatos_pvm) {

        $loyto = DB::table('ark_kons_loyto')
        ->where([
            ['ark_kons_toimenpiteet_id', '=', $id],
            ['ark_kons_kasittely_id', '=', $kasittely_id],
            ['ark_loyto_id', '=', $loyto_id],
            ['paattyy', '=', null],
        ])->update(['paattyy' => $paatos_pvm]);
    }

    /*
     * Konservoinnin löytöjen päätöspäivän asetus, jos päätöspäivää ei vielä ole.
     * Tätä käytetään kun toimenpiteelle asetetaan päätöspäivä.
     */
    public static function paivita_toimenpiteen_paatospaiva($id, $paatos_pvm) {

        $loyto = DB::table('ark_kons_loyto')
        ->where([
            ['ark_kons_toimenpiteet_id', '=', $id],
            ['paattyy', '=', null],
        ])->update(['paattyy' => $paatos_pvm]);
    }

    public function loyto() {
        return $this->hasOne('App\Ark\Loyto', 'id', 'ark_loyto_id');
    }

    public function kasittely() {
        return $this->hasOne('App\Ark\KonservointiKasittely', 'id', 'ark_kons_kasittely_id');
    }

}
