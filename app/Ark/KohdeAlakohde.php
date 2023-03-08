<?php

namespace App\Ark;
use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
class KohdeAlakohde extends Model {

	protected $table = "ark_kohde_alakohde";
	protected $fillable = array('ark_kohde_alakohde_id', 'nimi', 'kuvaus', 'koordselite', 'korkeus_min', 'korkeus_max');
	/**
	 * Laravel ei ymmärrä kaikkia SQL-tyyppejä. Castataan kentät, jotta erityisesti doublet menevät oikein.
	 */
	protected $casts = ['korkeus_min' => 'double', 'korkeus_max' => 'double'];
	public $timestamps = false;

	public static function paivita_kohde_alakohteet($kohde_id, $alakohteet) {

		//Haetaan alakohteet ja poistetaan viittaukset ja tämän jälkeen itse alakohteet
		$kohdeAlakohteet = KohdeAlakohde::where('ark_kohde_id', $kohde_id)->get();

		foreach($kohdeAlakohteet as $ak) {
			AlakohdeSijainti::where('ark_kohde_alakohde_id', $ak->id)->delete();
			AlakohdeAjoitus::where('ark_kohde_alakohde_id', $ak->id)->delete();
			$ak->delete();
		}

		if (!is_null($alakohteet)) {
			foreach($alakohteet as $ak) {
				$kohdeAlakohde = new KohdeAlakohde();
				$kohdeAlakohde->ark_kohde_id = $kohde_id;
				array_key_exists('nimi', $ak) ? $kohdeAlakohde->nimi = $ak['nimi'] : null;
				array_key_exists('kuvaus', $ak) ? $kohdeAlakohde->kuvaus = $ak['kuvaus'] : null;
                if (array_key_exists('tyyppi', $ak) && $ak['tyyppi'] != null){
                    $kohdeAlakohde->ark_kohdetyyppi_id = $ak['tyyppi']['id'];
                } 
                if (array_key_exists('tyyppitarkenne', $ak) && $ak['tyyppitarkenne'] != null){
                    $kohdeAlakohde->ark_kohdetyyppitarkenne_id = $ak['tyyppitarkenne']['id'];
                } 
			array_key_exists('koordselite', $ak) ? $kohdeAlakohde->koordselite = $ak['koordselite'] : null;
				array_key_exists('korkeus_min', $ak) ? $kohdeAlakohde->korkeus_min = $ak['korkeus_min'] : null;
				array_key_exists('korkeus_max', $ak) ? $kohdeAlakohde->korkeus_max = $ak['korkeus_max'] : null;
				$kohdeAlakohde->luoja = Auth::user()->id;
				$kohdeAlakohde->save();

				foreach($ak['sijainnit'] as $sijainti) {
					$aks = new AlakohdeSijainti();

					$aks->ark_kohde_alakohde_id = $kohdeAlakohde->id;

					if($sijainti['geometry']['type'] == 'Point') {
						$geom = MipGis::getPointGeometryValue($sijainti['geometry']['coordinates'][0] . " ".  $sijainti['geometry']['coordinates'][1]);
					} else if($sijainti['geometry']['type'] == 'Polygon') {
						$coordsAsText = MipGis::extractPolygonPoints($sijainti['geometry']);
						$geom = MipGis::getAreaGeometryValue($coordsAsText);
					}

					$aks->sijainti = $geom;
					$aks->luoja = Auth::user()->id;
					$aks->save();
				}
				foreach($ak['ajoitukset'] as $a) {
					$aka = new AlakohdeAjoitus();
					$aka->ark_kohde_alakohde_id = $kohdeAlakohde->id;
					$aka->ajoitus_id = $a['ajoitus']['id'];
					array_key_exists('tarkenne', $a) ? $aka->ajoitustarkenne_id = $a['tarkenne']['id'] : null;
					array_key_exists('ajoituskriteeri', $a) ? $aka->ajoituskriteeri = $a['ajoituskriteeri'] : null;
					$aka->luoja = Auth::user()->id;
					$aka->save();
				}
			}
		}
	}

	//Palauttaa sijainnit valmiiksi arrayssä. "Custom?" toteutus on AlakohdeSijainti.php luokassa.
	public function sijainnit() {
		return $this->hasMany('App\Ark\AlakohdeSijainti', 'ark_kohde_alakohde_id', 'id');
	}
	public function tyyppi() {
		return $this->hasOne('App\Ark\Tyyppi', 'id', 'ark_kohdetyyppi_id');
	}

	public function tyyppitarkenne() {
		return $this->hasOne('App\Ark\Tyyppitarkenne', 'id', 'ark_kohdetyyppitarkenne_id');
	}

	public function ajoitukset() {
		return $this->hasMany('App\Ark\AlakohdeAjoitus', 'ark_kohde_alakohde_id', 'id');
	}
}
