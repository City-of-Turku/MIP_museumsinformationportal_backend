<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KuvaAlue extends Model
{
    protected $table = "kuva_alue";

    protected $primaryKey = array('alue_id', 'kuva_id');
    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "kuva_id",
            "alue_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndAlueId($kuva_id, $entiteetti_id) {
    	$ret = static::select('kuva_alue.*')->where('kuva_id', '=', $kuva_id)->where('alue_id', '=', $entiteetti_id);
    	return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $alue_id) {
    	DB::update('update kuva_alue set jarjestys = ? where kuva_id = ? and alue_id = ?', [$jarjestys, $kuva_id, $alue_id]);

    }


}
