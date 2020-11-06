<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use App\Ark\Loyto;

class RontgenkuvaLoyto extends Model
{
    protected $table = "ark_rontgenkuva_loyto";

    protected $primaryKey = array('ark_loyto_id', 'ark_rontgenkuva_id');

    public $incrementing = false;

    protected $fillable = [
            "ark_rontgenkuva_id",
            "ark_loyto_id"
    ];

    public $timestamps = false;

    public static function whereXrayIdAndLoytoId($xray_id, $loyto_id) {
    	$ret = static::select('ark_rontgenkuva_loyto.*')->where('ark_rontgenkuva_id', '=', $xray_id)->where('ark_loyto_id', '=', $loyto_id);
    	return $ret;
    }

    public function loyto() {
        return $this->belongsTo('App\Ark\Loyto', 'ark_loyto_id');
    }


}
