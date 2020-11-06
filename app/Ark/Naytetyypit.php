<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

/**
 * Näytekoodin ja näytetyyppien välitaulu
 */
class Naytetyypit extends Model
{
    protected $table = "ark_naytetyypit";

    protected $fillable = array('ark_naytekoodi_id', 'ark_naytetyyppi_id');
    public $timestamps = false;

    public function naytetyypit() {
        return $this->hasMany('App\Ark\NayteTyyppi', 'ark_naytetyyppi_id');
    }
}
