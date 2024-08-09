<?php

namespace App\Muistot;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Muistot_muisto_kunta extends Model
{
    use SoftDeletes;

    protected $table = "muistot_muisto_kunta";

    protected $fillable = array (
        "muisto_id", "kunta_id", "kiinteistotunnus"
    );

    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    // public static function getSingleByAiheIdAndUserId($aiheId, $kayttajaId) {
    //     return self::select('muistot_aihe_kayttaja.*')->where('muistot_aihe_id', '=', $aiheId)->where('kayttaja_id', '=', $kayttajaId);
    // }

    public function muisto(){
        return $this->belongsTo("App\Muistot\Muistot_muisto", "muisto_id");
    }

    public function kunta() {
        return $this->belongsTo('App\Kunta', 'kunta_id', 'id');
    }

    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }

    public function muokkaaja() {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }

    public function poistaja() {
        return $this->belongsTo('App\Kayttaja', 'poistaja');
    }

    // public function scopeWithAihe($query, $id) {
    //     return $query->where('muistot_aihe_id', '=', $id);
    // }

}