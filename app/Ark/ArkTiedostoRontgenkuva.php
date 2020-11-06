<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkTiedostoRontgenkuva extends Model
{
    protected $table = "ark_tiedosto_rontgenkuva";

    protected $primaryKey = array('ark_rontgenkuva_id', 'ark_tiedosto_id');

    public $incrementing = false;

    protected $fillable = [
            "ark_tiedosto_id",
            "ark_rontgenkuva_id"
    ];

    public $timestamps = false;

    public static function whereTiedostoIdAndRontgenkuvaId($tiedosto_id, $xray_id) {
    	$ret = static::select('ark__tiedosto_rontgenkuva.*')->where('ark_tiedosto_id', '=', $tiedosto_id)->where('ark_rontgenkuva_id', '=', $xray_id);
    	return $ret;
    }

    public function rontgenkuva() {
        return $this->belongsTo('App\Ark\Rontgenkuva', 'ark_rontgenkuva_id');
    }


}
