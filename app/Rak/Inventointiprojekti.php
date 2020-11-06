<?php

namespace App\Rak;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App;

class Inventointiprojekti extends Model {
	
	use SoftDeletes;
	
	protected $table = "inventointiprojekti";
	
	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = ['inventorers','municipalities'];
	
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
			'nimi',
			'kuvaus',
			'inventointiaika',
			'toimeksiantaja',
			'tyyppi_id',
			'laji_id',
			'inventointitunnus'
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
	
	
	/**
	 * Method to get the inventointiprojektityyppi
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function inventointiprojektityyppi() {
		return $this->belongsTo('App\Rak\InventointiprojektiTyyppi', 'tyyppi_id');
	}
	
	/**
	 * Method to get the inventointiprojektilaji
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function inventointiprojektilaji() {
		return $this->belongsTo('App\Rak\InventointiProjektiLaji', 'laji_id');
	}
	
	/**
	 * Get the areas of inventoring project
	 * 
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @version 1.0
	 * @since 1.0
	 */
	public function alueet() {
		return $this->belongsToMany('App\Rak\Alue', 'inventointiprojekti_alue', 'inventointiprojekti_id', 'alue_id')
			->addSelect('*', 
						DB::raw(MipGis::getGeometryFieldQueryString("alue.keskipiste", "sijainti")),
						DB::raw(MipGis::getGeometryFieldQueryString("alue.aluerajaus", "alue")))
			->withPivot('inventoija_id', 'inventoija_nimi', 'inventoija_arvo', 'inventoija_organisaatio');
	}

	
	/**
	 * Get the inventorers of inventoring project
	 * 
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 * @version 1.0
	 * @since 1.0
	 */
	public function inventoijat() {
		return $this->belongsToMany('App\Kayttaja', 'inventointiprojekti_inventoija', 'inventointiprojekti_id', 'inventoija_id')
		->withPivot('inventoija_arvo','inventoija_organisaatio');
	}
	
	public function kunnat() {
		return $this->belongsToMany('App\Kunta', 'inventointiprojekti_kunta', 'inventointiprojekti_id', 'kunta_id');
	}
	
	/**
	 * Get the valueareas of inventoring project
	 * 
	 * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
	 * @version 1.0
	 * @since 1.0
	 */
	public function arvoalueet() {
		return $this->belongsToMany('App\Rak\Arvoalue', 'inventointiprojekti_arvoalue', 'inventointiprojekti_id', 'arvoalue_id')
			->addSelect('arvoalue.*',
				DB::raw(MipGis::getGeometryFieldQueryString("arvoalue.keskipiste", "sijainti")),
				DB::raw(MipGis::getGeometryFieldQueryString("arvoalue.aluerajaus", "aluerajaus")))
				->withPivot('inventoija_id', 'inventoija_nimi', 'inventoija_arvo', 'inventoija_organisaatio');
	}
		
	/**
	 * Get the villages of given inventoring project 
	 * @param int $inventoringproject_id
	 * @version 1.0
	 * @since 1.0
	 */
	public static function villages($inventoringproject_id) {
		
		return self::select('kyla.id', 'kunta.nimi as kunta_nimi', 'kyla.nimi as kyla_nimi', 'kyla.kylanumero as kyla_numero',
				'kyla.luoja', 'kyla.muokkaaja', 'kyla.poistaja',
				'kyla.luotu', 'kyla.muokattu', 'kyla.poistettu')
		
			// join to kyla via inventointiprojekti_kiinteisto
			->leftjoin('inventointiprojekti_kiinteisto', 'inventointiprojekti_kiinteisto.inventointiprojekti_id', '=', 'inventointiprojekti.id')
			->leftjoin('kiinteisto', 'kiinteisto.id', '=', 'inventointiprojekti_kiinteisto.kiinteisto_id')
			->leftjoin('kyla', 'kyla.id', '=', 'kiinteisto.kyla_id')
			
			// join to kyla via inventointiprojekti_arvoalue
			->leftjoin('inventointiprojekti_arvoalue', 'inventointiprojekti_arvoalue.inventointiprojekti_id', '=', 'inventointiprojekti.id')
			->leftjoin('arvoalue', 'arvoalue.id', '=', 'inventointiprojekti_arvoalue.arvoalue_id')
			
			// join to kyla via inventointiprojekti_alue 
			->leftjoin('inventointiprojekti_alue', 'inventointiprojekti_alue.inventointiprojekti_id', '=', 'inventointiprojekti.id')
			->leftjoin('alue', 'alue.id', '=', 'inventointiprojekti_alue.alue_id')
			
			->join('alue_kyla', function($join){
				$join->on('kyla.id', '=', 'alue_kyla.kyla_id')
					->orOn('arvoalue.alue_id', '=', 'alue_kyla.alue_id')
					->orOn('alue.id', '=', 'alue_kyla.alue_id');
			})
			->join('kunta', 'kunta.id', '=', 'kyla.kunta_id')
			->where('inventointiprojekti.id', '=', $inventoringproject_id)
			->groupBy('kyla.id', 'kunta.id', 'inventointiprojekti.id');
	}
	
