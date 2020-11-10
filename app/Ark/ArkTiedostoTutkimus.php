<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkTiedostoTutkimus extends Model
{
    protected $table = "ark_tiedosto_tutkimus";

    protected $primaryKey = array('ark_tutkimus_id', 'ark_tiedosto_id');

    public $incrementing = false;

    protected $fillable = [
            "ark_tiedosto_id",
            "ark_tutkimus_id"
    ];

    public $timestamps = false;

    public function tutkimus() {
        return $this->belongsTo('App\Ark\Tutkimus', 'ark_tutkimus_id');
    }

}
