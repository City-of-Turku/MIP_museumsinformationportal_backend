<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoytoLuettelointinroHistoria extends Model
{
    use SoftDeletes;

    protected $table = "ark_loyto_luettelonrohistoria";

    protected $fillable = array('ark_loyto_id', 'luettelointinumero_vanha', 'luettelointinumero_uusi', 'ark_loyto_tapahtumat_id',
        'luoja', 'luotu', 'muokkaaja', 'muokattu', 'poistaja', 'poistettu');

    /*
     * Aikaleimat päivitetään manuaalisesti
     */
    public $timestamps = false;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    /**
     * Haku löydön id:n mukaan.
     */
    public static function haeLoydonLuettelointinumerot($loyto_id) {
        return self::select('ark_loyto_luettelointinrohistoria.*')->where('ark_loyto_id', '=', $loyto_id);
    }

    /**
     * Haku id:n mukaan.
     */
    public static function getSingle($id) {
        return self::select('ark_loyto_luettelointinrohistoria.*')->where('id', '=', $id);
    }

    /**
     * Haku löydön id:n ja tapahtuman id:n mukaan.
     */
    public static function getWithLoytoIdAndTapahtumatId($loyto_id, $tapahtumat_id) {
        return self::select('ark_loyto_luettelointinrohistoria.*')
            ->where('ark_loyto_id', '=', $loyto_id)->where('ark_loyto_tapahtumat_id', '=', $tapahtumat_id)->first();
    }

    /**
     * Relaatiot
     */
    public function loyto() {
        return $this->belongsTo('App\Ark\Loyto', 'ark_loyto_id');
    }
    public function loytoTapahtuma() {
        return $this->belongsTo('App\Ark\LoytoTapahtumat', 'ark_loyto_tapahtumat_id');
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
}
