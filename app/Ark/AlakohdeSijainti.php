<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Sijainnin tallennus ja päivitys hoidetaan luokassa KohdeAlakohde.
 */
class AlakohdeSijainti extends Model {

	protected $table = "ark_alakohde_sijainti";
	protected $fillable = array('id', 'ark_kohde_alakohde_id', 'sijainti');
	protected $appends = array('geometry', 'type', 'properties');
	protected $hidden = ['sijainti'];
	public $timestamps = false;

	public function getPropertiesAttribute() {
		$alakohde = DB::select(DB::raw('select ark_kohde_alakohde.nimi, ark_kohde.ark_kohdelaji_id from ark_kohde_alakohde left join ark_kohde on ark_kohde.id = ark_kohde_alakohde.ark_kohde_id  where ark_kohde_alakohde.id = '.$this->ark_kohde_alakohde_id));
		return array('nimi' => $alakohde[0]->nimi, 'ark_kohdelaji_id' => $alakohde[0]->ark_kohdelaji_id);
	}


	public function getTypeAttribute() {
		return 'Feature';
	}

	public function getGeometryAttribute() {
		$s = DB::select(DB::raw("select ST_AsText(ST_transform(ark_alakohde_sijainti.sijainti, ".Config::get('app.json_srid').")) from ark_alakohde_sijainti where ark_alakohde_sijainti.id = :id", ["id" => $this->id]), [$this->id]);

		$splittedGeom = explode('(', $s[0]->st_astext);
		$type = $splittedGeom[0];
		$type = ucfirst(strtolower($type));//e.g. POINT --> Point

		if($type == 'Point') {
			$coordinates = $splittedGeom[1];

			//Poistetaan lopusta ) merkki
			if(substr($coordinates, -1) == ')') {
				$coordinates = rtrim($coordinates, ')');
			}

			//Parit on eroteltuna välilyönneillä, erotetaan parit omiksi arrayksi. Muutetaan string numeroksi
			$pair = explode(" ", $coordinates);
			$pair[0] = (float)$pair[0];
			$pair[1] = (float)$pair[1];

			$coordinates = $pair;

		} else if($type == 'Polygon') {
			$coordinates = $splittedGeom[2]; //Polygon muodossa koordinatit ovat 2. paikassa.

			//Poistetaan lopusta ) merkki
			if(substr($coordinates, -1) == ')') {
				$coordinates = rtrim($coordinates, ')');
			}

			//Erotellaan koordinaattiparit arrayksi
			$coordinates = explode(',', $coordinates);
			$pairs = [];

			//Jokaiselle parille muutetaan koordinaatit arrayksi ja muutetaan string numeroksi
			foreach($coordinates as $coord) {
				$coord = explode(' ', $coord);
				$coord[0] = (float)$coord[0];
				$coord[1] = (float)$coord[1];

				array_push($pairs, $coord);
			}

			$coordinates = [$pairs];

		}
		//Laitetaan koordinaatit valmiiksi oikeanlaiseen arrayhyn
		$ret = array('type' => $type, 'coordinates' => $coordinates);
		return $ret;
	}

}
