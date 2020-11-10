<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Näytekoodit
 */
class Naytekoodi extends Model
{
    use SoftDeletes;

    protected $table = "ark_naytekoodi";
    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    /**
     * fillable elements
     */
    protected $fillable = array (
        "nimi_fi",
        "nimi_se",
        "nimi_en",
        "aktiivinen"
    );

    public static function getSingle($id) {
        return self::select('*')->where('id', '=', $id);
    }

    /*
     * Palauttaa näytekoodille kuuluvat näytetyypit
     */
    public function naytetyypit() {
        return $this->belongsToMany('App\Ark\Naytetyyppi' ,'ark_naytetyypit' ,'ark_naytekoodi_id', 'ark_naytetyyppi_id');
    }

    // Palauttaa kaikki koodit ja nimet
    public static function koodit() {
        return self::select('koodi', 'nimi_fi');
    }
}
