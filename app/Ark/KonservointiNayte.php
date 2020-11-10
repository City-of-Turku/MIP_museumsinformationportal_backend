<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KonservointiNayte extends Model
{
    protected $table = "ark_kons_nayte";
    protected $fillable = array('ark_kons_toimenpiteet_id', 'ark_nayte_id', 'ark_kons_kasittely_id', 'paattyy');
    public $timestamps = false;

    /*
     * Konservoinnin näytteiden poisto ja uudelleen luonti
     */
    public static function paivita_naytteet($id, $naytteet, $kasittely_id) {
        if (!is_null($naytteet)) {
            // poistetaan
            DB::table('ark_kons_nayte')->where('ark_kons_toimenpiteet_id', $id)->delete();

            foreach($naytteet as $nayte) {
                $uusiNayte = new KonservointiNayte();
                $uusiNayte->ark_kons_toimenpiteet_id = $id;
                $uusiNayte->ark_nayte_id = $nayte['ark_nayte_id'];
                $uusiNayte->paattyy = $nayte['paattyy'];

                if(!is_null($kasittely_id)){
                    $uusiNayte->ark_kons_kasittely_id = $kasittely_id;
                }

                $uusiNayte->luoja = Auth::user()->id;
                $uusiNayte->save();
            }
        }
    }

    /*
     * Konservoinnin näytteiden päätöspäivän asetus, jos päätöspäivää ei vielä ole.
     * Tätä käytetään kun käsittelylle asetetaan päätöspäivä.
     */
    public static function paivita_kasittelyn_paatospaiva($id, $kasittely_id, $nayte_id, $paatos_pvm) {

        $loyto = DB::table('ark_kons_nayte')
        ->where([
            ['ark_kons_toimenpiteet_id', '=', $id],
            ['ark_kons_kasittely_id', '=', $kasittely_id],
            ['ark_nayte_id', '=', $nayte_id],
            ['paattyy', '=', null],
        ])->update(['paattyy' => $paatos_pvm]);
    }

    /*
     * Konservoinnin näytteiden päätöspäivän asetus, jos päätöspäivää ei vielä ole.
     * Tätä käytetään kun toimenpiteelle asetetaan päätöspäivä.
     */
    public static function paivita_toimenpiteen_paatospaiva($id, $paatos_pvm) {

        $loyto = DB::table('ark_kons_nayte')
        ->where([
            ['ark_kons_toimenpiteet_id', '=', $id],
            ['paattyy', '=', null],
        ])->update(['paattyy' => $paatos_pvm]);
    }

    public function nayte() {
        return $this->hasOne('App\Ark\Nayte', 'id', 'ark_nayte_id');
    }

    public function kasittely() {
        return $this->hasOne('App\Ark\KonservointiKasittely', 'id', 'ark_kons_kasittely_id');
    }
}
