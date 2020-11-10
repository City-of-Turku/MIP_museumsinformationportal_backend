<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KonservointiKasittelytapahtumat extends Model
{
    use SoftDeletes;

    protected $table = "ark_kons_kasittelytapahtumat";

    protected $fillable = array('ark_kons_kasittely_id', 'paivamaara', 'kasittelytoimenpide', 'huomiot');

    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    /**
     * Haku konservoinnin käsittelyn id:n mukaan.
     */
    public static function haeKasittelytapahtumat($kasittely_id) {
        return self::select('ark_kons_kasittelytapahtumat.*')->where('ark_kons_kasittely_id', '=', $kasittely_id);
    }

    /**
     * Poistetaan valitun käsittelyn tapahtumat ja lisätään muokatut
     */
    public static function paivita_kasittelytapahtumat($kasittely_id, $tapahtumat) {
        if (!is_null($tapahtumat)) {
            // poista vanhat
            DB::table('ark_kons_kasittelytapahtumat')->where('ark_kons_kasittely_id', $kasittely_id)->delete();

            foreach($tapahtumat as $tapahtuma) {
                $kasittelyTapahtuma = new KonservointiKasittelytapahtumat();
                $kasittelyTapahtuma->ark_kons_kasittely_id = $kasittely_id;
                $kasittelyTapahtuma->paivamaara = $tapahtuma['paivamaara'];
                $kasittelyTapahtuma->kasittelytoimenpide = $tapahtuma['kasittelytoimenpide'];
                $kasittelyTapahtuma->huomiot = $tapahtuma['huomiot'];

                $kasittelyTapahtuma->luoja = Auth::user()->id;
                $kasittelyTapahtuma->save();
            }
        }
    }

    /**
     * Relaatiot
     */
    public function kasittely() {
        return $this->belongsTo('App\Ark\KonservointiKasittely', 'ark_kons_kasittely_id');
    }
    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }
    public function muokkaaja() {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }
    public function poistaja() {
        return $this->belongsTo('App\Kayttaja', 'poistaja');
    }
}
