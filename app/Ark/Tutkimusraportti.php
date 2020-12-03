<?php

namespace App\Ark;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Kayttaja;
/**
 * Tutkimusraportti.
 *
 */
class Tutkimusraportti extends Model
{
    use SoftDeletes;

    protected $table = "ark_tutkimusraportti";

    protected $fillable = array(
        'ark_tutkimus_id', 'arkisto_ja_rekisteritiedot', 'johdanto',
        'tutkimus_ja_dokumentointimenetelmat', 'havainnot', 'yhteenveto',
        'lahdeluettelo', 'liitteet', 'tiivistelma'
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
        return self::select('ark_tutkimusraportti.*')->where('id', '=', $id);
    }

    /**
     * Kaikkien haku - Rajoitetaan katselijoiden näkemät rivit suoraan
     */
    public static function getAll() {
    	return self::select('ark_tutkimusraportti.*');
    }


    /**
     * Relaatiot
     */
    public function kuvat() {
        return $this->belongsToMany('App\Ark\ArkKuva', 'ark_tutkimusraportti_kuva', 'ark_tutkimusraportti_id')->withPivot(['kappale', 'jarjestys', 'id']);
    }
    public function tutkimusraporttikuvat() {
        return $this->hasMany('App\Ark\TutkimusraporttiKuva', 'ark_tutkimusraportti_id');
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
    public function tutkimus() {
        return $this->belongsTo('App\Ark\Tutkimus', 'ark_tutkimus_id');
    }
}