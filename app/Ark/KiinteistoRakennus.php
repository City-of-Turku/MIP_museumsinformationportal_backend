<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class KiinteistoRakennus extends Model
{
	protected $table = "ark_kohde_kiinteistorakennus";
	protected $fillable = array('id', 'kohde_id', 'kiinteistotunnus', 'kiinteisto_nimi',
			'osoite_katunimi', 'osoite_katunumero', 'osoite_postinumero', 'osoite_kuntanimi'
	);
	
	public $timestamps = false;

	
	public static function paivita_kohde_kiinteistorakennustiedot($kohde_id, $kiinteistotrakennukset) {
				
		if (!is_null($kiinteistotrakennukset)) {
		    
		    
		    // Haetaan kiinteistorakennukset, poistetaan ensin osoitteet ja sen jälkeen kiinteistorakennus
		    $kiinteistoRakennukset = KiinteistoRakennus::where('ark_kohde_id', $kohde_id)->get();
		    
		    foreach($kiinteistoRakennukset as $kiinteistorakennus) {
		        KohdeOsoite::where('ark_kohde_kiinteistorakennus_id', $kiinteistorakennus->id)->delete();

		        $kiinteistorakennus->delete();
		    }
		    
		    // Luodaan uudet
			foreach($kiinteistotrakennukset as $kr) {
				$nkr = new KiinteistoRakennus();

				$nkr->ark_kohde_id = $kohde_id;

				array_key_exists('kiinteistotunnus', $kr) ? $nkr->kiinteistotunnus = $kr['kiinteistotunnus'] : null;
				array_key_exists('kiinteisto_nimi', $kr) ? $nkr->kiinteisto_nimi = $kr['kiinteisto_nimi'] : null;
				
				// Rakennustunnus välitetään osoitteelle 
				$rakennustunnus = null;
				array_key_exists('rakennustunnus', $kr) ? $rakennustunnus = $kr['rakennustunnus'] : null;
				
				$nkr->luoja = Auth::user()->id;

				$nkr->save();
				
				foreach($kr['osoitteet'] as $input_osoite) {

				    $osoite = new KohdeOsoite();
				    $osoite->ark_kohde_kiinteistorakennus_id = $nkr->id;
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
	    return $this->hasMany('App\Ark\KohdeOsoite', 'ark_kohde_kiinteistorakennus_id', 'id');
	}
}
