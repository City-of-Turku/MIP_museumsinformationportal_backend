<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkTiedostoToimenpide extends Model
{
    protected $table = "ark_tiedosto_kons_toimenpiteet";

    protected $primaryKey = array('ark_kons_toimenpiteet_id', 'ark_tiedosto_id');

    public $incrementing = false;

    protected $fillable = [
            "ark_tiedosto_id",
            "ark_kons_toimenpiteet_id"
    ];

    public $timestamps = false;



}
