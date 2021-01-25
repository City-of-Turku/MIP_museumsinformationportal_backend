<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class Reitti extends Model {

	use SoftDeletes;

	protected $table = "reitti";

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = ['reitti'];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	*/
	protected $fillable = [
    	'kuvaus', // Not used currently, but maybe later on
    	'entiteetti_tyyppi', // Tyyppi johon reitti liittyy
    	'entiteetti_id', // Entiteetin id johon reitti liittyy
      'reitti' // Fyysinen sijainti pisteitä
	];

	protected $appends = array();

	/**
	 * By default, Eloquent will maintain the created_at and updated_at columns on your database table automatically.
	 * Simply add these timestamp columns to your table and Eloquent will take care of the rest.
	*/
	public $timestamps = true;

	const CREATED_AT 		= 'luotu';
	const UPDATED_AT 		= 'muokattu';
	const DELETED_AT 		= "poistettu";

	const CREATED_BY		= 'luoja';
	const UPDATED_BY		= 'muokkaaja';
	const DELETED_BY		= 'poistaja';

	public function luoja() {
		return $this->belongsTo('App\Kayttaja', 'luoja');
	}

	public function muokkaaja() {
		return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}

	public function files() {
		return $this->belongsToMany('App\Tiedosto', 'tiedosto_kunta');
	}

	public function scopeWithLimit($query, $start_row, $row_count) {
	    return $query->skip($start_row)->take($row_count);
	}

	public static function getAll() {
	    return self::select('reitti.*');
	}

	public static function getAllByEntiteettiTyyppiAndId($entiteettiTyyppi, $entiteettiId) {
		return self::select('*')
			->where('entiteetti_tyyppi', '=', $entiteettiTyyppi)->where('entiteetti_id', '=', $entiteettiId)
		    ->orderBy('luotu', 'asc');
	}

	public function scopeWithLuoja($query, $luojaId) {
		return $query->where('reitti.luoja', '=', $luojaId);
	}

	public function getGeometryAttribute() {
        // Palauttaa listan, jonka ensimmäinen rivi on haluamamme rivi
	    $res = DB::select(DB::raw("select ST_AsGeoJSON(ST_transform(reitti.reitti, ".Config::get('app.json_srid').")) from reitti where reitti.id = :id", ["id" => $this->id]), [$this->id]);

	    $geom = null;
	    // Otetaan geometria
	    foreach($res[0] as $key => $val) {
	        $geom = $val;
	    }

	    return $geom;
	}

}
