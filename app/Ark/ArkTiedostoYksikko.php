<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkTiedostoYksikko extends Model
{
    protected $table = "ark_tiedosto_yksikko";

    protected $primaryKey = array('ark_yksikko_id', 'ark_tiedosto_id');

    public $incrementing = false;

    protected $fillable = [
            "ark_tiedosto_id",
            "ark_yksikko_id"
    ];

    public $timestamps = false;

    public function yksikko() {
        return $this->belongsTo('App\Ark\TutkimusalueYksikko', 'ark_yksikko_id');
    }

}
