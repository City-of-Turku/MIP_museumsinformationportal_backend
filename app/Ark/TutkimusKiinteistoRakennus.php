<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TutkimusKiinteistoRakennus extends Model
{
    protected $table = "ark_tutkimus_kiinteistorakennus";
    protected $fillable = array('id', 'tutkimus_id', 'kiinteistotunnus', 'kiinteisto_nimi');

    public $timestamps = false;


    public static function paivita_tutkimus_kiinteistorakennustiedot($tutkimus_id, $kiinteistotrakennukset) {

        if (!is_null($kiinteistotrakennukset)) {


            // Haetaan kiinteistorakennukset, poistetaan ensin osoitteet ja sen jälkeen kiinteistorakennus
            $kiinteistoRakennukset = TutkimusKiinteistoRakennus::where('ark_tutkimus_id', $tutkimus_id)->get();

            foreach($kiinteistoRakennukset as $kiinteistorakennus) {
                TutkimusOsoite::where('ark_tutkimus_kiinteistorakennus_id', $kiinteistorakennus->id)->delete();

                $kiinteistorakennus->delete();
            }

            // Luodaan uudet
            foreach($kiinteistotrakennukset as $kr) {
                $nkr = new TutkimusKiinteistoRakennus();

                $nkr->ark_tutkimus_id = $tutkimus_id;

                array_key_exists('kiinteistotunnus', $kr) ? $nkr->kiinteistotunnus = $kr['kiinteistotunnus'] : null;
                array_key_exists('kiinteisto_nimi', $kr) ? $nkr->kiinteisto_nimi = $kr['kiinteisto_nimi'] : null;

                // Rakennustunnus välitetään osoitteelle
                $rakennustunnus = null;
                array_key_exists('rakennustunnus', $kr) ? $rakennustunnus = $kr['rakennustunnus'] : null;

                $nkr->luoja = Auth::user()->id;

                $nkr->save();

                foreach($kr['osoitteet'] as $input_osoite) {

                    $osoite = new TutkimusOsoite();
                    $osoite->ark_tutkimus_kiinteistorakennus_id = $nkr->id;
                    $osoite->kuntanimi = $input_osoite['kuntanimi'];
                    $osoite->postinumero = $input_osoite['postinumero'];
                    $osoite->rakennustunnus = $rakennustunnus;

                    $osoite->katunimi = $input_osoite['katunimi'];
                    $osoite->katunumero = $input_osoite['katunumero'];
                    $osoite->kieli = $input_osoite['kieli'];

                    $osoite->luoja = Auth::user()->id;
                    $osoite->save();

                }

            }
        }
    }

    public function osoitteet() {
        return $this->hasMany('App\Ark\TutkimusOsoite', 'ark_tutkimus_kiinteistorakennus_id', 'id');
    }
}
