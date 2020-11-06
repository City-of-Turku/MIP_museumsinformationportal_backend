<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class KohdeOsoite extends Model
{
    protected $table = "ark_kohde_osoite";
    protected $fillable = array('ark_kohde_kiinteistorakennus_id', 'rakennustunnus', 'katunimi', 'katunumero', 'postinumero', 'kuntanimi', 'kieli');
    public $timestamps = false;

    /*
     * Osoitetiedot kohteen välitaulun id:llä
     */
    public static function byKiinteistorakennusId($id) {
        return static::select('ark_kohde_osoite.*')
        ->where('ark_kohde_osoite.ark_kohde_kiinteistorakennus_id', '=', "$id");
    }

    public function kiinteistoRakennus() {
        return $this->belongsTo('App\Ark\KiinteistoRakennus', 'id', 'ark_kohde_kiinteistorakennus_id');
    }
}
