<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KuvaKyla extends Model
{
    protected $table = "kuva_kyla";

    protected $primaryKey = array('kuva_id', 'kyla_id');
    public $incrementing = false;
    
    protected $fillable = [
            "jarjestys",
            "kuva_id",
            "kyla_id"
    ];
    
    public $timestamps = false;
    
    public static function whereImageIdAndKylaId($kuva_id, $entiteetti_id) {
    	$ret = static::select('kuva_kyla.*')->where('kuva_id', '=', $kuva_id)->where('kyla_id', '=', $entiteetti_id);
    	return $ret;
    }
    
    public function updateJarjestys($jarjestys, $kuva_id, $kyla_id) {
    	DB::update('update kuva_kyla set jarjestys = ? where kuva_id = ? and kyla_id = ?', [$jarjestys, $kuva_id, $kyla_id]);
    	
    }
    

}
