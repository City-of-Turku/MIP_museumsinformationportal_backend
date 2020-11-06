<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KuvaSuunnittelija extends Model
{
    protected $table = "kuva_suunnittelija";

    protected $primaryKey = array('kuva_id', 'suunnittelija_id');
    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "kuva_id",
            "suunnittelija_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndSuunnittelijaId($kuva_id, $entiteetti_id) {
    	$ret = static::select('kuva_suunnittelija.*')->where('kuva_id', '=', $kuva_id)->where('suunnittelija_id', '=', $entiteetti_id);
    	return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $suunnittelija_id) {
        DB::update('update kuva_suunnittelija set jarjestys = ? where kuva_id = ? and suunnittelija_id = ?', [$jarjestys, $kuva_id, $suunnittelija_id]);
    }
}
