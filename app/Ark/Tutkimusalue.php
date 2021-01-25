<?php

namespace App\Ark;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Tutkimusalue.
 *
 */
class Tutkimusalue extends Model
{
    use SoftDeletes;

    protected $table = "ark_tutkimusalue";

    protected $fillable = array(
        'ark_tutkimus_id', 'nimi', 'sijaintikuvaus', 'muistiinpanot', 'havainnot', 'yhteenveto'
    );

    /**
     * Piilotetaan palautettavista tiedoista sijainti, palautetaan se erikseen.
     */
    protected $hidden = ['sijainti'];
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
    	return self::select('ark_tutkimusalue.*',
    			DB::raw(MipGis::getGeometryFieldQueryString("sijainti", "sijainti")),
    	        DB::raw(MipGis::getGeometryFieldQueryString("sijainti_piste", "sijainti_piste")))->where('id', '=', $id);
    }

    /**
     * Kaikkien haku
     */
    public static function getAll() {
        return self::select('ark_tutkimusalue.*');
    }

    /**
     * Relaatiot
     */
    public function tutkimus() {
        return $this->belongsTo('App\Ark\Tutkimus', 'ark_tutkimus_id');
    }

    public function yksikot() {
        return $this->hasMany('App\Ark\TutkimusalueYksikko', 'ark_tutkimusalue_id', 'id');
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

    /**
     * Suodatusjärjestys
     */
    public function scopeWithOrderBy($query, $jarjestys_kentta, $jarjestys_suunta) {
        /* TODO: Tech upgrade: Parameteri-ongelma.
         * 1. Kommentoitu pois if-lohko
         * 2. Muutettu return riviltä $jarjestys:suunta -> asc.
         *  Jostain syystä $query, $jarjestys_kentta ja $jarjestys_suunta
         *  parametrit menevät sekaisin ja 2. ensimmäistä ovat $queryä.
         */
        //if ($jarjestys_kentta == "nimi") {
        //    return $query->orderBy("ark_tutkimusalue.nimi", $jarjestys_suunta);
        //}
        //todo muut kentät jos on

        return $query->orderBy("ark_tutkimusalue.nimi", "asc");
    }

    public function scopeWithTutkimus($query, $id) {
        return $query->where('ark_tutkimus_id', '=', $id);
    }

    //KÄYTETÄÄN AINOASTAAN TUTKIMUSALUEELLE JOKA KUULUU IRTOLÖYTÖ-TUTKIMUKSEEN!
    public function loydot() {
        return $this->hasMany('App\Ark\Loyto', 'ark_tutkimusalue_id', 'id');
    }
    //KÄYTETÄÄN AINOASTAAN TUTKIMUSALUEELLE JOKA KUULUU IRTOLÖYTÖ-TUTKIMUKSEEN!
    public function naytteet() {
        return $this->hasMany('App\Ark\Loyto', 'ark_tutkimusalue_id', 'id');
    }

}