<?php

namespace App\Ark;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KohdeAjoitus extends Model {

	protected $table = "ark_kohde_ajoitus";
	protected $fillable = array('ark_kohde_id', 'ajoitus_id', 'ajoitustarkenne_id', 'ajoituskriteeri');
	public $timestamps = false;

	public static function byKohdeId($kohde_id) {
		return static::select('ark_kohde_ajoitus.*')
			->where('ark_kohde_ajoitus.ark_kohde_id', '=', "$kohde_id");
	}

	public static function paivita_kohde_ajoitukset($kohde_id, $ajoitukset) {
		if (!is_null($ajoitukset)) {
			// remove all selections and add new ones
			DB::table('ark_kohde_ajoitus')->where('ark_kohde_id', $kohde_id)->delete();

			foreach($ajoitukset as $ka) {
				$kohdeajoitus = new KohdeAjoitus();
				$kohdeajoitus->ark_kohde_id = $kohde_id;
				$kohdeajoitus->ajoitus_id = $ka['ajoitus']['id'];
				isset($ka['tarkenne']) ? $kohdeajoitus->ajoitustarkenne_id = $ka['tarkenne']['id'] : null;
				isset($ka['ajoituskriteeri']) ? $kohdeajoitus->ajoituskriteeri = $ka['ajoituskriteeri'] : null;
				$kohdeajoitus->luoja = Auth::user()->id;
				$kohdeajoitus->save();
			}
		}
	}

	public function ajoitus() {
		return $this->hasOne('App\Ark\Ajoitus', 'id', 'ajoitus_id');
	}

	public function tarkenne() {
		return $this->hasOne('App\Ark\Ajoitustarkenne', 'id', 'ajoitustarkenne_id');
	}
}
