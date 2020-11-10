<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class TutkimusOsoite extends Model
{
    protected $table = "ark_tutkimus_osoite";
    protected $fillable = array('ark_tutkimus_kiinteistorakennus_id', 'rakennustunnus', 'katunimi', 'katunumero', 'postinumero', 'kuntanimi', 'kieli');
    public $timestamps = false;

    /*
     * Osoitetiedot kohteen välitaulun id:llä
     */
    public static function byKiinteistorakennusId($id) {
        return static::select('ark_tutkimus_osoite.*')
        ->where('ark_tutkimus_osoite.ark_tutkimus_kiinteistorakennus_id', '=', "$id");
    }

    public function kiinteistoRakennus() {
        return $this->belongsTo('App\Ark\TutkimusKiinteistoRakennus', 'id', 'ark_tutkimus_kiinteistorakennus_id');
    }
}
