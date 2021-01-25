<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 *
 */
class TutkimusKayttaja extends Model
{
    use SoftDeletes;

    protected $table = "ark_tutkimus_kayttaja";

    protected $fillable = array(
        'ark_tutkimus_id', 'kayttaja_id', 'organisaatio'
    );

    /*
     * Aikaleimat päivitetään automaattisesti by Laravel
     */
    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    /**
     * Haku id:n mukaan.
     */
    public static function getSingle($id) {
    	return self::select('ark_tutkimus_kayttaja.*')->where('id', '=', $id);
    }

    /**
     * Haku käyttäjäIdn ja tutkimuksen mukaan
     */
    public static function getSingleByTutkimusIdAndUserId($tutkimusId, $kayttajaId) {
        return self::select('ark_tutkimus_kayttaja.*')->where('ark_tutkimus_id', '=', $tutkimusId)->where('kayttaja_id', '=', $kayttajaId);
    }


    /**
     * Relaatiot
     */
    public function tutkimus() {
        return $this->belongsTo('App\Ark\Tutkimus', 'ark_tutkimus_id');
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

    public function scopeWithTutkimus($query, $id) {
        return $query->where('ark_tutkimus_id', '=', $id);
    }
}