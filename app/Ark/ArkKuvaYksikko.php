<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ArkKuvaYksikko extends Model
{
    protected $table = "ark_kuva_yksikko";

    protected $primaryKey = array('ark_yksikko_id', 'ark_kuva_id');
    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "ark_kuva_id",
            "ark_yksikko_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndYksikkoId($kuva_id, $yksikko_id) {
    	$ret = static::select('ark_kuva_yksikko.*')->where('ark_kuva_id', '=', $kuva_id)->where('ark_yksikko_id', '=', $yksikko_id);
    	return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $yksikko_id) {
    	DB::update('update ark_kuva_yksikko set jarjestys = ? where ark_kuva_id = ? and ark_yksikko_id = ?', [$jarjestys, $kuva_id, $yksikko_id]);
    }

    public function yksikko() {
        return $this->belongsTo('App\Ark\TutkimusalueYksikko', 'ark_yksikko_id');
    }

}