	/**
	 * Get the estates of inventoring project
	 * 
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 * @version 1.0
	 * @since 1.0
	 */
	public function estates() {
		
		return $this->belongsToMany('App\Rak\Kiinteisto', 'inventointiprojekti_kiinteisto', 'inventointiprojekti_id', 'kiinteisto_id')
			->addSelect('kiinteisto.*', DB::raw(MipGis::getGeometryFieldQueryString("kiinteiston_sijainti", "sijainti")))
			->withPivot('inventointiprojekti_id', 'kiinteisto_id', 'inventointipaiva', 
					'inventoija_nimi', 'inventointipaiva_tekstina', 'kenttapaiva', 'inventoija_id')
			;
/*
			->select('kiinteisto.kiinteistotunnus as kiinteisto_kiinteistotunnus','kiinteisto.nimi as kiinteisto_nimi',
					'kiinteisto.luotu', 'kiinteisto.muokattu', 'kiinteisto.poistettu', 
					'kiinteisto.luoja', 'kiinteisto.muokkaaja', 'kiinteisto.poistaja',
					DB::raw(MipGis::getGeometryFieldQueryString("kiinteiston_sijainti", "sijainti")
			)
		
		);
		 */
	}
	
	/**
	 * Get the buildings related to given inventoring project
	 * 
	 * @param $inventoringproject_id
	 * @version 1.0
	 * @since 1.0
	 */
	public function buildingsByInventoringProjectID($inventoringproject_id) {
		
		$qry = DB::table("inventointiprojekti_kiinteisto")->select('rakennus.*',
				DB::raw(MipGis::getGeometryFieldQueryString("rakennuksen_sijainti", "sijainti"))
			)
			->where('inventointiprojekti_kiinteisto.inventointiprojekti_id', '=', $inventoringproject_id)
			->join('kiinteisto', 'kiinteisto.id', '=', 'inventointiprojekti_kiinteisto.kiinteisto_id')
			->join('rakennus', 'rakennus.kiinteisto_id', '=', 'inventointiprojekti_kiinteisto.kiinteisto_id')
			->orderBy('kiinteisto.id', 'ASC');
		
			//If the user role is katselija, get only rakennukset that belong to public kiinteistot
			if(Auth::user()->rooli == 'katselija') {
				$qry = $qry->where('kiinteisto.julkinen', '=', true);
			}
			
			return $qry;
	}
	
