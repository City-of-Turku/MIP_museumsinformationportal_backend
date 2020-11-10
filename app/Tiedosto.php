<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tiedosto extends Model {

	use SoftDeletes;

	protected $table = "tiedosto";
	protected $hidden = [
		"tiedosto_id",
		"kiinteisto_id",
		"rakennus_id",
		"nimi",
		"polku",
	];
	protected $fillable = [
		"otsikko",
		"kuvaus",
	];
	public $timestamps = true;

	const CREATED_AT 		= 'luotu';
	const UPDATED_AT 		= 'muokattu';
	const DELETED_AT 		= "poistettu";

	const CREATED_BY		= 'luoja';
	const UPDATED_BY		= 'muokkaaja';
	const DELETED_BY		= 'poistaja';

	public static function getSingle($id) {
		return Tiedosto::select('tiedosto.*')->where('tiedosto.id', '=', $id);
	}

	public function luoja() {
		return $this->belongsTo('App\Kayttaja', 'luoja');
	}

	public function muokkaaja() {
		return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}

	public function scopeWithLimit($query, $start_row, $row_count) {
		return $query->skip($start_row)->take($row_count);
	}

	public function scopeWithEstateID($query, $estateID) {
		return $query->join("tiedosto_kiinteisto", "tiedosto_kiinteisto.tiedosto_id", "=", "tiedosto.id")
		->where("tiedosto_kiinteisto.kiinteisto_id", "=", $estateID);
	}

	public function scopeWithBuildingID($query, $buildingID) {
		return $query->join("tiedosto_rakennus", "tiedosto_rakennus.tiedosto_id", "=", "tiedosto.id")
			->where("tiedosto_rakennus.rakennus_id", "=", $buildingID);
	}

	public function scopeWithAlueID($query, $alueID) {
		return $query->join("tiedosto_alue", "tiedosto_alue.tiedosto_id", "=", "tiedosto.id")
		->where("tiedosto_alue.alue_id", "=", $alueID);
	}

	public function scopeWithArvoalueID($query, $arvoalueID) {
		return $query->join("tiedosto_arvoalue", "tiedosto_arvoalue.tiedosto_id", "=", "tiedosto.id")
		->where("tiedosto_arvoalue.arvoalue_id", "=", $arvoalueID);
	}

	public function scopeWithPorrashuoneID($query, $porrashuoneID) {
		return $query->join("tiedosto_porrashuone", "tiedosto_porrashuone.tiedosto_id", "=", "tiedosto.id")
		->where("tiedosto_porrashuone.porrashuone_id", "=", $porrashuoneID);
	}

	public function scopeWithKuntaID($query, $kuntaID) {
		return $query->join("tiedosto_kunta", "tiedosto_kunta.tiedosto_id", "=", "tiedosto.id")
		->where("tiedosto_kunta.kunta_id", "=", $kuntaID);
	}

	public function scopeWithSuunnittelijaID($query, $suunnittelijaID) {
	    return $query->join("tiedosto_suunnittelija", "tiedosto_suunnittelija.tiedosto_id", "=", "tiedosto.id")
	    ->where("tiedosto_suunnittelija.suunnittelija_id", "=", $suunnittelijaID);
	}
}
