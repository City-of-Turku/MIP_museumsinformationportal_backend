<?php

namespace App\Muistot;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Muistot_aihe_kayttaja extends Model
{
    use SoftDeletes;

    protected $table = "muistot_aihe_kayttaja";

    protected $fillable = array (
        "muistot_aihe_id", "kayttaja_id"
    );

    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    public static function getSingleByAiheIdAndUserId($aiheId, $kayttajaId) {
        return self::select('muistot_aihe_kayttaja.*')->where('muistot_aihe_id', '=', $aiheId)->where('kayttaja_id', '=', $kayttajaId);
    }

    public function muistotAihe(){
        return $this->belongsTo("App\Muistot\Muistot_aihe", "muistot_aihe_id");
    }

    public function kayttaja() {
        return $this->belongsTo('App\Kayttaja', 'kayttaja_id', 'id');
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

    public function scopeWithAihe($query, $id) {
        return $query->where('muistot_aihe_id', '=', $id);
    }

}