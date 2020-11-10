<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KuvaKiinteisto extends Model
{
    protected $table = "kuva_kiinteisto";

    protected $primaryKey = array('kiinteisto_id', 'kuva_id');
    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "kuva_id",
            "kiinteisto_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndKiinteistoId($kuva_id, $entiteetti_id) {
    	$ret = static::select('kuva_kiinteisto.*')->where('kuva_id', '=', $kuva_id)->where('kiinteisto_id', '=', $entiteetti_id);
    	return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $kiinteisto_id) {
    	DB::update('update kuva_kiinteisto set jarjestys = ? where kuva_id = ? and kiinteisto_id = ?', [$jarjestys, $kuva_id, $kiinteisto_id]);
    }

    public static function getFirst($kiinteisto_id) {
    	$ret = static::select('kuva_kiinteisto.*')->where('kiinteisto_id', '=', $kiinteisto_id)->where('jarjestys', '=', 1);
    	return $ret;
    }

    public static function getAll($kiinteisto_id) {
    	$ret = static::select('kuva_kiinteisto.*')
    		->join('kuva', 'kuva.id', '=', 'kuva_kiinteisto.kuva_id')
    		->where('kiinteisto_id', '=', $kiinteisto_id)
    		->whereNull('kuva.poistettu');
    	return $ret;
    }

}
