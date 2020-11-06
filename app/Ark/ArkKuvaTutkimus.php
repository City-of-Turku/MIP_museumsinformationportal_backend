<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class ArkKuvaTutkimus extends Model
{
    protected $table = "ark_kuva_tutkimus";

    protected $primaryKey = array('ark_tutkimus_id', 'ark_kuva_id');

    public $incrementing = false;

    protected $fillable = [
        "jarjestys",
        "ark_kuva_id",
        "ark_tutkimus_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndTutkimusId($kuva_id, $tutkimus_id) {
        $ret = static::select('ark_kuva_tutkimus.*')->where('ark_kuva_id', '=', $kuva_id)->where('ark_tutkimus_id', '=', $tutkimus_id);
        return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $tutkimus_id) {
        DB::update('update ark_kuva_tutkimus set jarjestys = ? where ark_kuva_id = ? and ark_tutkimus_id = ?', [$jarjestys, $kuva_id, $tutkimus_id]);
    }

    public function tutkimus() {
        return $this->belongsTo('App\Ark\Tutkimus', 'ark_tutkimus_id');
    }
}
