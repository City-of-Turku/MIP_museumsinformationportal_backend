<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkKarttaLoyto extends Model
{
    protected $table = "ark_kartta_loyto";

    protected $primaryKey = array('ark_loyto_id', 'ark_kartta_id');

    public $incrementing = false;

    protected $fillable = [
            "ark_kartta_id",
            "ark_loyto_id"
    ];

    public $timestamps = false;

    public static function whereKarttaIdAndLoytoId($kartta_id, $loyto_id) {
    	$ret = static::select('ark_kartta_loyto.*')->where('ark_kartta_id', '=', $kartta_id)->where('ark_loyto_id', '=', $loyto_id);
    	return $ret;
    }

    public function loyto() {
        return $this->belongsTo('App\Ark\Loyto', 'ark_loyto_id');
    }


}
