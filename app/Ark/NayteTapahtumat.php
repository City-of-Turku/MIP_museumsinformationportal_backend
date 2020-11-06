<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Näytteen tapahtumat
 */
class NayteTapahtumat extends Model
{
    use SoftDeletes;

    protected $table = "ark_nayte_tapahtumat";

    protected $fillable = array('ark_nayte_id', 'ark_nayte_tapahtuma_id', 'kuvaus', 'tapahtumapaivamaara', 'luoja', 'luotu');

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
     * Haku näytteen id:n mukaan.
     */
    public static function haeNaytteenTapahtumat($nayte_id) {
        return self::select('ark_nayte_tapahtumat.*')->where('ark_nayte_id', '=', $nayte_id);
    }

    /**
     * Haku tapahtuman id:n mukaan.
     */
    public static function getSingle($id) {
        return self::select('ark_nayte_tapahtumat.*')->where('id', '=', $id);
    }

    /**
     * Haku näytteen id:n ja tapahtuman id:n mukaan.
     */
    public static function haeTapahtuma($nayte_id, $tapahtuma_id) {
        return self::select('ark_nayte_tapahtumat.*')->where('ark_nayte_id', '=', $nayte_id)->where('ark_nayte_tapahtuma_id', '=', $tapahtuma_id)->first();
    }

    /**
     * Haku tapahtuman id:n ja luotu aikaleiman mukaan. Tällä saadaan korin kautta luodut "massa" tapahtumat.
     * Palauttaa listan näyte id:tä.
     */
    public static function haeKoriTapahtumat($tapahtuma_id, $luotu){

        return self::select('ark_nayte_tapahtumat.ark_nayte_id')
        ->where('ark_nayte_tapahtuma_id', '=', $tapahtuma_id)
        ->where('luotu', '=', $luotu)->get();
    }

    /**
     * Relaatiot
     */
    public function nayte() {
        return $this->belongsTo('App\Ark\Nayte', 'ark_nayte_id');
    }
    public function tapahtumaTyyppi() {
        return $this->belongsTo('App\Ark\NayteTapahtuma', 'ark_nayte_tapahtuma_id');
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
