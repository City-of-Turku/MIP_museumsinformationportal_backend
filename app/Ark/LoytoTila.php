<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

/**
 * Löydön tilat
 */
class LoytoTila extends Model
{
    protected $table = "ark_loyto_tila";

    protected $fillable = array('nimi_fi', 'nimi_se', 'nimi_en', 'aktiivinen');

    public $timestamps = false;
}
