<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ArkKuvaRontgenkuva extends Model
{
    protected $table = "ark_kuva_rontgenkuva";

    protected $primaryKey = array('ark_rontgenkuva_id', 'ark_kuva_id');

    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "ark_kuva_id",
            "ark_rontgenkuva_id"
    ];

    public $timestamps = false;

    public function rontgenkuva() {
        return $this->belongsTo('App\Ark\Rontgenkuva', 'ark_rontgenkuva_id');
    }
}
