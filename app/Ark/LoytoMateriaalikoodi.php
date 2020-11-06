<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoytoMateriaalikoodi extends Model
{
    use SoftDeletes;

    protected $table = "ark_loyto_materiaalikoodi";
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

    // Palauttaa kaikki koodit ja nimet
    public static function koodit() {
        return self::select('koodi', 'nimi_fi');
    }

    /*
     * Palauttaa materiaalikoodin mukaiset ensisijaiset materiaalit
     */
    public function ensisijaisetMateriaalit() {
        return $this->belongsToMany('App\Ark\LoytoMateriaali' ,'ark_loyto_ensisijaiset_materiaalit' ,'ark_loyto_materiaalikoodi_id', 'ark_loyto_materiaali_id');
    }

    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }

    public function muokkaaja() {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }
}