	/**
	 * Get all Models from DB - order by given $order_field to given $order_direction
	 *
	 * @param int $order_field field name we want to order results by
	 * @param int $order_direction Direction we want to order results to (ASC/DESC)
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getAll($order_field, $order_direction) {
	
		$inv_ajanjaksot_sql = "( select inventointiprojekti_id, ";
		$inv_ajanjaksot_sql .= "   min(alkupvm) as ajanjaksot ";
		$inv_ajanjaksot_sql .= "   from inventointiprojekti_ajanjakso ";
		$inv_ajanjaksot_sql .= "   group by inventointiprojekti_id ";
		$inv_ajanjaksot_sql .= ") as inv_ajanjaksot";
		
		$qry = self::select(
				'inventointiprojekti.id', 'inventointiprojekti.nimi', 'inventointiprojekti.toimeksiantaja',	'inventointiprojekti.luoja',			
				'inv_ajanjaksot.ajanjaksot', 'inventointiprojekti.tyyppi_id', 'inventointiprojekti.laji_id'
			)
			->leftJoin('inventointiprojektityyppi', 'inventointiprojekti.tyyppi_id', '=', 'inventointiprojektityyppi.id')
			->leftJoin('inventointiprojekti_laji', 'inventointiprojekti.laji_id', '=', 'inventointiprojekti_laji.id')
			->leftJoin(DB::raw($inv_ajanjaksot_sql), 'inventointiprojekti.id', '=', 'inv_ajanjaksot.inventointiprojekti_id');
			
			return $qry;
		
		
		//return self::select('*')->orderBy($order_field, $order_direction);
	}
	
	// use only with all() above
	public function scopeWithOrder($query, $order_field=null, $order_direction=null) {
				
		if ($order_field == "inventointiprojektityyppi") {
			return $query->orderBy('inventointiprojektityyppi.'.self::getLocalizedfieldname('nimi'), $order_direction);
		}
		if ($order_field == "inventointiprojektilaji") {
			return $query->orderBy('inventointiprojekti_laji.'.self::getLocalizedfieldname('nimi'), $order_direction);
		}
		if ($order_field == "nimi") {
			return $query->orderBy('inventointiprojekti.nimi', $order_direction);
		}
		if ($order_field == "toimeksiantaja") {
			return $query->orderBy('inventointiprojekti.toimeksiantaja', $order_direction);
		}
		if ($order_field == "inventointiaika") {
			return $query->orderBy('inv_ajanjaksot.ajanjaksot', $order_direction);
		}
	
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
	
	public function scopeWithInventointiAloitusaika($query, $startdate) {
		return $query->whereIn('inventointiprojekti.id', function($q) use ($startdate) {
			
			$q->select('inventointiprojekti_id')
				->from('inventointiprojekti_ajanjakso')				
				->where('inventointiprojekti_ajanjakso.alkupvm', '>=', $startdate)
				->orWhere('inventointiprojekti_ajanjakso.alkupvm', '=', null);
		});
	}
	
	public function scopeWithExcludeTechProjects($query) {
		return $query->whereIn('inventointiprojekti.laji_id', function($q) {
			$q->select('id')
			->from('inventointiprojekti_laji')
			->where('tekninen_projekti', '=', false);
		});
	}
	
	public function scopeWithInventointiLopetusaika($query, $enddate) {
		return $query->whereIn('inventointiprojekti.id', function($q) use ($enddate) {
			
			$q->select('inventointiprojekti_id')
			->from('inventointiprojekti_ajanjakso')
			->where('inventointiprojekti_ajanjakso.loppupvm', '<=', $enddate)
			->orWhere('inventointiprojekti_ajanjakso.loppupvm', '=', null);
		});
	}
	
	public function scopeWithToimeksiantaja($query, $toimeksiantaja) {
		return $query->where('toimeksiantaja', 'ILIKE', "%" . $toimeksiantaja . '%');
	}
	
	public function scopeWithTyyppiId($query, $keyword) {
		    $keyword = explode(',' , $keyword);
		    return $query->whereIn('inventointiprojektityyppi.id', function($q) use ($keyword) {
		        $q->select('id')
		        ->from('inventointiprojektityyppi')
		        ->whereNull('inventointiprojektityyppi.poistettu')
		        ->where(function($query) use ($keyword) {
		            return $query->whereIn('inventointiprojektityyppi.id', $keyword);
		        });
		    });
	} 

	public function scopeWithLajiId($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('inventointiprojekti.laji_id', function($q) use ($keyword) {
			$q->select('id')
			->from('inventointiprojekti_laji')
			->whereNull('inventointiprojekti_laji.poistettu')
			->where(function($query) use ($keyword) {
				return $query->whereIn('inventointiprojekti_laji.id', $keyword);
			});
		});
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
	
	public function scopeWithId($query, $keyword) {
		return $query->where('inventointiprojekti.id', '=', $keyword);
	}
	
	public function ajanjakso() {
		return $this->hasMany('App\Rak\InventointiprojektiAjanjakso');
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
	
	public function scopeWithLuoja($query, $luojaId) {
		return $query->where('inventointiprojekti.luoja', '=', $luojaId);
	}
}
