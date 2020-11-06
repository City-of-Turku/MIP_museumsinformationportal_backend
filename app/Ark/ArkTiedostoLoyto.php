<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkTiedostoLoyto extends Model
{
    protected $table = "ark_tiedosto_loyto";

    protected $primaryKey = array('ark_loyto_id', 'ark_tiedosto_id');

    public $incrementing = false;

    protected $fillable = [
            "ark_tiedosto_id",
            "ark_loyto_id"
    ];

    public $timestamps = false;


    public function loyto() {
        return $this->belongsTo('App\Ark\Loyto', 'ark_loyto_id');
    }


}
