<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventointijulkaisu extends Model {

	use SoftDeletes;

	protected $table = "inventointijulkaisu";

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
			'nimi',
			'kuvaus',
	        'kentat',
	        'kunta_id',
	        'kyla_id'
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


	public function inventointiprojektit() {
		return $this->belongsToMany('App\Rak\Inventointiprojekti');
	}

	//entiteetti_tyyppi
	public function tasot() {
		return $this->belongsToMany('App\Entiteettityyppi', 'inventointijulkaisu_taso', 'inventointijulkaisu_id', 'entiteetti_tyyppi_id');
	}

	public static function getAll($order_field, $order_direction) {
		$qry = self::select('inventointijulkaisu.*');

		if (is_null($order_direction) || $order_direction=="") {
			$order_direction = "asc";
		}

		return $qry->orderBy('inventointijulkaisu.nimi', $order_direction);
	}



	/**
	 * Method to get single entity from db with given ID
	 *
	 * @param int $id
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getSingle($id) {
		return self::select('*')->where('id', '=', $id);
	}

	/**
	 * Limit result to given rows only
	 *
	 * @param $query
	 * @param int $start_row
	 * @param int $row_count
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithLimit($query, $start_row, $row_count) {
		return $query->skip($start_row)->take($row_count);
	}


	/**
	 * Limit results to entities with name matching the given keyword
	 *
	 * @param $query
	 * @param String $keyword
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithName($query, $keyword) {
		return $query->where('nimi', 'ILIKE', '%'.$keyword.'%');
	}

	public function luoja() {
		return $this->belongsTo('App\Kayttaja', 'luoja');
	}

	public function muokkaaja() {
		return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}

	public function poistaja() {
		return $this->belongsTo('App\Kayttaja', 'poistaja');
	}

	public function kunnatkylat() {
	    return $this->hasMany('App\Rak\InventointijulkaisuKuntakyla', 'inventointijulkaisu_id');
	}
}
