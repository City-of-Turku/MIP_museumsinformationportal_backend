<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

/**
 * Löydön tapahtumat (tyypit)
 */
class LoytoTapahtuma extends Model
{
    protected $table = "ark_loyto_tapahtuma";

    protected $fillable = array('nimi_fi', 'nimi_se', 'nimi_en', 'aktiivinen');

    public $timestamps = false;

    // Vakiot bäkkärissä käytettäville tapahtuma-koodeille
    const LUETTELOITU = 1;
    const VAIHDETTU_LUETTELOINTINUMERO = 5;
    const LOYDETTY = 6;

}
