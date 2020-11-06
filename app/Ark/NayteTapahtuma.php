<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Näyteen tapahtuman koodit
 */
class NayteTapahtuma extends Model
{
    use SoftDeletes;

    protected $table = "ark_nayte_tapahtuma";
    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    // Vakiot bäkkärissä käytettäville tapahtuma-koodeille
    const LUETTELOITU = 4;

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



}
