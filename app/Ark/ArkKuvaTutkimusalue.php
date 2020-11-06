<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ArkKuvaTutkimusalue extends Model
{
    protected $table = "ark_kuva_tutkimusalue";

    protected $primaryKey = array('ark_tutkimusalue_id', 'ark_kuva_id');

    public $incrementing = false;

    protected $fillable = [
        "jarjestys",
        "ark_kuva_id",
        "ark_tutkimusalue_id"
    ];

    public $timestamps = false;

    public static function whereImageIdAndTutkimusalueId($kuva_id, $tutkimusalue_id) {
        $ret = static::select('ark_kuva_tutkimusalue.*')->where('ark_kuva_id', '=', $kuva_id)->where('ark_tutkimusalue_id', '=', $tutkimusalue_id);
        return $ret;
    }

    public function updateJarjestys($jarjestys, $kuva_id, $tutkimusalue_id) {
        DB::update('update ark_kuva_tutkimusalue set jarjestys = ? where ark_kuva_id = ? and ark_tutkimusalue_id = ?', [$jarjestys, $kuva_id, $tutkimusalue_id]);
    }

    public function tutkimusalue() {
        return $this->belongsTo('App\Ark\Tutkimusalue', 'ark_tutkimusalue_id');
    }
}
