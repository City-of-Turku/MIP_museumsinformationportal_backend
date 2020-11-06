<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ArkKuvaLoyto extends Model
{
    protected $table = "ark_kuva_loyto";

    protected $primaryKey = array('ark_loyto_id', 'ark_kuva_id');

    public $incrementing = false;

    protected $fillable = [
            "jarjestys",
            "ark_kuva_id",
            "ark_loyto_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndLoytoId($kuva_id, $loyto_id) {
    	$ret = static::select('ark_kuva_loyto.*')->where('ark_kuva_id', '=', $kuva_id)->where('ark_loyto_id', '=', $loyto_id);
    	return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $loyto_id) {
    	DB::update('update ark_kuva_loyto set jarjestys = ? where ark_kuva_id = ? and ark_loyto_id = ?', [$jarjestys, $kuva_id, $loyto_id]);
    }

    public function loyto() {
        return $this->belongsTo('App\Ark\Loyto', 'ark_loyto_id');
    }


}
