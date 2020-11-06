<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kartan tyyppi
 */
class ArkKarttaTyyppi extends Model
{
    use SoftDeletes;

    protected $table = "ark_karttatyyppi";
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
        "tyyppi",
        "aktiivinen"
    );

    public static function getSingle($id) {
        return self::select('*')->where('id', '=', $id);
    }
}
