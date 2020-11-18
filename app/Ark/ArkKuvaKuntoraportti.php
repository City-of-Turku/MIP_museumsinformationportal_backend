<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ArkKuvaKuntoraportti extends Model
{
    protected $table = "ark_kuva_kuntoraportti";

    protected $primaryKey = array('ark_kuntoraportti_id', 'ark_kuva_id');

    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "ark_kuva_id",
            "ark_kuntoraportti_id"
    ];

    public $timestamps = false;

    public function kuntoraportti() {
        return $this->belongsTo('App\Ark\ArkKuntoraportti', 'ark_kuntoraportti_id');
    }
}
