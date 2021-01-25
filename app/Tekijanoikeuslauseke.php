<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tekijanoikeuslauseke extends Model {

	use SoftDeletes;

	protected $table = "tekijanoikeuslauseke";

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
			'lauseke',
			'osio',
	        'otsikko'
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

	public function poistaja() {
		return $this->belongsTo('App\Kayttaja', 'poistaja');
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
		$qry = self::select('tekijanoikeuslauseke.*');
		if (is_null($order_direction) || $order_direction=="") {
			$order_direction = "asc";
		}
		if ($order_field=="lauseke") {
			return $qry->orderBy('tekijanoikeuslauseke.lauseke', $order_direction);
		}
		if ($order_field=="osio") {
			return $qry->orderBy('tekijanoikeuslauseke.osio', $order_direction);
		}
		//default
		return $qry->orderBy('tekijanoikeuslauseke.id', $order_direction);
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
	public static function scopeWithOsio($query, $osio) {
	    return $query->where('tekijanoikeuslauseke.osio', '=', '')->orWhereNull('tekijanoikeuslauseke.osio')->orWhere('tekijanoikeuslauseke.osio', 'ilike', $osio);
	}
}
