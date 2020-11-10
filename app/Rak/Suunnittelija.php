<?php

namespace App\Rak;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App;

class Suunnittelija extends Model {

	use SoftDeletes;

	protected $table = "suunnittelija";

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = [
		"rakentajan_nimi"
	];

	//protected $casts = ['id' => 'integer', 'suunnittelija_ammattiarvo_id' => 'integer', 'suunnittelija_laji_id' => 'integer'];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	*/
	protected $fillable = [
			'etunimi',
			'sukunimi',
			'suunnittelija_ammattiarvo_id',
			'kuvaus',
			'suunnittelija_laji_id'
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
	 * Get all Models from DB - order by given $order_field to given $order_direction
	 *
	 * @param int $order_field field name we want to order results by
	 * @param int $order_direction Direction we want to order results to (ASC/DESC)
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getAll() {
		return self::select('suunnittelija.id', 'suunnittelija.sukunimi', 'suunnittelija.etunimi', 'suunnittelija.kuvaus', 'suunnittelija.luoja',
							'suunnittelija.suunnittelija_laji_id', 'suunnittelija.suunnittelija_ammattiarvo_id')->addSelect(DB::raw("CONCAT(sukunimi,' ', etunimi) as kokonimi"))
			->leftJoin('suunnittelija_laji', 'suunnittelija.suunnittelija_laji_id', '=', 'suunnittelija_laji.id')
			->leftJoin('suunnittelija_ammattiarvo', 'suunnittelija.suunnittelija_ammattiarvo_id', '=', 'suunnittelija_ammattiarvo.id');
	}

	public function scopeWithOrderBy($query, $order_field=null, $order_direction=null) {
	    if ($order_field == "nimi") {
	        return $query->orderBy("suunnittelija.sukunimi", $order_direction)->orderBy("suunnittelija.etunimi", $order_direction);
	    } elseif($order_field == 'ammattiarvo') {
	        return $query->orderBy("suunnittelija_ammattiarvo.".self::getLocalizedfieldname('nimi'), $order_direction);
	    } else {
	        return $query->orderBy($order_field, $order_direction);
	    }
	}

	public static function getLocalizedfieldname($field_name) {

		if (App::getLocale()=="fi") {
			return $field_name."_fi";
		}
		if (App::getLocale()=="en") {
			return $field_name."_en";
		}
		if (App::getLocale()=="se") {
			return $field_name."_se";
		}
		return $field_name."_fi";
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
		return self::select('*')->where('id', '=', $id);
	}

	/**
	 * Limit result to given rows only
	 *
	 * @param  $query
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
	 * Limit results to entities with FIRSTNAME matching the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithFirstName($query, $keyword) {
		return $query->where("etunimi", 'ILIKE', '%'.$keyword.'%');
	}

	/**
	 * Limit results to entities with LASTNAME matching the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithLastName($query, $keyword) {
		return $query->where("sukunimi", 'ILIKE', '%'.$keyword.'%');
	}


	public function scopeWithName($query, $keyword) {
		$query->where('sukunimi', 'ILIKE', "%".$keyword."%")
		->orWhere('etunimi', 'ILIKE', "%".$keyword."%");

		return $query;

	}


	/**
	 * Limit results to entities with PROFESSION matching the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithProfessionId($query, $keyword) {
		    $keyword = explode(',' , $keyword);
		    return $query->whereIn('suunnittelija.suunnittelija_ammattiarvo_id', function($q) use ($keyword) {
		        $q->select('id')
		        ->from('suunnittelija_ammattiarvo')
		        ->whereNull('suunnittelija_ammattiarvo.poistettu')
		        ->where(function($query) use ($keyword) {
		            return $query->whereIn('suunnittelija_ammattiarvo.id', $keyword);
		        });
		    });
		//return $query->where("ammatti_arvo", 'ILIKE', '%'.$keyword.'%');
	}

	/**
	 * Limit results to entities with LAJI matching the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithLajiId($query, $keyword) {
		    $keyword = explode(',' , $keyword);
		    return $query->whereIn('suunnittelija.suunnittelija_laji_id', function($q) use ($keyword) {
		        $q->select('id')
		        ->from('suunnittelija_laji')
		        ->whereNull('suunnittelija_laji.poistettu')
		        ->where(function($query) use ($keyword) {
		            return $query->whereIn('suunnittelija_laji.id', $keyword);
		        });
		    });
			//return $query->where("ammatti_arvo", 'ILIKE', '%'.$keyword.'%');
	}

	/**
	 * Limit results to entities with given ID
	 *
	 * @param  $query
	 * @param int $id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithID($query, $id) {
		return $query->where('suunnittelija.id', '=', $id);
	}

	/**
	 * Method to get the kinds of suunnittelija
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function laji() {
		return $this->belongsTo('App\Rak\SuunnittelijaLaji', 'suunnittelija_laji_id');
	}

	/**
	 * Method to get the professions of suunnittelija
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function ammattiarvo() {
		return $this->belongsTo('App\Rak\SuunnittelijaAmmattiarvo', 'suunnittelija_ammattiarvo_id');
	}


	public function rakennukset() {
		return $this->belongsToMany('App\Rak\Rakennus', 'suunnittelija_rakennus')
			->withPivot('lisatieto', 'suunnitteluvuosi_alku', 'suunnitteluvuosi_loppu', 'suunnittelija_tyyppi_id')
			->addSelect('*')
			->addSelect(DB::raw(MipGis::getGeometryFieldQueryString("rakennuksen_sijainti", "sijainti")));
	}

	/**
	 * Kuvat
	 *
	 */
	public function images() {
	    return $this->belongsToMany('App\Rak\Kuva', 'kuva_suunnittelija');
	}
	/**
	 * Tiedostot
	 */
	public function files() {
	    return $this->belongsToMany('App\Tiedosto', 'tiedosto_suunnittelija');
	}

	public function scopeWithLuoja($query, $luojaId) {
		return $query->where('suunnittelija.luoja', '=', $luojaId);
	}
}
