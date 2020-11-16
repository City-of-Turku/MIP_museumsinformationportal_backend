<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ArkKuntoraportti extends Model
{

    use SoftDeletes;

    protected $table = "ark_kuntoraportti";

    protected $fillable = [
        "kohteen_lisakuvaus",
        "loydon_kunto",
        "kasittelyohjeet",
        "olosuhdesuositukset",
        "pakkausohjeet",
        "ark_loyto_id"
    ];

    public $timestamps = false;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    public function loyto() {
        return $this->belongsTo('App\Ark\Loyto', 'id');
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
