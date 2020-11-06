<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkTiedostoKasittely extends Model
{
    protected $table = "ark_tiedosto_kons_kasittely";

    protected $primaryKey = array('ark_kons_kasittely_id', 'ark_tiedosto_id');

    public $incrementing = false;

    protected $fillable = [
            "ark_tiedosto_id",
            "ark_kons_kasittely_id"
    ];

    public $timestamps = false;



}
