<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Näytteen tilakoodit
 */
class NayteTila extends Model
{
    protected $table = "ark_nayte_tila";
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
}
