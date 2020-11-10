<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kunta extends Model {

	use SoftDeletes;

	protected $table = "kunta";

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = [];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	*/
	protected $fillable = [
			'kuntanumero',
			'nimi',
			'nimi_se',
	];

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


	/**
	 * Get all Models from DB - order by given $order_field to given $order_direction
	 *
	 * @param int $order_field field name we want to order results by
	 * @param int $order_direction Direction we want to order results to (ASC/DESC)
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getAll($order_field, $order_direction) {
		$qry = self::select('kunta.*');

		if (is_null($order_direction) || $order_direction=="") {
			$order_direction = "asc";
		}
		if ($order_field=="nimi") {
			return $qry->orderBy('kunta.nimi', $order_direction);
		}
		if ($order_field=="kuntanumero") {
			return $qry->orderBy('kunta.kuntanumero', $order_direction);
		}

		return $qry->orderBy('kunta.nimi', $order_direction);
	}

	/**
	 * Method to get single entity from db with given ID
	 *
	 * @param int $id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getSingle($id) {
		return self::select('*')
			->where('id', '=', $id);
	}

	/**
	 * Method to get the areas of given municipality
	 *
	 * @param int $municipality_id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function areas($municipality_id) {

		return self::select('alue.*, kyla.*')
			->join('kyla', 'kyla.kunta_id', '=', 'kunta.id')
			->join('alue_kyla', 'alue_kyla.kyla_id', '=', 'kyla.id')
			->join('alue', 'alue.id', '=', 'alue_kyla.alue_id')
			->groupBy('alue.id')
			->where('kunta.id', '=', $municipality_id);

	}

	/**
	 * Method to get the buildings of given municipality
	 *
	 * @param int $municipality_id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function buildings($municipality_id) {

		return self::select('rakennus.*')
			->join('kyla', 'kyla.kunta_id', '=', 'kunta.id')
			->join('kiinteisto', 'kiinteisto.kyla_id', '=', 'kyla.id')
			->join('rakennus', 'rakennus.kiinteisto_id', '=', 'kiinteisto.id')
			->groupBy('rakennus.id')
			->where('kunta.id', '=', $municipality_id);
	}

	/**
	 * Method to get the estates of given municipality
	 *
	 * @param int $municipality_id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function estates($municipality_id) {
		return self::select('kiinteisto.*')
			->join('kyla', 'kyla.kunta_id', '=', 'kunta.id')
			->join('kiinteisto', 'kiinteisto.kyla_id', '=', 'kyla.id')
			->groupBy('kiinteisto.id')
			->where('kunta.id', '=', $municipality_id);
	}

	public function villages() {
		return $this->hasMany('App\Kyla', 'kunta_id', 'id');
	}

	/**
	 * Limit result to given rows only
	 *
	 * @param $query
	 * @param int $start_row
	 * @param int $row_count
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithLimit($query, $start_row, $row_count) {
		return $query->skip($start_row)->take($row_count);
	}

	/**
	 * Limit the results by given municipality number
	 *
	 * @param $municipality_nuber
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithMunicipalityNumber($query, $municipality_number) {
		if(strlen($municipality_number) == 3) {
			return $query->where('kuntanumero', '=', $municipality_number);
		} else {
			return $query->where('kuntanumero', 'LIKE', $municipality_number."%");
		}
	}

	/**
	 * Limit the results by given municipality name
	 *
	 * @param $municipality_name
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithMunicipalityName($query, $municipality_name) {
		return $query->where('nimi', 'ILIKE', $municipality_name."%");
			//->orWhere('nimi_se', 'ILIKE', $municipality_name."%");
	}

	public function scopeWithLuoja($query, $luojaId) {
		return $query->where('kunta.luoja', '=', $luojaId);
	}

	public function scopeWithId($query, $id) {
	    return $query->where('kunta.id', '=', $id);
	}

}
