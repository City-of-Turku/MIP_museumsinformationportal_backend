<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KuvaArvoalue extends Model
{
    protected $table = "kuva_arvoalue";

    protected $primaryKey = array('arvoalue_id', 'kuva_id');
    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "kuva_id",
            "arvoalue_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndArvoalueId($kuva_id, $entiteetti_id) {
    	$ret = static::select('kuva_arvoalue.*')->where('kuva_id', '=', $kuva_id)->where('arvoalue_id', '=', $entiteetti_id);
    	return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $arvoalue_id) {
    	DB::update('update kuva_arvoalue set jarjestys = ? where kuva_id = ? and arvoalue_id = ?', [$jarjestys, $kuva_id, $arvoalue_id]);

    }

}
