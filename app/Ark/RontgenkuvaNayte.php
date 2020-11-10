<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use App\Ark\Nayte;

class RontgenkuvaNayte extends Model
{
    protected $table = "ark_rontgenkuva_nayte";

    protected $primaryKey = array('ark_nayte_id', 'ark_rontgenkuva_id');
    public $incrementing = false;

    protected $fillable = [
            "ark_rontgenkuva_id",
            "ark_nayte_id"
    ];

    public $timestamps = false;

    public static function whereXrayIdAndNayteId($xray_id, $nayte_id) {
    	$ret = static::select('ark_rontgenkuva_nayte.*')->where('ark_rontgenkuva_id', '=', $xray_id)->where('ark_nayte_id', '=', $nayte_id);
    	return $ret;
    }

    public function nayte() {
        return $this->belongsTo('App\Ark\Nayte', 'ark_nayte_id');
    }

}
