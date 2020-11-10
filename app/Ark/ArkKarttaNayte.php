<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkKarttaNayte extends Model
{
    protected $table = "ark_kartta_nayte";

    protected $primaryKey = array('ark_nayte_id', 'ark_kartta_id');

    public $incrementing = false;

    protected $fillable = [
            "ark_kartta_id",
            "ark_nayte_id"
    ];

    public $timestamps = false;

    public static function whereKarttaIdAndNayteId($kartta_id, $nayte_id) {
    	$ret = static::select('ark_kartta_nayte.*')->where('ark_kartta_id', '=', $kartta_id)->where('ark_nayte_id', '=', $nayte_id);
    	return $ret;
    }

    public function nayte() {
        return $this->belongsTo('App\Ark\Nayte', 'ark_nayte_id');
    }


}
