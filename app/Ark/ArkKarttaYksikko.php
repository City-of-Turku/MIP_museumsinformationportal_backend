<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkKarttaYksikko extends Model
{
    protected $table = "ark_kartta_yksikko";

    protected $primaryKey = array('ark_yksikko_id', 'ark_kartta_id');
    public $incrementing = false;

    protected $fillable = [
            "ark_kartta_id",
            "ark_yksikko_id"
    ];

    public $timestamps = false;

    public static function whereKarttaIdAndYksikkoId($kartta_id, $yksikko_id) {
    	$ret = static::select('ark_kartta_yksikko.*')->where('ark_kartta_id', '=', $kartta_id)->where('ark_yksikko_id', '=', $yksikko_id);
    	return $ret;
    }

    public function yksikko() {
        return $this->belongsTo('App\Ark\TutkimusalueYksikko', 'ark_yksikko_id');
    }

}
