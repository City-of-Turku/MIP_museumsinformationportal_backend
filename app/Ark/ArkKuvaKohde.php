<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ArkKuvaKohde extends Model
{
    protected $table = "ark_kuva_kohde";

    protected $primaryKey = array('ark_kohde_id', 'ark_kuva_id');

    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "ark_kuva_id",
            "ark_kohde_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndKohdeId($kuva_id, $kohde_id) {
    	$ret = static::select('ark_kuva_kohde.*')->where('ark_kuva_id', '=', $kuva_id)->where('ark_kohde_id', '=', $kohde_id);
    	return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $kohdeo_id) {
    	DB::update('update ark_kuva_kohde set jarjestys = ? where ark_kuva_id = ? and ark_kohde_id = ?', [$jarjestys, $kuva_id, $kohde_id]);
    }

    public function kohde() {
        return $this->belongsTo('App\Ark\Kohde', 'ark_kohde_id');
    }


}
