<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ArkKuvaNayte extends Model
{
    protected $table = "ark_kuva_nayte";

    protected $primaryKey = array('ark_nayte_id', 'ark_kuva_id');
    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "ark_kuva_id",
            "ark_nayte_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndNayteId($kuva_id, $nayte_id) {
    	$ret = static::select('ark_kuva_nayte.*')->where('ark_kuva_id', '=', $kuva_id)->where('ark_nayte_id', '=', $nayte_id);
    	return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $nayte_id) {
    	DB::update('update ark_kuva_nayte set jarjestys = ? where ark_kuva_id = ? and ark_nayte_id = ?', [$jarjestys, $kuva_id, $nayte_id]);
    }

    public function nayte() {
        return $this->belongsTo('App\Ark\Nayte', 'ark_nayte_id');
    }

}
