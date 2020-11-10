<?php
/*
 * Tätä ei tarvita, alakohteella on ainoastaan yksi tyyppi + tarkenne. Poistetaan
 */

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class AlakohdeTyyppi extends Model {
	
	protected $table = "ark_alakohde_tyyppi";
	protected $fillable = array('ark_kohde_alakohde_id', 'tyyppi_id', 'tyyppitarkenne_id');
	public $timestamps = false;
		
	/*
	public static function paivita_kohde_tyypit($kohde_id, $tyypit) {
		if (!is_null($tyypit)) {
			// remove all selections and add new ones
			DB::table('ark_kohde_tyyppi')->where('ark_kohde_id', $kohde_id)->delete();
			
			foreach($tyypit as $kt) {
				$kohdetyyppi = new KohdeTyyppi();
				$kohdetyyppi->ark_kohde_id = $kohde_id;
				$kohdetyyppi->tyyppi_id = $kt['tyyppi']['id'];
				array_key_exists('tarkenne', $kt) ? $kohdetyyppi->tyyppitarkenne_id = $kt['tarkenne']['id'] : null;
				$kohdetyyppi->luoja = Auth::user()->id;
				$kohdetyyppi->save();				
			}
		}
	}	
	*/
	
	public function tyyppi() {
		return $this->hasOne('App\Ark\Tyyppi', 'id', 'tyyppi_id');
	}
	
	public function tarkenne() {
		return $this->hasOne('App\Ark\Tyyppitarkenne', 'id', 'tyyppitarkenne_id');
	}
}
