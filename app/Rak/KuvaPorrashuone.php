<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KuvaPorrashuone extends Model
{
    protected $table = "kuva_porrashuone";

    protected $primaryKey = array('kuva_id', 'porrashuone_id');
    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "kuva_id",
            "porrashuone_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndPorrashuoneId($kuva_id, $entiteetti_id) {
    	$ret = static::select('kuva_porrashuone.*')->where('kuva_id', '=', $kuva_id)->where('porrashuone_id', '=', $entiteetti_id);
    	return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $porrashuone_id) {
    	DB::update('update kuva_porrashuone set jarjestys = ? where kuva_id = ? and porrashuone_id = ?', [$jarjestys, $kuva_id, $porrashuone_id]);
    }
}
