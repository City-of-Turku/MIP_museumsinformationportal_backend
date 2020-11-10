<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App;

class Kyla extends Model {

	use SoftDeletes;

	protected $table = "kyla";

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = ['pivot'];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	*/
	protected $fillable = [
			'id',
			'kunta_id',
			'kylanumero',
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

	/**
	 * Method to get the Municipality of THIS Village
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function kunta() {
		return $this->belongsTo('App\Kunta');
	}

	public function images() {
		return $this->belongsToMany('App\Rak\Kuva', 'kuva_kyla');
	}

	/** Get all Models from DB - order by given $order_field to given $order_direction
	 *
	 * @param int $order_field field name we want to order results by
	 * @param int $order_direction Direction we want to order results to (ASC/DESC)
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getAll($order_field, $order_direction) {
		$qry = self::select('kyla.*')
			->leftJoin('kunta', 'kyla.kunta_id', '=', 'kunta.id');

		if (is_null($order_direction) || $order_direction=="") {
			$order_direction = "asc";
		}
		if ($order_field=="nimi") {
			return $qry->orderBy('kyla.nimi', $order_direction);
		}
		if ($order_field=="kylanumero") {
			return $qry->orderBy('kyla.kylanumero', $order_direction);
		}
		if ($order_field=="kuntanimi") {
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
	public function scopeWithVillageNumber($query, $keyword) {
		return $query->where('kylanumero', '=', $keyword);
	}

	/**
	 * Limit the results by given municipality name
	 *
	 * @param $municipality_name
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithVillageName($query, $keyword) {
		return $query->where('kyla.nimi', 'ILIKE', $keyword."%");
			//->orWhere('kyla.nimi_se', 'ILIKE', "%" . $keyword."%");
	}

	public function scopeWithKuntanumero($query, $keyword) {
		return $query->where('kunta.kuntanumero', '=', $keyword);
	}

	public function scopeWithKuntanimi($query, $keyword) {
	    if(App::getLocale()=="se"){
	        return $query->where('kunta.nimi_se', 'ILIKE', $keyword . "%")
	        ->orWhere('kunta.nimi', 'ILIKE', $keyword . "%");
	    }
	    return $query->where('kunta.nimi', 'ILIKE', $keyword . "%");
	    //->orWhere('kunta.nimi_se', 'ILIKE', $keyword . "%");
	}


	/**
	 * Limit the results by given municipality ID
	 *
	 * @param int $municipality_id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithMunicipalityID($query, $keyword) {
		return $query->where('kunta_id', '=', $keyword);
	}
	public function scopeWithMunicipalityIDs($query, $idArray) {
		return $query->whereIn('kunta_id', $idArray);
	}

	public function scopeWithId($query, $id) {
	    return $query->where('kyla.id', '=', $id);
	}

	/**
	 * Method to get the estates of given kyla
	 *
	 * @param int $municipality_id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function estates($kyla_id) {
		return self::select('kiinteisto.*')
		->join('kiinteisto', 'kiinteisto.kyla_id', '=', 'kyla.id')
		->groupBy('kiinteisto.id')
		->where('kyla.id', '=', $kyla_id);
	}

	public function scopeWithLuoja($query, $luojaId) {
		return $query->where('kyla.luoja', '=', $luojaId);
	}

}
