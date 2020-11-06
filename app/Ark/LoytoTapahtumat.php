<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoytoTapahtumat extends Model
{
    use SoftDeletes;

    protected $table = "ark_loyto_tapahtumat";

    protected $fillable = array('ark_loyto_id', 'ark_loyto_tapahtuma_id', 'kuvaus', 'tapahtumapaivamaara', 'luoja', 'luotu');

    /*
     * Aikaleimat päivitetään manuaalisesti
     */
    public $timestamps = false;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    /**
     * Haku löydön id:n mukaan.
     */
    public static function haeLoydonTapahtumat($loyto_id) {
        return self::select('ark_loyto_tapahtumat.*')->where('ark_loyto_id', '=', $loyto_id);
    }

    /**
     * Haku tapahtuman id:n mukaan.
     */
    public static function getSingle($id) {
        return self::select('ark_loyto_tapahtumat.*')->where('id', '=', $id);
    }

    /**
     * Haku löydön id:n ja tapahtuman id:n mukaan.
     */
    public static function haeTapahtuma($loyto_id, $tapahtuma_id) {
        return self::select('ark_loyto_tapahtumat.*')->where('ark_loyto_id', '=', $loyto_id)->where('ark_loyto_tapahtuma_id', '=', $tapahtuma_id)->first();
    }

    /**
     * Haku tapahtuman id:n ja luotu aikaleiman mukaan. Tällä saadaan korin kautta luodut "massa" tapahtumat.
     * Palauttaa listan löytö id:tä.
     */
    public static function haeKoriTapahtumat($tapahtuma_id, $luotu){

        return self::select('ark_loyto_tapahtumat.ark_loyto_id')
                    ->where('ark_loyto_tapahtuma_id', '=', $tapahtuma_id)
                    ->where('luotu', '=', $luotu)->get();
    }

    /**
     * Relaatiot
     */
    public function loyto() {
        return $this->belongsTo('App\Ark\Loyto', 'ark_loyto_id');
    }
    public function tapahtumaTyyppi() {
        return $this->belongsTo('App\Ark\LoytoTapahtuma', 'ark_loyto_tapahtuma_id');
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

    public function sailytystila() {
        return $this->belongsTo('App\Ark\ArkSailytystila', 'vakituinen_sailytystila_id');
    }
}
