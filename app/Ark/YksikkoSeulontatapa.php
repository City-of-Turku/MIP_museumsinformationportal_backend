<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class YksikkoSeulontatapa extends Model
{
    use SoftDeletes;

    protected $table = "yksikko_seulontatapa";
    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    protected $fillable = array (
        "id",
        "nimi_fi",
        "nimi_se",
        "nimi_en"
    );

    public static function getSingle($id) {
        return self::select('*')->where('id', '=', $id);
    }

    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }

    public function muokkaaja() {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }
}
