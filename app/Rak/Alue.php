<?php

namespace App\Rak;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App;

class Alue extends Model {

	use SoftDeletes;


	protected $table = "alue";

	public $timestamps = true;

	const CREATED_AT 		= 'luotu';
	const UPDATED_AT 		= 'muokattu';
	const DELETED_AT 		= "poistettu";

	const CREATED_BY		= 'luoja';
	const UPDATED_BY		= 'muokkaaja';
	const DELETED_BY		= 'poistaja';

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = [
		'aluerajaus','keskipiste'
	];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	*/
	protected $fillable = [
		'nimi',
		'maisema',
	    'paikkakunta',
		'historia',
		'nykytila',
		'lisatiedot',
		'lahteet',
		'keskipiste',
		'aluerajaus',
	    'arkeologinen_kohde'
	];

	public function files() {
		return $this->belongsToMany('App\Tiedosto', 'tiedosto_alue');
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

	public function images() {
		return $this->belongsToMany('App\Rak\Kuva', 'kuva_alue');
	}


	/**
	 * Method to list the Villages of this area
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function kylat() {
		return $this->belongsToMany('App\Kyla');
	}

	 /*
     * Inventointiprojektit joissa kiinteistö on inventoituna.
     * PALAUTETAAN AINOASTAAN kertaalleen jokainen projekti
     */
    public function inventointiprojektit() {
        return $this->belongsToMany('App\Rak\Inventointiprojekti', 'inventointiprojekti_alue')
        	->whereNull('inventointiprojekti_alue.poistettu')
        	->whereIn('inventointiprojekti_alue.inventointiprojekti_id', function($q) {
        		$q->select('id')->from('inventointiprojekti')
        		->whereIn('inventointiprojekti.laji_id', function($q) {
        			$q->select('id')
        			->from('inventointiprojekti_laji')
        			->where('tekninen_projekti', '=', false);
        		});
        	})
    		->groupBy(['inventointiprojekti_alue.inventointiprojekti_id', 'inventointiprojekti.id', 'inventointiprojekti_alue.alue_id'])
    		->orderBy('inventointiprojekti_alue.inventointiprojekti_id');
    }

    /*
     * Palauttaa kyseisen entiteetin inventoineet käyttäjät
     */
    public function inventoijat_str() {
    	return $this->belongsToMany('App\Kayttaja', 'inventointiprojekti_alue', 'alue_id', 'inventoija_id')
    	->whereNull('kayttaja.poistettu')
    	->whereNull('inventointiprojekti_alue.poistettu')
    	->whereIn('inventointiprojekti_alue.inventointiprojekti_id', function($q) {
    		$q->select('id')->from('inventointiprojekti')
    		->whereIn('inventointiprojekti.laji_id', function($q) {
    			$q->select('id')
    			->from('inventointiprojekti_laji')
    			->where('tekninen_projekti', '=', false);
    		});
    	})
    	->groupBy(['kayttaja.id', 'inventointiprojekti_alue.inventoija_id', 'inventointiprojekti_alue.alue_id']);

    }


    public static function inventoijat($alueId, $inventointiprojektiId) {
    	$inventoijat = DB::select(DB::raw('select inventointiprojekti_alue.id, inventointiprojekti_id, inventointiprojekti.nimi,
							alue_id, kayttaja.etunimi, kayttaja.sukunimi, inventointipaiva, kenttapaiva, inventointiprojekti_alue.inventoija_id
							from inventointiprojekti_alue
							join kayttaja on inventointiprojekti_alue.inventoija_id = kayttaja.id
							join inventointiprojekti on inventointiprojekti_alue.inventointiprojekti_id = inventointiprojekti.id
							where inventointiprojekti_alue.id in (
							    select max(id)
							    from inventointiprojekti_alue
							    where inventointiprojekti_alue.alue_id = ?
								and inventointiprojekti_alue.inventointiprojekti_id = ?
								and inventointiprojekti_alue.poistettu is null
							    group by inventointiprojekti_id, inventoija_id
							)
							and inventointiprojekti_alue.poistettu is null
							order by inventointiprojekti_alue.id'), [$alueId, $inventointiprojektiId]);
    	return $inventoijat;
    }

	/**
	 * Method to list the value areas of THIS Area
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function valueAreas() {
		return $this->hasMany('App\Rak\Arvoalue');
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

		$kylat_sql  = "( select alue_id, ";
		$kylat_sql .= "  string_agg(kyla.nimi, '\n') as kylat ";
		$kylat_sql .= "  from alue_kyla, kyla ";
		$kylat_sql .= "  where alue_kyla.kyla_id = kyla.id ";
		$kylat_sql .= "  group by alue_id ";
		$kylat_sql .= ") as alue_kylat ";

		$kunnat_sql  = "( select alue_id, ";
		$kunnat_sql .= "  string_agg(kunta.nimi, '\n') as kunnat ";
		$kunnat_sql .= "  from alue_kyla, kyla ";
		$kunnat_sql .= "  left join kunta on (kunta.id = kyla.kunta_id) ";
		$kunnat_sql .= "  where alue_kyla.kyla_id = kyla.id ";
		$kunnat_sql .= "  group by alue_id ";
		$kunnat_sql .= ") as alue_kunnat ";

		$order_table = "alue";
		$qry = self::select(
			'alue.id','alue.paikkakunta','alue.nimi as nimi', 'alue.luoja',
			DB::raw(MipGis::getGeometryFieldQueryString("keskipiste", "sijainti")),
			DB::raw(MipGis::getGeometryFieldQueryString("aluerajaus", "alue"))
			)
			->leftJoin(DB::raw($kylat_sql), 'alue.id', '=', 'alue_kylat.alue_id')
			->leftJoin(DB::raw($kunnat_sql), 'alue.id', '=', 'alue_kunnat.alue_id');

		return $qry;
	}

	public function scopeWithOrderBy($query, $bbox=null, $order_field=null, $order_direction=null) {

		if ($order_field == "bbox_center" && !is_null($bbox)) {
			return $query->orderByRaw(MipGis::getGeometryFieldOrderByBoundingBoxAreaAndPointCenterString("aluerajaus", "keskipiste", $bbox));
		}

		if($order_field == "kunta") {
			return $query->orderBy("alue_kunnat.kunnat", $order_direction);
		} else if($order_field == "kyla") {
			return $query->orderBy("alue_kylat.kylat", $order_direction);
		} else if ($order_field == "paikkakunta") {
			return $query->orderBy("alue.paikkakunta", $order_direction);
		}else if ($order_field == "nimi") {
			return $query->orderBy("alue.nimi", $order_direction);
		}

		return $query->orderBy("alue_kunnat.kunnat", $order_direction);
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
		return self::select('alue.*',
			DB::raw(MipGis::getGeometryFieldQueryString("keskipiste", "sijainti")),
			DB::raw(MipGis::getGeometryFieldQueryString("aluerajaus", "alue"))
			)
		->where('alue.id', '=', $id);
	}

	/**
	 * Limit results to only for area of given bounding box
	 *
	 * @param $query
	 * @param String $bbox The bounding box value (21.900000 60.910000,22.000000 61.000000)
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithBoundingBox($query, $bbox) {
		return $query->whereRaw(MipGis::getGeometryFieldBoundingBoxQueryWhereStringFromAreaAndPoint("aluerajaus", "keskipiste", $bbox));
	}

	public function scopeWithPolygon($query, $polygon) {
		return $query->whereRaw(MipGis::getGeometryFieldPolygonQueryWhereString($polygon, "keskipiste", "aluerajaus"));
	}

	public function scopeWithID($query, $id){
		return $query->where('alue.id', '=', $id);
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
	 * Limit results to entities with name matching the given keyword
	 *
	 * @param $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithAreaName($query, $keyword) {
		return $query->where('alue.nimi', 'ILIKE', '%'.$keyword.'%');
	}

	/**
	 * Limit results to only for those rows which PAIKKAKUNTA values matches the given keyword
	 */
	public function scopeWithPaikkakunta($query, $keyword) {
	    return $query->where('alue.paikkakunta', 'ILIKE', "%".$keyword."%");
	}

	public function scopeWithMunicipalityName($query, $keyword) {
			if(App::getLocale()=="se"){
			    return $query->whereIn('alue.id', function($q) use ($keyword){
			        $q->select('AK1.alue_id')
			        ->from('alue_kyla as AK1')
			        ->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			        ->join('kunta as KU1', 'KU1.id', '=', 'K1.kunta_id')
			        ->where('KU1.nimi_se', 'ILIKE', $keyword . "%")
			        ->orWhere('KU1.nimi', 'ILIKE', $keyword . "%")
			        ->whereNull('KU1.poistettu');
			    });
			}
			return $query->whereIn('alue.id', function($q) use ($keyword){
			    $q->select('AK1.alue_id')
			    ->from('alue_kyla as AK1')
			    ->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			    ->join('kunta as KU1', 'KU1.id', '=', 'K1.kunta_id')
			    ->where('KU1.nimi', 'ILIKE', $keyword . "%")
			    ->whereNull('KU1.poistettu');
		});
	}

	public function scopeWithMunicipalityId($query, $keyword) {
		return $query->whereIn('alue.id', function($q) use ($keyword){
			$q->select('AK1.alue_id')
			->from('alue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->join('kunta as KU1', 'KU1.id', '=', 'K1.kunta_id')
			->where('KU1.id', '=', $keyword)
			->whereNull('KU1.poistettu')
			->groupBy('K1.id', 'AK1.alue_id');
		});
	}

	public function scopeWithMunicipalityIds($query, $idArray) {
		return $query->whereIn('alue.id', function($q) use ($idArray){
			$q->select('AK1.alue_id')
			->from('alue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->join('kunta as KU1', 'KU1.id', '=', 'K1.kunta_id')
			->whereIn('KU1.id', $idArray)
			->whereNull('KU1.poistettu')
			->groupBy('K1.id', 'AK1.alue_id');
		});
	}

	public function scopeWithMunicipalityNumber($query, $keyword) {
		return $query->whereIn('alue.id', function($q) use ($keyword){
			$q->select('AK1.alue_id')
			->from('alue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->join('kunta as KU1', 'KU1.id', '=', 'K1.kunta_id')
			->where('KU1.kuntanumero', '=', $keyword)
			->whereNull('KU1.poistettu');
		});
	}

	public function scopewithVillageName($query, $keyword) {
		return $query->whereIn('alue.id', function($q) use ($keyword){
			$q->select('AK1.alue_id')
			->from('alue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->where('K1.nimi', 'ILIKE', "%" . $keyword . "%")
			//->orWhere('K1.nimi_se', 'ILIKE', "%" . $keyword . "%")
			->whereNull('K1.poistettu');
		});
	}

	public function scopewithVillageNumber($query, $keyword) {
		return $query->whereIn('alue.id', function($q) use ($keyword){
			$q->select('AK1.alue_id')
			->from('alue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->where('K1.kylanumero', '=', $keyword)
			->whereNull('K1.poistettu');
		});
	}

	public function scopewithVillageId($query, $kylaId) {
		return $query->whereIn('alue.id', function($q) use ($kylaId){
			$q->select('AK1.alue_id')
			->from('alue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->where('K1.id', '=', $kylaId)
			->whereNull('K1.poistettu');
		});
	}

	public function scopewithVillageIds($query, $idArray) {
		return $query->whereIn('alue.id', function($q) use ($idArray){
			$q->select('AK1.alue_id')
			->from('alue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->whereIn('K1.id', $idArray)
			->whereNull('K1.poistettu');
		});
	}

	public function scopeWithInventointiprojektiOrInventoija($query, $inventointiprojekti_id, $inventoija_id) {
		if(($inventointiprojekti_id && $inventointiprojekti_id != 'null') || ($inventoija_id && $inventoija_id != 'null')) {

			$query->whereIn('alue.id', function($q) use ($inventointiprojekti_id, $inventoija_id) {

				$q->select('IA1.alue_id')->from('inventointiprojekti_alue as IA1')
				  ->whereNull('IA1.poistettu');

				if($inventointiprojekti_id && $inventointiprojekti_id != 'null') {
					$q->where('IA1.inventointiprojekti_id', '=', $inventointiprojekti_id);
				}
				if($inventoija_id && $inventoija_id != 'null') {
					$q->where('IA1.inventoija_id', '=', $inventoija_id);
				}
			});
		}
		return $query;
	}

	public function scopeWithLuoja($query, $luojaId) {
		return $query->where('alue.luoja', '=', $luojaId);
	}

	/*
	 * Haku id listalla. Koritoiminnallisuus käyttää
	 */
	public function scopeWithAlueIdLista($query, $keyword) {
	    return $query->whereIn('alue.id', $keyword);
	}
}
