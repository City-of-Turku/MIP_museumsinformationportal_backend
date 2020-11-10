<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkKarttaTutkimusalue extends Model
{
    protected $table = "ark_kartta_tutkimusalue";

    protected $primaryKey = array('ark_tutkimusalue_id', 'ark_kartta_id');
    public $incrementing = false;

    protected $fillable = [
            "ark_kartta_id",
            "ark_tutkimusalue_id"
    ];

    public $timestamps = false;

    public static function whereKarttaIdAndTutkimusalueId($kartta_id, $tutkimusalue_id) {
    	$ret = static::select('ark_kartta_tutkimusalue.*')->where('ark_kartta_id', '=', $kartta_id)->where('ark_tutkimusalue_id', '=', $tutkimusalue_id);
    	return $ret;
    }

    public function tutkimusalue() {
        return $this->belongsTo('App\Ark\Tutkimusalue', 'ark_tutkimusalue_id');
    }
}
