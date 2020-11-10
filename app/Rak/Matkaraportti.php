<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Matkaraportti extends Model
{
	
	use SoftDeletes;
	
	protected $table = "matkaraportti";
	
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
			'tehtavan_kuvaus',
			'huomautukset',
			'matkapvm',
			'kiinteisto_id'
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
	
	
	public function syyt() {
		return $this->belongsToMany('App\Rak\MatkaraportinSyy', 'matkaraportti_syy', 'matkaraportti_id', 'matkaraportinsyy_id');
	}
	
	public function kiinteisto() {
		return $this->belongsTo('App\Rak\Kiinteisto')->withTrashed();
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
	
	
	public static function getAll($order_field, $order_direction) {
		$qry = self::select('matkaraportti.*');
		
		if (is_null($order_direction) || $order_direction=="") {
			$order_direction = "asc";
		}
		
		return $qry->orderBy('matkaraportti.id', $order_direction);
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
	
	public function scopeWithUserFirstName($query, $keyword) {
		return $query->whereIn('matkaraportti.id', function($q) use ($keyword) {
			$q->select('matkaraportti.id')
				->from('matkaraportti')
				->join('kayttaja', 'kayttaja.id', '=', 'matkaraportti.luoja')
				->where('kayttaja.etunimi', 'ILIKE', "%".$keyword."%");
		});				
	}
	
	public function scopeWithUserLastName($query, $keyword) {
		return $query->whereIn('matkaraportti.id', function($q) use ($keyword) {
			$q->select('matkaraportti.id')
			->from('matkaraportti')
			->join('kayttaja', 'kayttaja.id', '=', 'matkaraportti.luoja')
			->where('kayttaja.sukunimi', 'ILIKE', "%".$keyword."%");
		});
	}	
	
	public function scopeWithPalstanumero($query, $keyword) {
	    return $query->whereIn('matkaraportti.id', function($q) use ($keyword) {
	        $q->select('matkaraportti.id')
	        ->from('matkaraportti')
	        ->join('kiinteisto', 'kiinteisto.id', '=', 'matkaraportti.kiinteisto_id')
	        ->where('kiinteisto.palstanumero', '=', $keyword);
	    });
	}
	
	public function scopeWithKiinteistotunnus($query, $keyword) {
		return $query->whereIn('matkaraportti.id', function($q) use ($keyword) {
			$q->select('matkaraportti.id')
			->from('matkaraportti')
			->join('kiinteisto', 'kiinteisto.id', '=', 'matkaraportti.kiinteisto_id')
			->where('kiinteisto.kiinteistotunnus', 'ILIKE', "%".$keyword."%");
		});
	}
		
	public function scopeWithKiinteistonimi($query, $keyword) {
		return $query->whereIn('matkaraportti.id', function($q) use ($keyword) {
			$q->select('matkaraportti.id')
			->from('matkaraportti')
			->join('kiinteisto', 'kiinteisto.id', '=', 'matkaraportti.kiinteisto_id')
			->where('kiinteisto.nimi', 'ILIKE', "%".$keyword."%");
		});
	}
	
	public function scopeWithSyyId($query, $keyword) {
		// lets create a subquery
		$keyword = explode(',' , $keyword);
		return $query->whereIn('matkaraportti.id', function($q) use ($keyword){
			$q->select('matkaraportti_id')
			->from('matkaraportti_syy')
			->join('matkaraportinsyy', 'matkaraportinsyy.id', '=', 'matkaraportti_syy.matkaraportinsyy_id')
			->whereNull('matkaraportinsyy.poistettu')
			->where(function($query) use ($keyword) {
				return $query->whereIn('matkaraportinsyy.id', $keyword);
			});
		});
	}
	
	public function scopeWithMatkapvmAloitus($query, $date) {
		return $query->where('matkapvm', '>=', $date);	
	}
		
	public function scopeWithMatkapvmLopetus($query, $date) {
		return $query->where('matkapvm', '<=', $date);
	}
	
	/**
	 * Limit results to entities with name matching the given keyword
	 *
	 * @param $query
	 * @param String $keyword
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithKayttajaId($query, $keyword) {
		return $query->where('luoja', '=', $keyword);
	}
	
	public function scopeWithKiinteistoId($query, $keyword) {
		return $query->where('kiinteisto_id', '=', $keyword);
	}	
	
	public function scopeWithLuoja($query, $luojaId) {
		return $query->where('matkaraportti.luoja', '=', $luojaId);
	}
	
	
}
