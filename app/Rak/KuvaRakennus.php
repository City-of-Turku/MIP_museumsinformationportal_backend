<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KuvaRakennus extends Model
{
    protected $table = "kuva_rakennus";

    protected $primaryKey = array('kuva_id', 'rakennus_id');
    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "kuva_id",
            "rakennus_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndRakennusId($kuva_id, $entiteetti_id) {
    	$ret = static::select('kuva_rakennus.*')->where('kuva_id', '=', $kuva_id)->where('rakennus_id', '=', $entiteetti_id);
    	return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $rakennus_id) {
    	DB::update('update kuva_rakennus set jarjestys = ? where kuva_id = ? and rakennus_id = ?', [$jarjestys, $kuva_id, $rakennus_id]);
    }
}
