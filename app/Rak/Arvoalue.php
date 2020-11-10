<?php

namespace App\Rak;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App;

class Arvoalue extends Model {

	use SoftDeletes;

	protected $table = "arvoalue";

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
		'keskipiste', 'aluerajaus'
	];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	*/
	protected $fillable = [
		'alue_id',
		'nimi',
	    'paikkakunta',
		'arvotustyyppi_id',
		'kuvaus',
		'aluetyyppi_id',
		'tarkistettu',
		'inventointinumero',
		'yhteenveto',
	    'arkeologinen_kohde'
	];

	public function files() {
		return $this->belongsToMany('App\Tiedosto', 'tiedosto_arvoalue');
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
		return $this->belongsToMany('App\Rak\Kuva', 'kuva_arvoalue');
	}
	/**
	 * Method to get the Area of THIS valueArea
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function _alue() {
		return $this->belongsTo('App\Rak\Alue', 'alue_id');
	}

	public function arvotustyyppi() {
		return $this->belongsTo('App\Rak\Arvotustyyppi', 'arvotustyyppi_id');
	}

	public function aluetyyppi() {
		return $this->belongsTo('App\Rak\Aluetyyppi', 'aluetyyppi_id');
	}

	public function kylat() {
		return $this->belongsToMany('App\Kyla');
	}

	/*
     * Inventointiprojektit joissa kiinteistö on inventoituna.
     * PALAUTETAAN AINOASTAAN kertaalleen jokainen projekti
     */
    public function inventointiprojektit() {
        return $this->belongsToMany('App\Rak\Inventointiprojekti', 'inventointiprojekti_arvoalue')
        	->whereNull('inventointiprojekti_arvoalue.poistettu')
        	->whereIn('inventointiprojekti_arvoalue.inventointiprojekti_id', function($q) {
        		$q->select('id')->from('inventointiprojekti')
        		->whereIn('inventointiprojekti.laji_id', function($q) {
        			$q->select('id')
        			->from('inventointiprojekti_laji')
        			->where('tekninen_projekti', '=', false);
        		});
        	})
    		->groupBy(['inventointiprojekti_arvoalue.inventointiprojekti_id', 'inventointiprojekti.id', 'inventointiprojekti_arvoalue.arvoalue_id'])
    		->orderBy('inventointiprojekti_arvoalue.inventointiprojekti_id');
    }

    /*
     * Palauttaa kyseisen entiteetin inventoineet käyttäjät
     */
    public function inventoijat_str() {
    	return $this->belongsToMany('App\Kayttaja', 'inventointiprojekti_arvoalue', 'arvoalue_id', 'inventoija_id')
    	->whereNull('kayttaja.poistettu')
    	->whereNull('inventointiprojekti_arvoalue.poistettu')
    	->whereIn('inventointiprojekti_arvoalue.inventointiprojekti_id', function($q) {
    		$q->select('id')->from('inventointiprojekti')
    		->whereIn('inventointiprojekti.laji_id', function($q) {
    			$q->select('id')
    			->from('inventointiprojekti_laji')
    			->where('tekninen_projekti', '=', false);
    		});
    	})
    	->groupBy(['kayttaja.id', 'inventointiprojekti_arvoalue.inventoija_id', 'inventointiprojekti_arvoalue.arvoalue_id']);

    }

    public static function inventoijat($arvoalueId, $inventointiprojektiId) {
    	$inventoijat = DB::select(DB::raw('select inventointiprojekti_arvoalue.id, inventointiprojekti_id, inventointiprojekti.nimi,
							arvoalue_id, kayttaja.etunimi, kayttaja.sukunimi, inventointipaiva, kenttapaiva, inventointiprojekti_arvoalue.inventoija_id
							from inventointiprojekti_arvoalue
							join kayttaja on inventointiprojekti_arvoalue.inventoija_id = kayttaja.id
							join inventointiprojekti on inventointiprojekti_arvoalue.inventointiprojekti_id = inventointiprojekti.id
							where inventointiprojekti_arvoalue.id in (
							    select max(id)
							    from inventointiprojekti_arvoalue
							    where inventointiprojekti_arvoalue.arvoalue_id = ?
								and inventointiprojekti_arvoalue.inventointiprojekti_id = ?
								and inventointiprojekti_arvoalue.poistettu is null
							    group by inventointiprojekti_id, inventoija_id
							)
							and inventointiprojekti_arvoalue.poistettu is null
							order by inventointiprojekti_arvoalue.id'), [$arvoalueId, $inventointiprojektiId]);
    	return $inventoijat;
    }

	/**
	 * Get the municipalities of the given valuearea
	 *
	 * @param  $valuearea_id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function municipalities($valuearea_id) {

		return self::select('kunta.*')
			->join('arvoalue_kyla', 'arvoalue_kyla.arvoalue_id', '=', 'arvoalue.id')
			->join('kyla', 'kyla.id', '=', 'arvoalue_kyla.kyla_id')
			->join('kunta', 'kunta.id', '=', 'kyla.kunta_id')
			->groupBy('kunta.id')
			->where('arvoalue.id', '=', $valuearea_id);
	}

	/**
	 * Get the villages of the given valuearea
	 *
	 * @param  $valuearea_id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function villages($valuearea_id) {

		return self::select('kyla.*')
			->join('arvoalue_kyla', 'arvoalue_kyla.arvoalue_id', '=', 'arvoalue.id')
			->join('kyla', 'kyla.id', '=', 'arvoalue_kyla.kyla_id')
			->groupBy('kyla.id')
			->where('arvoalue.id', '=', $valuearea_id);
	}

	/**
	 * Method to get kulttuurihistoriallisetarvot of the value area
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function kulttuurihistoriallisetarvot() {
		return $this->belongsToMany('App\Rak\ArvoalueenKulttuurihistoriallinenArvo', 'arvoalue_arvoaluekulttuurihistoriallinenarvo', 'arvoalue_id', 'kulttuurihistoriallinenarvo_id');
	}

	public function suojelutiedot() {
		return $this->hasMany('App\Rak\ArvoalueSuojelutyyppi');
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
	 * Get all Models from DB - order by given $order_field to given $order_direction
	 *
	 * @param int $order_field field name we want to order results by
	 * @param int $order_direction Direction we want to order results to (ASC/DESC)
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getAll() {

		$kylat_sql  = "( select arvoalue_id, ";
		$kylat_sql .= "  string_agg(kyla.nimi, '\n') as kylat ";
		$kylat_sql .= "  from arvoalue_kyla, kyla ";
		$kylat_sql .= "  where arvoalue_kyla.kyla_id = kyla.id ";
		$kylat_sql .= "  group by arvoalue_id ";
		$kylat_sql .= ") as arvoalue_kylat ";

		$kunnat_sql  = "( select arvoalue_id, ";
		$kunnat_sql .= "  string_agg(kunta.nimi, '\n') as kunnat ";
		$kunnat_sql .= "  from arvoalue_kyla, kyla ";
		$kunnat_sql .= "  left join kunta on (kunta.id = kyla.kunta_id) ";
		$kunnat_sql .= "  where arvoalue_kyla.kyla_id = kyla.id ";
		$kunnat_sql .= "  group by arvoalue_id ";
		$kunnat_sql .= ") as arvoalue_kunnat ";


		$qry= self::select(
		    'arvoalue.id','arvoalue.paikkakunta','arvoalue.nimi', 'arvoalue.luoja',
			'arvoalue.alue_id', 'arvoalue.aluetyyppi_id', 'arvoalue.arvotustyyppi_id', 'arvoalue.inventointinumero',
			DB::raw(MipGis::getGeometryFieldQueryString("arvoalue.keskipiste", "sijainti")),
			DB::raw(MipGis::getGeometryFieldQueryString("arvoalue.aluerajaus", "alue"))
			)
			->leftJoin(DB::raw($kylat_sql), 'arvoalue.id', '=', 'arvoalue_kylat.arvoalue_id')
			->leftJoin(DB::raw($kunnat_sql), 'arvoalue.id', '=', 'arvoalue_kunnat.arvoalue_id')
			->leftJoin('alue', 'arvoalue.alue_id', '=', 'alue.id')
			->leftJoin('aluetyyppi', 'arvoalue.aluetyyppi_id', '=', 'aluetyyppi.id')
			->leftJoin('arvotustyyppi', 'arvoalue.arvotustyyppi_id', '=', 'arvotustyyppi.id')
			;

		return $qry;
	}

	public function scopeWithOrderBy($query, $bbox=null, $order_field=null, $order_direction=null) {

		if ($order_field == "bbox_center" && !is_null($bbox)) {
			return $query->orderByRaw(MipGis::getGeometryFieldOrderByBoundingBoxAreaAndPointCenterString("arvoalue.aluerajaus", "arvoalue.keskipiste", $bbox));
		}

		if (is_null($order_direction) || $order_direction=="") {
			$order_direction = "asc";
		}
		if($order_field == "kunta") {
			return $query->orderBy("arvoalue_kunnat.kunnat", $order_direction);
		} else if($order_field == "kyla") {
			return $query->orderBy("arvoalue_kylat.kylat", $order_direction);
		}else if ($order_field == "paikkakunta") {
			return $query->orderBy("arvoalue.paikkakunta", $order_direction);
		}else if ($order_field == "nimi") {
			return $query->orderBy("arvoalue.nimi", $order_direction);
		} else if ($order_field == "alue_nimi") {
			return $query->orderBy("alue.nimi", $order_direction);
		} else if ($order_field == "arvotustyyppi_nimi") {
			return $query->orderBy("arvotustyyppi.".self::getLocalizedfieldname('nimi'), $order_direction);
		} else if ($order_field == "aluetyyppi_nimi") {
			return $query->orderBy("aluetyyppi.".self::getLocalizedfieldname('nimi'), $order_direction);
		}

		return $query->orderBy("arvoalue_kunnat.kunnat", $order_direction);
	}

	public static function getAllForInv() {

	    $kylat_sql  = "( select arvoalue_id, ";
	    $kylat_sql .= "  string_agg(kyla.nimi, '\n') as kylat ";
	    $kylat_sql .= "  from arvoalue_kyla, kyla ";
	    $kylat_sql .= "  where arvoalue_kyla.kyla_id = kyla.id ";
	    $kylat_sql .= "  group by arvoalue_id ";
	    $kylat_sql .= ") as arvoalue_kylat ";

	    $kunnat_sql  = "( select arvoalue_id, ";
	    $kunnat_sql .= "  string_agg(kunta.nimi, '\n') as kunnat ";
	    $kunnat_sql .= "  from arvoalue_kyla, kyla ";
	    $kunnat_sql .= "  left join kunta on (kunta.id = kyla.kunta_id) ";
	    $kunnat_sql .= "  where arvoalue_kyla.kyla_id = kyla.id ";
	    $kunnat_sql .= "  group by arvoalue_id ";
	    $kunnat_sql .= ") as arvoalue_kunnat ";

	    $qry= self::select(
	            'arvoalue.id', 'arvoalue.nimi', 'arvoalue.luoja',
				'arvoalue.alue_id', 'arvoalue.aluetyyppi_id', 'arvoalue.arvotustyyppi_id', 'arvoalue.inventointinumero',
	            'arvoalue_kylat.kylat',
	            'arvoalue_kunnat.kunnat',
	            'aluetyyppi.nimi_fi',
	            'aluetyyppi.nimi_se',
	            'alue.nimi as alue_nimi',
	        DB::raw(MipGis::getGeometryFieldQueryString("arvoalue.keskipiste", "sijainti")),
	        DB::raw(MipGis::getGeometryFieldQueryString("arvoalue.aluerajaus", "alue"))
	        )
	        ->join('alue', 'alue.id', '=', 'arvoalue.alue_id')
	        ->leftJoin('aluetyyppi', 'arvoalue.aluetyyppi_id', '=', 'aluetyyppi.id')
	        ->leftJoin(DB::raw($kylat_sql), 'arvoalue.id', '=', 'arvoalue_kylat.arvoalue_id')
	        ->leftJoin(DB::raw($kunnat_sql), 'arvoalue.id', '=', 'arvoalue_kunnat.arvoalue_id')
	        ;

		return $qry;
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
		return self::select('*',
			DB::raw(MipGis::getGeometryFieldQueryString("keskipiste", "sijainti")),
			DB::raw(MipGis::getGeometryFieldQueryString("aluerajaus", "alue"))
			)
		->where('id', '=', $id);
	}


	public static function getByAreaId($area_id) {
		return self::select('*',
			DB::raw(MipGis::getGeometryFieldQueryString("keskipiste", "sijainti")),
			DB::raw(MipGis::getGeometryFieldQueryString("aluerajaus", "alue"))
			)
			->where('alue_id', '=', $area_id);
	}

	/**
	 * Limit results to only for area of given bounding box
	 *
	 * @param  $query
	 * @param String $bbox The bounding box value (21.900000 60.910000,22.000000 61.000000)
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithBoundingBox($query, $bbox) {
		return $query->whereRaw(MipGis::getGeometryFieldBoundingBoxQueryWhereStringFromAreaAndPoint("arvoalue.aluerajaus", "arvoalue.keskipiste", $bbox));
	}

	public function scopeWithPolygon($query, $polygon) {
		return $query->whereRaw(MipGis::getGeometryFieldPolygonQueryWhereString($polygon, "arvoalue.keskipiste", "arvoalue.aluerajaus"));
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
	 * Limit results to entities with name matching the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithName($query, $keyword) {
		return $query->where('arvoalue.nimi', 'ILIKE', '%'.$keyword.'%');
	}

	public function scopeWithID($query, $id){
		return $query->where('id', '=', $id);
	}

	public function scopeWithVillageName($query, $keyword) {

		return $query->whereIn('arvoalue.id', function($q) use ($keyword){
			$q->select('AK1.arvoalue_id')
			->from('arvoalue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->where('K1.nimi', 'ILIKE', "%" . $keyword . "%")
			//->orWhere('K1.nimi_se', 'ILIKE', "%" . $keyword . "%")
			->whereNull('K1.poistettu');
		});
	}

	public function scopeWithVillageNumber($query, $keyword) {
		return $query->whereIn('arvoalue.id', function($q) use ($keyword){
			$q->select('AK1.arvoalue_id')
			->from('arvoalue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->where('K1.kylanumero', 'ILIKE', $keyword)
			->whereNull('K1.poistettu');
		});
	}

	public function scopeWithVillageId($query, $kylaId) {
		return $query->whereIn('arvoalue.id', function($q) use ($kylaId){
			$q->select('AK1.arvoalue_id')
			->from('arvoalue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->where('K1.id', '=', $kylaId)
			->whereNull('K1.poistettu');
		});
	}

	public function scopeWithVillageIds($query, $kylaArray) {
		return $query->whereIn('arvoalue.id', function($q) use ($kylaArray){
			$q->select('AK1.arvoalue_id')
			->from('arvoalue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->whereIn('K1.id', $kylaArray)
			->whereNull('K1.poistettu');
		});
	}

	/**
	 * Limit results to only for those rows which PAIKKAKUNTA values matches the given keyword
	 */
	public function scopeWithPaikkakunta($query, $keyword) {
	    return $query->where('arvoalue.paikkakunta', 'ILIKE', "%".$keyword."%");
	}

	/*
	public function scopeWithAreatypeName($query, $keyword) {
		return $query->whereIn('arvoalue.aluetyyppi_id', function($q) use ($keyword){
			$q->select('id')
			->from('aluetyyppi')
			->whereNull('aluetyyppi.poistettu')
			->where(function($query) use ($keyword) {
				return $query->where('aluetyyppi.nimi_fi', 'ILIKE', $keyword."%")
				->orWhere('aluetyyppi.nimi_se', 'ILIKE', $keyword."%")
				->orWhere('aluetyyppi.nimi_en', 'ILIKE', $keyword."%");
			});
		});
	}
	*/

	public function scopeWithAreatypeId($query, $keyword) {
	    $keyword = explode(',' , $keyword);
	    return $query->whereIn('arvoalue.aluetyyppi_id', function($q) use ($keyword) {
	        $q->select('id')
	        ->from('aluetyyppi')
	        ->whereNull('aluetyyppi.poistettu')
	        ->where(function($query) use ($keyword) {
	            return $query->whereIn('aluetyyppi.id', $keyword);
	        });
	    });
	}

	public function scopeWithArvotustyyppiId($query, $keyword) {
	    $keyword = explode(',' , $keyword);
	    return $query->whereIn('arvoalue.arvotustyyppi_id', function($q) use ($keyword) {
	        $q->select('id')
	        ->from('arvotustyyppi')
	        ->whereNull('arvotustyyppi.poistettu')
	        ->where(function($query) use ($keyword) {
	            return $query->whereIn('arvotustyyppi.id', $keyword);
	        });
	    });
	}
	public function scopeWithAreaName($query, $keyword) {
		return $query->whereIn('arvoalue.alue_id', function($q) use ($keyword){
			$q->select('id')
			->from('alue')
			->where('nimi', 'ILIKE', "%" . $keyword."%")
			->whereNull('poistettu');
		});

		//Mikä tämä on? Eihän tänne edes mennä koskaan.
		return $query->join('alue as AN', 'AN.id', '=', 'arvoalue.alue_id')
			->where('AN.nimi', 'ILIKE', "%" . $keyword."%")
			->whereNull('AN.poistettu');
	}

	public function scopeWithAreaId($query, $keyword) {
		return $query->where('arvoalue.alue_id', '=', $keyword);
	}

	public function scopeWithMunicipalityName($query, $keyword) {
	    if(App::getLocale()=="se"){
	        return $query->whereIn('arvoalue.id', function($q) use ($keyword){
	            $q->select('AK1.arvoalue_id')
	            ->from('arvoalue_kyla as AK1')
	            ->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
	            ->join('kunta as KU1', 'KU1.id', '=', 'K1.kunta_id')
	            ->where('KU1.nimi_se', 'ILIKE', $keyword . "%")
	            ->orWhere('KU1.nimi', 'ILIKE', $keyword . "%")
	            ->whereNull('KU1.poistettu');
	        });
	    }
	    return $query->whereIn('arvoalue.id', function($q) use ($keyword){
	        $q->select('AK1.arvoalue_id')
	        ->from('arvoalue_kyla as AK1')
	        ->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
	        ->join('kunta as KU1', 'KU1.id', '=', 'K1.kunta_id')
	        ->where('KU1.nimi', 'ILIKE', $keyword . "%")
	        ->whereNull('KU1.poistettu');
	    });
	}

	public function scopeWithMunicipalityNumber($query, $keyword) {
		return $query->whereIn('arvoalue.id', function($q) use ($keyword){
			$q->select('AK1.arvoalue_id')
			->from('arvoalue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->join('kunta as KU1', 'KU1.id', '=', 'K1.kunta_id')
			->where('KU1.kuntanumero', 'ILIKE', $keyword)
			->whereNull('KU1.poistettu');
		});
	}

	public function scopeWithMunicipalityId($query, $keyword) {
		return $query->whereIn('arvoalue.id', function($q) use ($keyword){
			$q->select('AK1.arvoalue_id')
			->from('arvoalue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->join('kunta as KU1', 'KU1.id', '=', 'K1.kunta_id')
			->where('KU1.id', '=', $keyword)
			->whereNull('KU1.poistettu');
		});
	}

	public function scopeWithMunicipalityIds($query, $kuntaArray) {
		return $query->whereIn('arvoalue.id', function($q) use ($kuntaArray){
			$q->select('AK1.arvoalue_id')
			->from('arvoalue_kyla as AK1')
			->join('kyla as K1', 'K1.id', '=', 'AK1.kyla_id')
			->join('kunta as KU1', 'KU1.id', '=', 'K1.kunta_id')
			->whereIn('KU1.id', $kuntaArray)
			->whereNull('KU1.poistettu');
		});
	}

	public function scopeWithInventointiprojektiOrInventoija($query, $inventointiprojekti_id, $inventoija_id) {

		if(($inventointiprojekti_id && $inventointiprojekti_id != 'null') || ($inventoija_id && $inventoija_id != 'null')) {

			$query->join('inventointiprojekti_arvoalue', 'inventointiprojekti_arvoalue.arvoalue_id', '=', 'arvoalue.id')
			       ->whereNull('inventointiprojekti_arvoalue.poistettu');

			if ($inventointiprojekti_id && $inventointiprojekti_id != 'null') {
				$query->where('inventointiprojekti_arvoalue.inventointiprojekti_id', '=', $inventointiprojekti_id);
			}

			if($inventoija_id && $inventoija_id != 'null') {
				$query->where('inventointiprojekti_arvoalue.inventoija_id', '=', $inventoija_id);
			}
		}

		return $query;
	}

	public function scopeWithLuoja($query, $luojaId) {
		return $query->where('arvoalue.luoja', '=', $luojaId);
	}

	/*
	 * Haku id listalla. Koritoiminnallisuus käyttää
	 */
	public function scopeWithArvoalueIdLista($query, $keyword) {
	    return $query->whereIn('arvoalue.id', $keyword);
	}


}
