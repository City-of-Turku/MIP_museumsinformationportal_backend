<?php

namespace App\Ark;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class KohdeSijainti extends Model {
	
	protected $table = "ark_kohde_sijainti";
	protected $fillable = array('id', 'kohde_id', 'sijainti', 'tuhoutunut');
	protected $appends = array('geometry', 'type', 'properties');
	protected $hidden = ['sijainti'];
	public $timestamps = false;
		
	public function sijainnit() {
		return $this->belongsTo('App\Ark\Kohde', 'id', 'kohde_id');
	}
	
	public function getTypeAttribute() {
		return 'Feature';
	}
	
	public function getPropertiesAttribute() {
		$kohde = DB::select(DB::raw('select nimi from ark_kohde where id = '.$this->kohde_id));
		return array('tuhoutunut' => $this->tuhoutunut, 'nimi' => $kohde[0]->nimi);
	}
	
	public function getGeometryAttribute() {
		$s = DB::select(DB::raw("select ST_AsText(ST_transform(ark_kohde_sijainti.sijainti, ".Config::get('app.json_srid').")) from ark_kohde_sijainti where ark_kohde_sijainti.id = :id", ["id" => $this->id]), [$this->id]);
		
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
	
	public static function paivita_kohde_sijainnit($kohde_id, $sijainnit) {
		if(!is_null($sijainnit)) {
			//Poistetaan vanhat ja lisätään uudet
			DB::table('ark_kohde_sijainti')->where('kohde_id', $kohde_id)->delete();
			
			foreach($sijainnit as $sijainti) {
				$ks = new KohdeSijainti();
				
				$ks->kohde_id = $kohde_id;
				
				if($sijainti['geometry']['type'] == 'Point') {
					$geom = MipGis::getPointGeometryValue($sijainti['geometry']['coordinates'][0] . " ".  $sijainti['geometry']['coordinates'][1]);
				} else if($sijainti['geometry']['type'] == 'Polygon') {
					$coordsAsText = MipGis::extractPolygonPoints($sijainti['geometry']);
					$geom = MipGis::getAreaGeometryValue($coordsAsText);
				}
				
				$ks->sijainti = $geom;
				
				//TODO: Varmista tuhoutunut-toiminnallisuus
				$ks->tuhoutunut = $sijainti['properties']['tuhoutunut'];
				$ks->luoja = Auth::user()->id;
				
				$ks->save();
			}
			
		}
	}
	
		
}
