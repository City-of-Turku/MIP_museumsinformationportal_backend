<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

/**
 * Näytteen tilan ja tapahtuma-tyypin välitaulun model
 */
class NayteTilaTapahtuma extends Model
{
    protected $table = "ark_nayte_tila_tapahtuma";
    protected $fillable = array('ark_nayte_tila_id', 'ark_nayte_tapahtuma_id');
    public $timestamps = false;

    /**
     * Haku näytteen tilan id:n mukaan
     */
    public static function haeNaytteenTilaIdMukaan($nayte_id) {
        return self::select('ark_nayte_tila_tapahtuma.*')
        ->where('ark_nayte_tila_id', '=', $nayte_id)->first();
    }

    /**
     * Haku tapahtuman id:n mukaan
     */
    public static function haeTapahtumaIdMukaan($tapahtuma_id) {
        return self::select('ark_nayte_tila_tapahtuma.*')
        ->where('ark_nayte_tapahtuma_id', '=', $tapahtuma_id)->first();
    }

    public function tapahtumaTyypit() {
        return $this->hasOne('App\Ark\NayteTapahtuma', 'ark_nayte_tapahtuma_id');
    }
}
