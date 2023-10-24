<?php

namespace App\Rak;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class Kiinteisto extends Model {

    use SoftDeletes;

    protected $table = "kiinteisto";

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'kiinteiston_sijainti'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'kyla_id',
        'kiinteistotunnus',
        'nimi',
        'osoite',
        'postinumero',
        'paikkakunta',
        'lisatiedot',
        'perustelut_yhteenveto',
        'lahteet',
        'kiinteiston_sijainti',
        'asutushistoria',
        'lahiymparisto',
        'pihapiiri',
        'omistajatiedot',
        'julkinen',
        'arkeologinen_intressi',
        'muu_historia',
        'perustelut',
        'tarkistettu',
        'palstanumero',
        'arkeologinen_kohde',
        'linkit_paikallismuseoihin',
        'paikallismuseot_kuvaus'
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


    public function images() {
        return $this->belongsToMany('App\Rak\Kuva', 'kuva_kiinteisto');
    }

    public function files() {
        return $this->belongsToMany('App\Tiedosto', 'tiedosto_kiinteisto');
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
     * @version 1.0
     * @since 1.0
     */
    public static function getAll() {

        $qry = Kiinteisto::select('kiinteisto.id', 'kiinteistotunnus', 'kiinteisto.nimi', 'kiinteisto.luoja',
            'kiinteisto.osoite', 'kiinteisto.postinumero', 'kiinteisto.paikkakunta',
            'kiinteisto.kyla_id', 'kiinteisto.arvotustyyppi_id', 'palstanumero',
            DB::raw(MipGis::getGeometryFieldQueryString("kiinteiston_sijainti", "sijainti"))
            )
            ->leftJoin('kyla', 'kiinteisto.kyla_id', '=', 'kyla.id')
            ->leftJoin('kunta', 'kyla.kunta_id', '=', 'kunta.id')
            ->addSelect('kunta.id as kunta_id','kunta.nimi as kunta', 'kunta.nimi_se as kunta_se', 'kyla.nimi as kyla', 'kunta.kuntanumero as kuntanumero', 'kyla.kylanumero as kylanumero')
            ->leftJoin('arvotustyyppi', 'kiinteisto.arvotustyyppi_id', '=', 'arvotustyyppi.id')
            ->addSelect('arvotustyyppi.'.self::getLocalizedfieldname('nimi').' as arvotustyyppi_nimi');

		return $qry;
    }

    /**
     * Get all public models from DB
     */
    public static function getAllPublicInformation() {

        $qry = Kiinteisto::select('kiinteisto.id', 'kiinteistotunnus', 'kiinteisto.nimi',
            'kiinteisto.osoite', 'kiinteisto.paikkakunta',
            'kiinteisto.kyla_id', 'kiinteisto.arvotustyyppi_id',
            DB::raw(MipGis::getGeometryFieldQueryString("kiinteiston_sijainti", "sijainti"))
            )
            ->leftJoin('kyla', 'kiinteisto.kyla_id', '=', 'kyla.id')
            ->leftJoin('kunta', 'kyla.kunta_id', '=', 'kunta.id')
            ->addSelect('kunta.id as kunta_id','kunta.nimi as kunta', 'kunta.nimi_se as kunta_se', 'kyla.nimi as kyla', 'kunta.kuntanumero as kuntanumero', 'kyla.kylanumero as kylanumero')
            ->leftJoin('arvotustyyppi', 'kiinteisto.arvotustyyppi_id', '=', 'arvotustyyppi.id')
            ->addSelect('arvotustyyppi.'.self::getLocalizedfieldname('nimi').' as arvotustyyppi_nimi')
            ->where('kiinteisto.julkinen', '=', true);

		return $qry;
    }

    /**
     * Method to get single entity with only public information with given ID
     */
    public static function getSinglePublicInformation($id) {
        return Kiinteisto::select('kiinteisto.id', 'kiinteisto.kyla_id', 'kiinteisto.kiinteistotunnus', 'kiinteisto.nimi', 'kiinteisto.osoite',
        'kiinteisto.paikkakunta', 'kiinteisto.aluetyyppi', 'kiinteisto.arvotus',
        'kiinteisto.historiallinen_tilatyyppi', 'kiinteisto.kiinteiston_sijainti',
        'kiinteisto.arvotustyyppi_id',
        DB::raw('ST_AsGeoJson(ST_transform(kiinteiston_sijainti, 4326)) as sijainti')
        )->where('kiinteisto.id', '=', $id)
        ->where('kiinteisto.julkinen', '=', true);
    }

    /**
     * Method to get single entity from db with given ID
     *
     * @param int $id
     * @version 1.0
     * @since 1.0
     */
    public static function getSingle($id) {
        return Kiinteisto::select('kiinteisto.*',
            DB::raw('ST_AsGeoJson(ST_transform(kiinteiston_sijainti, 4326)) as sijainti')
            )
            ->where('kiinteisto.id', '=', $id);
    }

    /*
     * If the user role is katselija, we do not return all fields.
     * If the kiinteisto we're getting is public, return almost all fields, if it's not public return only a few fields.
     */
    public static function getSingleForKatselija($id) {
        $isJulkinen= Kiinteisto::select('kiinteisto.julkinen')->where('kiinteisto.id', '=', $id)->first();
        if($isJulkinen->julkinen) {
            //We do not return the omistajatieto and lisatiedot fields.
            return Kiinteisto::select('kiinteisto.id', 'kiinteisto.kyla_id', 'kiinteisto.kiinteistotunnus', 'kiinteisto.nimi', 'kiinteisto.osoite', 'kiinteisto.palstanumero',
                'kiinteisto.postinumero', 'kiinteisto.paikkakunta', 'kiinteisto.aluetyyppi', 'kiinteisto.arvotus',
                'kiinteisto.historiallinen_tilatyyppi', 'kiinteisto.perustelut_yhteenveto', 'kiinteisto.lahteet', 'kiinteisto.kiinteiston_sijainti', 'kiinteisto.asutushistoria',
                'kiinteisto.lahiymparisto', 'kiinteisto.pihapiiri', 'kiinteisto.arkeologinen_intressi', 'kiinteisto.muu_historia', 'kiinteisto.perustelut',
                'kiinteisto.tarkistettu', 'kiinteisto.poistettu', 'kiinteisto.luotu', 'kiinteisto.muokattu', 'kiinteisto.luoja',
                'kiinteisto.muokkaaja', 'kiinteisto.poistaja', 'kiinteisto.data_sailo', 'kiinteisto.arvotustyyppi_id', 'kiinteisto.julkinen',
                'linkit_paikallismuseoihin', 'paikallismuseot_kuvaus',
                DB::raw('ST_AsGeoJson(ST_transform(kiinteiston_sijainti, 4326)) as sijainti')
                )->where('kiinteisto.id', '=', $id);
        } else {
            //We return only a few public fields.
            return Kiinteisto::select('kiinteisto.id', 'kiinteisto.kyla_id', 'kiinteisto.kiinteistotunnus', 'kiinteisto.nimi', 'kiinteisto.paikkakunta',
                'kiinteisto.palstanumero', 'kiinteisto.poistettu', 'kiinteisto.luotu', 'kiinteisto.muokattu', 'kiinteisto.luoja',
                'kiinteisto.muokkaaja', 'kiinteisto.poistaja',
                DB::raw('ST_AsGeoJson(ST_transform(kiinteiston_sijainti, 4326)) as sijainti')
                )
                ->where('kiinteisto.id', '=', $id);
        }
    }

    public static function getWithinArvoalue($id) {
        return Kiinteisto::on()->fromQuery( DB::raw("select k.*, ST_AsGeoJson(ST_transform(kiinteiston_sijainti, 4326)) as sijainti from kiinteisto k, arvoalue a where ST_Within(k.kiinteiston_sijainti, a.aluerajaus) and a.id = :arvoalue_id and k.poistettu is null"),
            array('arvoalue_id' => $id));
    }

    public function kyla() {
        return $this->belongsTo('App\Kyla');
    }

    public function arvotustyyppi() {
        return $this->belongsTo('App\Rak\Arvotustyyppi');
    }

    public function aluetyypit() {
        return $this->belongsToMany('App\Rak\Aluetyyppi', 'kiinteisto_aluetyyppi', 'kiinteisto_id', 'aluetyyppi_id');
    }

    public function historialliset_tilatyypit() {
        return $this->belongsToMany('App\Rak\Tilatyyppi', 'kiinteisto_historiallinen_tilatyyppi', 'kiinteisto_id', 'tilatyyppi_id');
    }

    public function matkaraportit() {
    	return $this->hasMany('App\Rak\Matkaraportti');
    }
    /*
     public function suojelutyypit() {
     return $this->belongsToMany('App\Rak\Suojelutyyppi', 'kiinteisto_suojelutyyppi', 'kiinteisto_id', 'suojelutyyppi_id');
     }
     */

    public function suojelutiedot() {
        return $this->hasMany('App\Rak\KiinteistoSuojelutyyppi');
    }

    /**
     * Method to get the buildings of this Property
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * @version 1.0
     * @since 1.0
     */
    public function buildings() {
        return $this->hasMany('App\Rak\Rakennus', 'kiinteisto_id', 'id')
        ->addSelect('*',DB::raw(MipGis::getGeometryFieldQueryString("rakennuksen_sijainti", "sijainti")));
    }

    /*
     * Inventointiprojektit joissa kiinteistö on inventoituna.
     * PALAUTETAAN AINOASTAAN kertaalleen jokainen projekti
     */
    public function inventointiprojektit() {
    	return $this->belongsToMany('App\Rak\Inventointiprojekti', 'inventointiprojekti_kiinteisto')
    		->whereNull('inventointiprojekti_kiinteisto.poistettu')
    		->whereIn('inventointiprojekti_kiinteisto.inventointiprojekti_id', function($q) {
    			$q->select('id')->from('inventointiprojekti')
    				->whereIn('inventointiprojekti.laji_id', function($q) {
    					$q->select('id')
    						->from('inventointiprojekti_laji')
    						->where('tekninen_projekti', '=', false);
    				});
    		})
    		->groupBy(['inventointiprojekti_kiinteisto.inventointiprojekti_id', 'inventointiprojekti.id', 'inventointiprojekti_kiinteisto.kiinteisto_id'])
    		->orderBy('inventointiprojekti_kiinteisto.inventointiprojekti_id');
    }

    /*
     * Palauttaa kyseisen entiteetin inventoineet käyttäjät
     */
    public function inventoijat_str() {
    	return $this->belongsToMany('App\Kayttaja', 'inventointiprojekti_kiinteisto', 'kiinteisto_id', 'inventoija_id')
    	->whereNull('kayttaja.poistettu')
    	->whereNull('inventointiprojekti_kiinteisto.poistettu')
    	->whereIn('inventointiprojekti_kiinteisto.inventointiprojekti_id', function($q) {
    		$q->select('id')->from('inventointiprojekti')
				->whereIn('inventointiprojekti.laji_id', function($q) {
		    		$q->select('id')
		    		->from('inventointiprojekti_laji')
		    		->where('tekninen_projekti', '=', false);
			    });
    	})
    	->groupBy(['kayttaja.id', 'inventointiprojekti_kiinteisto.inventoija_id', 'inventointiprojekti_kiinteisto.kiinteisto_id']);

    }

    public static function inventoijat($kiinteistoId, $inventointiprojektiId) {
    	$inventoijat = DB::select(DB::raw('select inventointiprojekti_kiinteisto.id, inventointiprojekti_id, inventointiprojekti.nimi,
							kiinteisto_id, kayttaja.etunimi, kayttaja.sukunimi, inventointipaiva, kenttapaiva, inventointiprojekti_kiinteisto.inventoija_id
							from inventointiprojekti_kiinteisto
							join kayttaja on inventointiprojekti_kiinteisto.inventoija_id = kayttaja.id
							join inventointiprojekti on inventointiprojekti_kiinteisto.inventointiprojekti_id = inventointiprojekti.id
							where inventointiprojekti_kiinteisto.id in (
							    select max(id)
							    from inventointiprojekti_kiinteisto
							    where inventointiprojekti_kiinteisto.kiinteisto_id = ?
								and inventointiprojekti_kiinteisto.inventointiprojekti_id = ?
								and inventointiprojekti_kiinteisto.poistettu is null
							    group by inventointiprojekti_id, inventoija_id
							)
							and inventointiprojekti_kiinteisto.poistettu is null
							order by inventointiprojekti_kiinteisto.id'), [$kiinteistoId, $inventointiprojektiId]);
    	return $inventoijat;
    }

    /**
     * Method to get culturehistoricalvalues of the estate
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     * @version 1.0
     * @since 1.0
     */
    public function kulttuurihistoriallisetarvot() {
        return $this->belongsToMany('App\Rak\KiinteistonKulttuurihistoriallinenArvo', 'kiinteisto_kiinteistokulttuurihistoriallinenarvo', 'kiinteisto_id', 'kulttuurihistoriallinenarvo_id');
    }

    public function rakennusOsoitteet() {
        return $this->hasManyThrough('App\Rak\RakennusOsoite', 'App\Rak\Rakennus');
    }


    public function scopeWithOrderBy($query, $bbox=null, $order_field=null, $order_direction=null) {
    	if ($order_field == "bbox_center" && !is_null($bbox)) {
    		return $query->orderByRaw(MipGis::getGeometryFieldOrderByBoundingBoxCenterString("kiinteiston_sijainti", $bbox));
    	}

    	$order_table = "kiinteisto";

    	if (is_null($order_field) && is_null($order_direction)) {
    		$order_table = "kunta";
    		if(App::getLocale()=="se"){
    			$order_field = "nimi_se";
    		} else {
    			$order_field = "nimi";
    		}
    	} else if ($order_field == "kunta") {
    		$order_table = "kunta";
    		if(App::getLocale()=="se"){
    			$order_field = "nimi_se";
    		} else {
    			$order_field = "nimi";
    		}
    	} else if ($order_field == "kyla") {
    		$order_table = "kyla";
    		if(App::getLocale()=="se"){
    			$order_field = "nimi_se";
    		} else {
    			$order_field = "nimi";
    		}
    	} else if ($order_field == "arvotustyyppi_nimi") {
    		$order_table = "arvotustyyppi";
    		$order_field = self::getLocalizedfieldname('nimi');
    	}

    	/*
    	 * If orderfield AND orderDirection is given, ONLY then order the results by given field
    	 */
    	if ($order_field != null && $order_direction != null) {

    		//We may not be able to order the data by the bbox
    		if($order_field == 'bbox_center' && is_null($bbox)) {
    			$order_field = 'id';
    		}

    		$query->orderBy($order_table.'.'.$order_field, $order_direction);
    	}

    	return $query;
    }
    /**
     * Limit results to address, search building and estate address(es)
     * @param $query
     * @param $keyword
     * @version 1.0
     * @since
     */
    public function scopeWithAddress($query, $keyword) {
        return $query->whereIn("kiinteisto.id", function($q) use ($keyword) {
            return $q->select('kiinteisto_id')
            ->from('rakennus')
            //Ei haeta poistettujen rakennusten osoitteita
            ->whereNull('poistettu')
            ->whereIn("rakennus.id", function($q) use ($keyword) {
                //"Splitataan" hakusana ensimmäisestä numerosta
                //Kaikki mikä on numeron vasemmalla puolella, on katunimeä, loput katunumeroa (esim eerikinkatu 12a)
                preg_match_all('(\d+|\D+)', $keyword, $osumat) && $osumat = $osumat[0];

                $katunimi = null;
                $katunumero = null;

                $sizeOfOsumat = sizeof($osumat);

                if($sizeOfOsumat > 0) {
                    $katunimi = $osumat[0];
                }

                if($sizeOfOsumat > 1) {
                    $katunumero = $osumat[1];
                }

                if($sizeOfOsumat > 2) {
                    for($i = 2; $i<$sizeOfOsumat; $i++) {
                        $katunumero .= $osumat[$i];
                    }
                }

                $katunimi = rtrim($katunimi);

                $q->select('rakennus_id')->from("rakennus_osoite");
                if(strlen($katunimi) > 0) {
                    $q->where('rakennus_osoite.katunimi', 'ILIKE', "%".$katunimi."%");
                }
                if(strlen($katunumero) > 0) {
                    $q->where('rakennus_osoite.katunumero', 'ILIKE', $katunumero."%");
                }

                return $q;
            });
        })
        ->orWhere("kiinteisto.osoite", "ILIKE", "%".$keyword."%");
    }

    /**
     * Limit results by given estate ID
     *
     * @param $query
     * @param int $keyword
     * @version 1.0
     * @since 1.0
     */
    public function scopeWithID($query, $keyword) {
        return $query->where('kiinteisto.id', '=', $keyword);
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

    public function scopeWithPalstanumero($query, $keyword) {
        return $query->where("kiinteisto.palstanumero", "=" , $keyword);
    }

    /**
     * Limit results to only for those rows which MUNICIPALITY matches the given keyword
     *
     * @param $query
     * @param String $keyword
     * @version 1.0
     * @since 1.0
     */
    public function scopeWithMunicipality($query, $keyword) {
        if(App::getLocale()=="se"){
            return $query->where('kunta.nimi_se', 'ILIKE', $keyword . "%")
            ->orWhere('kunta.nimi', 'ILIKE', $keyword . "%");
        }
        return $query->where('kunta.nimi', 'ILIKE', $keyword . "%");
        //->orWhere('kunta.nimi_se', 'ILIKE', $keyword . "%");
    }

    public function scopeWithMunicipalityNumber($query, $keyword) {
        return $query->where('kunta.kuntanumero', '=', $keyword);
    }

    public function scopeWithMunicipalityId($query, $keyword) {
        return $query->where('kunta.id', '=', $keyword);
    }

    /**
     * Limit results to only for those rows which NAME values matches the given keyword
     *
     * @param $query
     * @param String $keyword
     * @version 1.0
     * @since 1.0
     */
    public function scopeWithName($query, $keyword) {
        return $query->where('kiinteisto.nimi', 'ILIKE', "%".$keyword."%");
    }

    /**
     * Limit results to only for those whose EstateID matches to given keyword
     * @param $query
     * @param String $keyword
     * @version 1.0
     * @since 1.0
     */
    public function scopeWithPropertyID($query, $keyword) {
        return $query->where("kiinteisto.kiinteistotunnus", "LIKE", '%'.$keyword.'%');
    }

    /**
     * Limit results to only for those rows which VILLAGE values matches the given keyword
     *
     * @param $query
     * @param String $keyword
     * @version 1.0
     * @since 1.0
     */
    public function scopeWithVillage($query, $keyword){
        return $query->where('kyla.nimi', 'ILIKE', "%" . $keyword . "%");
        //->orWhere('kyla.nimi_se', 'ILIKE', $keyword . "%");
    }

    public function scopeWithVillageNumber($query, $keyword){
        return $query->where('kyla.kylanumero', '=', $keyword);
    }

    public function scopeWithVillageId($query, $kyla_id){
        return $query->where('kyla.id', '=', $kyla_id);
    }

    public function scopeWithArvotustyyppiId($query, $keyword) {
        $keyword = explode(',' , $keyword);
        return $query->whereIn('kiinteisto.arvotustyyppi_id', function($q) use ($keyword) {
            $q->select('id')
            ->from('arvotustyyppi')
            ->whereNull('arvotustyyppi.poistettu')
            ->where(function($query) use ($keyword) {
                return $query->whereIn('arvotustyyppi.id', $keyword);
            });
        });
    }

    /**
     * Limit results to only for those rows which PAIKKAKUNTA values matches the given keyword
     *
     * @param $query
     * @param String $keyword
     * @version 1.0
     * @since 1.0
     */
    public function scopeWithPaikkakunta($query, $keyword) {
        return $query->where('kiinteisto.paikkakunta', 'ILIKE', "%".$keyword."%");
    }

    /**
     * Limit redults to only for area of given bounding box
     *
     * @param $query
     * @param String $bbox The bounding box value (21.900000 60.910000,22.000000 61.000000)
     * @version 1.0
     * @since 1.0
     */
    public function scopeWithBoundingBox($query, $bbox) {
        return $query->whereRaw(MipGis::getGeometryFieldBoundingBoxQueryWhereString("kiinteiston_sijainti", $bbox));
    }

    public function scopeWithPolygon($query, $polygon) {
        return $query->whereRaw(MipGis::getGeometryFieldPolygonQueryWhereString($polygon, "kiinteiston_sijainti"));
    }

    public function scopeWithInventointiprojektiOrInventoija($query, $inventointiprojekti_id, $inventoija_id) {

        return $query->whereIn('kiinteisto.id', function($q) use ($inventointiprojekti_id, $inventoija_id) {
            $q->select('kiinteisto_id')
            ->from('inventointiprojekti_kiinteisto')
            ->whereNull('inventointiprojekti_kiinteisto.poistettu');
            if($inventointiprojekti_id && !is_null($inventointiprojekti_id)) {
                $q->where('inventointiprojekti_id', '=' , $inventointiprojekti_id);
            }
            if($inventoija_id && !is_null($inventoija_id)) {
                $q->where('inventoija_id', '=', $inventoija_id);
            }
        });
            return $query;
    }

    /*
     * Haku kiinteistöjen id listalla. Koritoiminnallisuus käyttää
     */
    public function scopeWithKiinteistoIdLista($query, $keyword) {
        return $query->whereIn('kiinteisto.id', $keyword);
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

    /*
     * Palautetaan pienin vapaa palstanumero samalle kiinteistötunnukselle.
     */
    public static function getNextFreePalstanumero($kiinteistotunnus){
        //Palautetaan pienin vapaa numero
        $palstanumero = 0;
        $isFree = false;

        while(!$isFree) {
            //Try to search such kiinteisto
            $palstanumero++;
            $count = DB::table('kiinteisto')->where('kiinteistotunnus', '=', $kiinteistotunnus)->where('palstanumero', '=', $palstanumero)->whereNull(Kiinteisto::DELETED_AT)->count();

            $isFree = ($count < 1);
        }

        return $palstanumero;
    }

    public function scopeWithLuoja($query, $luojaId) {
    	return $query->where('kiinteisto.luoja', '=', $luojaId);
    }
}
