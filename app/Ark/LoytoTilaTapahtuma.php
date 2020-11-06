<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

/**
 * Löydön tilan ja tapahtuma-tyypin välitaulun model
 */
class LoytoTilaTapahtuma extends Model
{
    protected $table = "ark_loyto_tila_tapahtuma";
    protected $fillable = array('ark_loyto_tila_id', 'ark_loyto_tapahtuma_id');
    public $timestamps = false;

    /**
     * Haku löydön tilan id:n mukaan
     */
    public static function haeLoydonTilaIdMukaan($loyto_id) {
        return self::select('ark_loyto_tila_tapahtuma.*')
        ->where('ark_loyto_tila_id', '=', $loyto_id)->first();
    }

    /**
     * Haku tapahtuman id:n mukaan
     */
    public static function haeTapahtumaIdMukaan($tapahtuma_id) {
        return self::select('ark_loyto_tila_tapahtuma.*')
        ->where('ark_loyto_tapahtuma_id', '=', $tapahtuma_id)->first();
    }

    public function tapahtumaTyypit() {
        return $this->hasOne('App\Ark\LoytoTapahtuma', 'ark_loyto_tapahtuma_id');
    }
}
