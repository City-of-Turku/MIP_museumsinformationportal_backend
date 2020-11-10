<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkTiedostoNayte extends Model
{
    protected $table = "ark_tiedosto_nayte";

    protected $primaryKey = array('ark_nayte_id', 'ark_tiedosto_id');

    public $incrementing = false;

    protected $fillable = [
            "ark_tiedosto_id",
            "ark_nayte_id"
    ];

    public $timestamps = false;

    public function nayte() {
        return $this->belongsTo('App\Ark\Nayte', 'ark_nayte_id');
    }

}
