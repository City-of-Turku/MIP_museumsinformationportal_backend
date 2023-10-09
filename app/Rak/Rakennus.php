<?php

namespace App\Rak;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App;

class Rakennus extends Model {

	use SoftDeletes;

	protected $table = "rakennus";

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = [
			'rakennuksen_sijainti',
	];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	*/
	protected $fillable = [
			'kiinteisto_id',
			'inventointinumero',
			'kerroslukumaara',
			'rakennusvuosi_alku',
			'rakennusvuosi_loppu',
			'rakennusvuosi_selite',
			'ulkovari',
			'kuntotyyppi_id',
			'nykyinen_tyyli_id',
			'erityispiirteet',
			'rakennushistoria',
			'sisatilakuvaus',
			'rakennuksen_sijainti',
			'muut_tiedot',
			'arvotustyyppi_id',
			'asuin_ja_liikehuoneistoja',
			'rakennustunnus',
			'purettu',
			'kulttuurihistoriallisetarvot_perustelut',
			'postinumero',
			'rakennustyyppi_kuvaus'
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

	public function images() {
		return $this->belongsToMany('App\Rak\Kuva', 'kuva_rakennus');
	}

	public function files() {
		return $this->belongsToMany('App\Tiedosto', 'tiedosto_rakennus');
	}

	public function nykyinentyyli() {
		return $this->belongsTo('App\Rak\Tyylisuunta', 'nykyinen_tyyli_id');
	}

	public function suojelutiedot() {
		return $this->hasMany('App\Rak\RakennusSuojelutyyppi');
	}

	public function muutosvuodet() {
		return $this->hasMany('App\Rak\RakennusMuutosvuosi')->orderBy('alkuvuosi');
	}

	public function osoitteet() {
		return $this->hasMany('App\Rak\RakennusOsoite');
	}

	public function omistajat() {
		return $this->hasMany('App\Rak\RakennusOmistaja');
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
	 * NOTE: If the user role is katselija, we get only the rakennukset belonging to public kiinteistot
	 *
	 * @param int $order_field field name we want to order results by
	 * @param int $order_direction Direction we want to order results to (ASC/DESC)
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getAll() {

		$rak_osoitteet_sql  = "( select rakennus_id, ";
		$rak_osoitteet_sql .= "  string_agg(rakennus_osoite.katunimi || ' ' || rakennus_osoite.katunumero, '\n') as osoitteet ";
		$rak_osoitteet_sql .= "  from rakennus_osoite ";
		$rak_osoitteet_sql .= "  group by rakennus_id ";
		$rak_osoitteet_sql .= ") as rak_osoitteet ";

		$rak_raktyypit_sql  = "( select rakennus_id, ";
		$rak_raktyypit_sql .= "   string_agg(rakennustyyppi.".self::getLocalizedfieldname('nimi').", '\n') as rakennustyypit ";
		$rak_raktyypit_sql .= "   from rakennus_rakennustyyppi, rakennustyyppi ";
		$rak_raktyypit_sql .= "   where rakennus_rakennustyyppi.rakennustyyppi_id = rakennustyyppi.id ";
		$rak_raktyypit_sql .= "   group by rakennus_id ";
		$rak_raktyypit_sql .= ") as rak_rakennustyypit ";

		$rak_suunnittelijat_sql  = "( select rakennus_id, ";
 		$rak_suunnittelijat_sql .= " string_agg( suunnittelija.sukunimi || ' ' || coalesce(suunnittelija.etunimi, ''), '<br />' ) as kaikki_suunnittelijat ";
		$rak_suunnittelijat_sql .= "  from suunnittelija_rakennus ";
		$rak_suunnittelijat_sql .= "  left join suunnittelija suunnittelija on (suunnittelija.id = suunnittelija_rakennus.suunnittelija_id) ";
		$rak_suunnittelijat_sql .= "  group by rakennus_id";
		$rak_suunnittelijat_sql .= ") as rak_suunnittelijat ";

		$rak_inventointiprojektit_sql = "( select kiinteisto_id, ";
		$rak_inventointiprojektit_sql .= " string_agg(distinct inventointiprojekti.nimi, '<br >') as inventointiprojektit_str ";
		$rak_inventointiprojektit_sql .= " from inventointiprojekti, inventointiprojekti_kiinteisto, inventointiprojekti_laji ";
		$rak_inventointiprojektit_sql .= " where inventointiprojekti.id = inventointiprojekti_kiinteisto.inventointiprojekti_id ";
		$rak_inventointiprojektit_sql .= " and inventointiprojekti.laji_id = inventointiprojekti_laji.id ";
		$rak_inventointiprojektit_sql .= " and inventointiprojekti_laji.tekninen_projekti = false ";
		$rak_inventointiprojektit_sql.= "  group by kiinteisto_id ";
		$rak_inventointiprojektit_sql .= ") as rak_inventointiprojektit ";

		$qry = self::select(
				'rakennus.id', 'rakennus.rakennustunnus', 'rakennus.inventointinumero', 'rakennus.purettu', 'rakennus.kuntotyyppi_id', 'rakennus.nykyinen_tyyli_id',
				'rakennus.rakennustyyppi_kuvaus', 'rakennus.rakennusvuosi_alku', 'rakennus.rakennusvuosi_loppu', 'rakennus.rakennusvuosi_selite',
				'rakennus.kiinteisto_id', 'rakennus.arvotustyyppi_id', 'rakennus.luoja', 'rakennus.rakennushistoria', 'rakennus.erityispiirteet', 'rakennus.sisatilakuvaus', 'rakennus.muut_tiedot',
				DB::raw(MipGis::getGeometryFieldQueryString("rakennuksen_sijainti", "sijainti")),
				'arvotustyyppi.'.self::getLocalizedfieldname('nimi').' as arvotustyyppi_nimi',
				'rak_osoitteet.osoitteet',
				'rak_rakennustyypit.rakennustyypit',
		        'rak_suunnittelijat.kaikki_suunnittelijat',
				'rak_inventointiprojektit.inventointiprojektit_str',
		        'kunta.id as kunta_id',
		        'kyla.id as kyla_id',
		        'kiinteisto.nimi as kiinteisto_nimi',
		        'kiinteisto.kiinteistotunnus as kiinteisto_tunnus'
			)
			->join('kiinteisto', 'rakennus.kiinteisto_id', '=', 'kiinteisto.id')
			->join('kyla', 'kiinteisto.kyla_id', '=', 'kyla.id')
			->join('kunta', 'kyla.kunta_id', '=', 'kunta.id')
    		->leftJoin('arvotustyyppi', 'rakennus.arvotustyyppi_id', '=', 'arvotustyyppi.id')
    		->leftJoin(DB::raw($rak_osoitteet_sql), 'rakennus.id', '=', 'rak_osoitteet.rakennus_id')
    		->leftJoin(DB::raw($rak_suunnittelijat_sql), 'rakennus.id', '=', 'rak_suunnittelijat.rakennus_id')
    		->leftJoin(DB::raw($rak_inventointiprojektit_sql), 'rakennus.kiinteisto_id', '=', 'rak_inventointiprojektit.kiinteisto_id')
    		->leftJoin(DB::raw($rak_raktyypit_sql), 'rakennus.id', '=', 'rak_rakennustyypit.rakennus_id')
    		->whereNull('rakennus.poistettu')->whereNull('kiinteisto.poistettu');

    	//If the user role is katselija, get only rakennukset that belong to public kiinteistot
    	if(Auth::user()->rooli == 'katselija') {
    		$qry = $qry->where('kiinteisto.julkinen', '=', true);
    	}

	    return $qry;
	}

	/**
	 * Get all public information of Models from DB - order by given $order_field to given $order_direction
	 *
	 *
	 * @param int $order_field field name we want to order results by
	 * @param int $order_direction Direction we want to order results to (ASC/DESC)
	 */
	public static function getAllPublicInformation() {

		$rak_osoitteet_sql  = "( select rakennus_id, ";
		$rak_osoitteet_sql .= "  string_agg(rakennus_osoite.katunimi || ' ' || rakennus_osoite.katunumero, '\n') as osoitteet ";
		$rak_osoitteet_sql .= "  from rakennus_osoite ";
		$rak_osoitteet_sql .= "  group by rakennus_id ";
		$rak_osoitteet_sql .= ") as rak_osoitteet ";

		$rak_raktyypit_sql  = "( select rakennus_id, ";
		$rak_raktyypit_sql .= "   string_agg(rakennustyyppi.".self::getLocalizedfieldname('nimi').", '\n') as rakennustyypit ";
		$rak_raktyypit_sql .= "   from rakennus_rakennustyyppi, rakennustyyppi ";
		$rak_raktyypit_sql .= "   where rakennus_rakennustyyppi.rakennustyyppi_id = rakennustyyppi.id ";
		$rak_raktyypit_sql .= "   group by rakennus_id ";
		$rak_raktyypit_sql .= ") as rak_rakennustyypit ";

		$rak_suunnittelijat_sql  = "( select rakennus_id, ";
 		$rak_suunnittelijat_sql .= " string_agg( suunnittelija.sukunimi || ' ' || coalesce(suunnittelija.etunimi, ''), '<br />' ) as kaikki_suunnittelijat ";
		$rak_suunnittelijat_sql .= "  from suunnittelija_rakennus ";
		$rak_suunnittelijat_sql .= "  left join suunnittelija suunnittelija on (suunnittelija.id = suunnittelija_rakennus.suunnittelija_id) ";
		$rak_suunnittelijat_sql .= "  group by rakennus_id";
		$rak_suunnittelijat_sql .= ") as rak_suunnittelijat ";

		$rak_inventointiprojektit_sql = "( select kiinteisto_id, ";
		$rak_inventointiprojektit_sql .= " string_agg(distinct inventointiprojekti.nimi, '<br >') as inventointiprojektit_str ";
		$rak_inventointiprojektit_sql .= " from inventointiprojekti, inventointiprojekti_kiinteisto, inventointiprojekti_laji ";
		$rak_inventointiprojektit_sql .= " where inventointiprojekti.id = inventointiprojekti_kiinteisto.inventointiprojekti_id ";
		$rak_inventointiprojektit_sql .= " and inventointiprojekti.laji_id = inventointiprojekti_laji.id ";
		$rak_inventointiprojektit_sql .= " and inventointiprojekti_laji.tekninen_projekti = false ";
		$rak_inventointiprojektit_sql.= "  group by kiinteisto_id ";
		$rak_inventointiprojektit_sql .= ") as rak_inventointiprojektit ";

		$qry = self::select(
				'rakennus.id', 'rakennus.rakennustunnus', 'rakennus.inventointinumero', 'rakennus.purettu', 'rakennus.nykyinen_tyyli_id',
				'rakennus.rakennustyyppi_kuvaus', 'rakennus.rakennusvuosi_alku', 'rakennus.rakennusvuosi_loppu', 'rakennus.rakennusvuosi_selite',
				'rakennus.kiinteisto_id', 'rakennus.arvotustyyppi_id', 'rakennus.rakennushistoria',
				DB::raw(MipGis::getGeometryFieldQueryString("rakennuksen_sijainti", "sijainti")),
				'arvotustyyppi.'.self::getLocalizedfieldname('nimi').' as arvotustyyppi_nimi',
				'rak_osoitteet.osoitteet',
				'rak_rakennustyypit.rakennustyypit',
		        'rak_suunnittelijat.kaikki_suunnittelijat',
				'rak_inventointiprojektit.inventointiprojektit_str',
		        'kunta.id as kunta_id',
		        'kyla.id as kyla_id',
		        'kiinteisto.nimi as kiinteisto_nimi',
		        'kiinteisto.kiinteistotunnus as kiinteisto_tunnus'
			)
			->join('kiinteisto', 'rakennus.kiinteisto_id', '=', 'kiinteisto.id')
			->join('kyla', 'kiinteisto.kyla_id', '=', 'kyla.id')
			->join('kunta', 'kyla.kunta_id', '=', 'kunta.id')
    		->leftJoin('arvotustyyppi', 'rakennus.arvotustyyppi_id', '=', 'arvotustyyppi.id')
    		->leftJoin(DB::raw($rak_osoitteet_sql), 'rakennus.id', '=', 'rak_osoitteet.rakennus_id')
    		->leftJoin(DB::raw($rak_suunnittelijat_sql), 'rakennus.id', '=', 'rak_suunnittelijat.rakennus_id')
    		->leftJoin(DB::raw($rak_inventointiprojektit_sql), 'rakennus.kiinteisto_id', '=', 'rak_inventointiprojektit.kiinteisto_id')
    		->leftJoin(DB::raw($rak_raktyypit_sql), 'rakennus.id', '=', 'rak_rakennustyypit.rakennus_id')
    		->whereNull('rakennus.poistettu')->whereNull('kiinteisto.poistettu');


    	$qry = $qry->where('kiinteisto.julkinen', '=', true);

	    return $qry;
	}

	// use only with all() above
	public function scopeWithOrder($query, $order_field=null, $order_direction=null) {

		if (is_null($order_field) && is_null($order_direction)) {
    		return $query->orderBy('kunta.nimi', $order_direction);
    	}
    	if ($order_field == "kunta") {
			return $query->orderBy('kunta.nimi', $order_direction);
		}
		if ($order_field == "kyla") {
			return $query->orderBy('kyla.nimi', $order_direction);
		}
		if ($order_field == "arvotustyyppi_nimi") {
			return $query->orderBy('arvotustyyppi.'.self::getLocalizedfieldname('nimi'), $order_direction);
		}
		if ($order_field == "kiinteistotunnus") {
			return $query->orderBy('kiinteisto.kiinteistotunnus', $order_direction);
		}
		if ($order_field == "paikkakunta") {
			return $query->orderBy('kiinteisto.paikkakunta', $order_direction);
		}
		if ($order_field == "kiinteisto_nimi") {
			return $query->orderBy('kiinteisto.nimi', $order_direction);
		}
		if ($order_field == "osoite") {
			return $query->orderBy('rak_osoitteet.osoitteet', $order_direction);
		}
		if ($order_field == "rakennustyyppi") {
			return $query->orderBy('rak_rakennustyypit.rakennustyypit', $order_direction);
		}
		if ($order_field == "kaikki_suunnittelijat") {
		    return $query->orderBy('rak_suunnittelijat.kaikki_suunnittelijat', $order_direction);
		}

		$query->orderBy('kunta.nimi', $order_direction);

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
		return Rakennus::select('rakennus.*',
				DB::raw(MipGis::getGeometryFieldQueryString("rakennuksen_sijainti", "sijainti"))
		)
/*		->join('kiinteisto', 'rakennus.kiinteisto_id', '=', 'kiinteisto.id')
		->join('kyla', 'kiinteisto.kyla_id', '=', 'kyla.id')
		->join('kunta', 'kyla.kunta_id', '=', 'kunta.id')
		->addSelect('kunta.nimi as kunta','kyla.nimi as kyla')
*/
		->where('rakennus.id', '=', $id);
	}

	/**
	 * Method to get the Estate that this buiding belongs to
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function estate() {
		return $this->belongsTo('App\Rak\Kiinteisto', 'kiinteisto_id');
	}

	/**
	 * Method to get the Staircases of the building
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function staircases() {
		return $this->hasMany('App\Rak\Porrashuone', 'rakennus_id', 'id');
	}

	/**
	 * Method to get kulttuurihistoriallisetarvot of the estate
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function kulttuurihistoriallisetarvot() {
		return $this->belongsToMany('App\Rak\RakennuksenKulttuurihistoriallinenArvo', 'rakennus_rakennuskulttuurihistoriallinenarvo', 'rakennus_id', 'kulttuurihistoriallinenarvo_id');
	}

	/**
	 * Method to get the Designers of the building
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function suunnittelijat() {
	/*
		 * TODO: This returns suunnittelija.id as string, but if we say suunnittelija.id as suunnittelija_id, the value becomes string.
		 * Currently all of the other id's are returned as string also. (suunnittelija_tyyppi_id ...)
		 */
		return $this->hasMany('App\Rak\SuunnittelijaRakennus');
		//return $this->belongsToMany('App\Rak\Suunnittelija', 'suunnittelija_rakennus');
		//	->select('suunnittelija.id as suunnittelija_id', 'lisatieto', 'suunnitteluvuosi_alku', 'suunnitteluvuosi_loppu', 'suunnittelija_rakennus.suunnittelija_tyyppi_id',
		//			'suunnittelija.etunimi', 'suunnittelija.sukunimi', 'suunnittelija.suunnittelija_ammattiarvo_id','suunnittelija.suunnittelija_laji_id')->with(array('laji', 'ammattiarvo'));
//			->leftJoin('suunnittelija_tyyppi', 'suunnittelija_tyyppi_id', '=', 'suunnittelija_tyyppi.id')
//			->addSelect('suunnittelija_tyyppi as suunnittelija_tyyppi');
	}

	/**
	 * Kaikki rakennuksen suunnittelijat
	 */
	public function kaikki_suunnittelijat() {
	    return $this->belongsToMany('App\Rak\Suunnittelija', 'suunnittelija_rakennus');
	}

	/**
	 * Method to get the kuntotyyppi (kunto)
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function kuntotyyppi() {
		return $this->belongsTo('App\Rak\Kuntotyyppi');
	}

	/**
	 * Method to get the arvotustyyppi (arvotus)
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function arvotustyyppi() {
		return $this->belongsTo('App\Rak\Arvotustyyppi');
	}

	/**
	 * Method to get the types of building
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function rakennustyypit() {
		return $this->belongsToMany('App\Rak\Rakennustyyppi', 'rakennus_rakennustyyppi', 'rakennus_id', 'rakennustyyppi_id');
	}

	/**
	 * Method to get the "katto" types of building
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function kattotyypit() {
		return $this->belongsToMany('App\Rak\Kattotyyppi', 'rakennus_kattotyyppi', 'rakennus_id', 'kattotyyppi_id');
	}

	/**
	 * Method to get the "vuoraus" types of building
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function vuoraustyypit() {
		return $this->belongsToMany('App\Rak\Vuoraustyyppi', 'rakennus_vuoraustyyppi', 'rakennus_id', 'vuoraustyyppi_id');
	}

	/**
	 * Method to get the "kate" types of building
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function katetyypit() {
		return $this->belongsToMany('App\Rak\Katetyyppi', 'rakennus_katetyyppi', 'rakennus_id', 'katetyyppi_id');
	}

	/**
	 * Method to get the "runko" types of building
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function runkotyypit() {
		return $this->belongsToMany('App\Rak\Runkotyyppi', 'rakennus_runkotyyppi', 'rakennus_id', 'runkotyyppi_id');
	}

	/**
	 * Method to get all the alkuperainen kaytto
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function alkuperaisetkaytot() {
		return $this->belongsToMany('App\Rak\Kayttotarkoitus', 'rakennus_alkuperainenkaytto', 'rakennus_id', 'kayttotarkoitus_id');
	}

	/**
	 * Method to get all the nykykaytto
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function nykykaytot() {
		return $this->belongsToMany('App\Rak\Kayttotarkoitus', 'rakennus_nykykaytto', 'rakennus_id', 'kayttotarkoitus_id');
	}

	/**
	 * Method to get all the perustustyypit
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function perustustyypit() {
		return $this->belongsToMany('App\Rak\Perustustyyppi', 'rakennus_perustustyyppi', 'rakennus_id', 'perustustyyppi_id');
	}

	public function kiinteisto() {
		return $this->belongsTo('App\Rak\Kiinteisto');
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
		return $query->whereRaw(MipGis::getGeometryFieldBoundingBoxQueryWhereString("rakennuksen_sijainti", $bbox));
	}

	public function scopeWithPolygon($query, $polygon) {
		return $query->whereRaw(MipGis::getGeometryFieldPolygonQueryWhereString($polygon, "rakennuksen_sijainti"));
	}
	/**
	 * Add order by by bounding box
	 *
	 * @param  $query
	 * @param String $bbox The bounding box value (21.900000 60.910000,22.000000 61.000000)
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithBoundingBoxOrder($query, $bbox) {
		return $query->orderByRaw(MipGis::getGeometryFieldOrderByBoundingBoxCenterString("rakennuksen_sijainti", $bbox));
	}

	/**
	 * Limit results to Entities which matches the given keyword for buildingtype
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithBuildingTypeId($query, $keyword) {
		// lets create a subquery
	    $keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.id', function($q) use ($keyword){
			$q->select('rakennus_id')
			->from('rakennus_rakennustyyppi')
			->join('rakennustyyppi', 'rakennustyyppi.id', '=', 'rakennus_rakennustyyppi.rakennustyyppi_id')
			->whereNull('rakennustyyppi.poistettu')
			->where(function($query) use ($keyword) {
			    return $query->whereIn('rakennustyyppi.id', $keyword);
			});
		});
	}



	/**
	 * Haku rakennuksen suunnittelijan mukaan.
	 */
	public function scopeWithDesigner($query, $keyword) {
		return $query->join('suunnittelija_rakennus', 'suunnittelija_rakennus.rakennus_id', '=', 'rakennus.id')
			->join('suunnittelija', 'suunnittelija.id', '=', 'suunnittelija_rakennus.suunnittelija_id')
			->where("suunnittelija.id", "=", $keyword);
	}

	/**
	 * Limit results to Entities which matches the given keyword for estatename
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithEstateName($query, $keyword) {
		return $query->where("kiinteisto.nimi", "ILIKE", "%".$keyword."%");
	}

	/**
	 * Limit results to only for those rows which estate identifier values matches the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithEstateIdentifier($query, $keyword) {
		return $query->where('kiinteisto.kiinteistotunnus', 'ILIKE', "%".$keyword."%");
	}

	public function scopeWithPaikkakunta($query, $keyword) {
		return $query->where('kiinteisto.paikkakunta', 'ILIKE', "%".$keyword."%");
	}

	/**
	 * Limit the results by the given building id
	 *
	 * @param  $query
	 * @param int $id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithID($query, $id) {
		return $query->where('rakennus.id', '=', $id);
	}

	/**
	 * Lmit results to Entities which matches the given keywords for inventoring number
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithInventoringNumber($query, $keyword) {
		return $query->where("rakennus.inventointinumero" , "ILIKE", $keyword.'%');
	}

	/**
	 * Lmit results to Entities which matches the given keywords for building identifier
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithBuildingIdentifier($query, $keyword) {
		return $query->where("rakennus.rakennustunnus" , "ILIKE", $keyword.'%');
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
	 * Limit results to only for those rows which MUNICIPALITY matches the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithMunicipality($query, $keyword) {
	    if(\App::getLocale()=="se"){
	        return $query->where('kunta.nimi_se', 'ILIKE', $keyword . "%")
	        ->orWhere('kunta.nimi', 'ILIKE', $keyword . "%");
	    }
	    return $query->where('kunta.nimi', 'ILIKE', $keyword . "%");
		//->orWhere('kunta.nimi_se', 'ILIKE', $keyword . "%");
	}

	/**
	 * Limit results to only for those rows which MUNICIPALITY NUMBER matches the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithMunicipalityNumber($query, $keyword) {
		return $query->where('kunta.kuntanumero', '=', $keyword);
	}

	public function scopeWithKuntaId($query, $id) {
		return $query->where('kunta.id', '=', $id);
	}

	/**
	 * Limit results to only for those rows which NAME values matches the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithName($query, $keyword) {
		return $query->where('kiinteisto.nimi', 'ILIKE', "%".$keyword."%");
	}

	public function scopeWithPalstanumero($query, $keyword) {
	    return $query->where('kiinteisto.palstanumero', '=', $keyword);
	}

	public function scopeWithAddress($query, $keyword) {

		//Extract the numbers from the string to variable $number. Returns array
		preg_match_all('!\d+!', $keyword, $number);

		//Extract the alphabets from the string then replace the space with nothing
		$char = preg_replace("/[0-9]/", "", $keyword);
		$streetName = str_replace(" ", "", $char);

		$streetNum = implode(" ", $number[0]);

		// Search from the kiinteisto.osoite with the whole $keyword and from the rakennus_osoite table as alphabets and numbers separated
		$query->whereIn('rakennus.id', function($q) use ($streetName, $streetNum){

			$q->select('rakennus_id')->from('rakennus_osoite');

			if(strLen($streetName) > 0) {
				$q->where('rakennus_osoite.katunimi', 'ILIKE', $streetName ."%");
			}
			if(strlen($streetNum) > 0) {
				$q->where('rakennus_osoite.katunumero', 'ILIKE', $streetNum."%");
			}
		});

		$query->orWhere('kiinteisto.osoite', 'ILIKE', '%' . $keyword .'%');

		return $query;
	}

	/**
	 * Limit results to only for those rows whcih ADDRESS matches the given keyword
	 *
	 * @param  $query
	 * @param string $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithPropertyOrEstateAddress($query, $keyword) {

		//Extract the numbers from the string to variable $number. Returns array
		preg_match_all('!\d+!', $keyword, $number);

		//Extract the alphabets from the string then replace the space with nothing
		$char = preg_replace("/[0-9]/", "", $keyword);
		$streetName = str_replace(" ", "", $char);

		$streetNum = implode(" ", $number[0]);

		// Search from the kiinteisto.osoite with the whole $keyword and from the rakennus_osoite table as alphabets and numbers separated
		$query->whereIn('rakennus.id', function($q) use ($streetName, $streetNum){

			$q->select('rakennus_id')->from('rakennus_osoite');

			if(strLen($streetName) > 0) {
				$q->where('rakennus_osoite.katunimi', 'ILIKE', $streetName ."%");
			}
			if(strlen($streetNum) > 0) {
				$q->where('rakennus_osoite.katunumero', 'ILIKE', $streetNum."%");
			}
		});

		$query->orWhere('kiinteisto.osoite', 'ILIKE', $keyword."%");

		return $query;
	}

	/**
	 * Limit results to only for those whose rakennus.kiinteisto_id matches to given keyword
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithPropertyID($query, $keyword) {
		return $query->where("rakennus.kiinteisto_id", "=", $keyword);
	}

	/**
	 * Limit results to only for those rows which VILLAGE values matches the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithVillage($query, $keyword){
		return $query->where('kyla.nimi', 'ILIKE', "%" . $keyword."%");
				//->orWhere('kyla.nimi_se', 'ILIKE', "%" . $keyword . "%");
	}

	/**
	 * Limit results to only for those rows which VILLAGE NUMBER values matches the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithVillageNumber($query, $keyword){
		return $query->where('kyla.kylanumero', '=', $keyword);
	}


	/**
	 * Limit results to only for those rows which VILLAGE ID value matches the given keyword
	 *
	 * @param  $query
	 * @param  $kylaId
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithVillageId($query, $kylaId){
		return $query->where('kyla.id', '=', $kylaId);
	}

	public function scopeWithInventointiprojektiOrInventoija($query, $inventointiprojekti_id, $inventoija_id) {
		if(($inventointiprojekti_id && $inventointiprojekti_id != 'null') || ($inventoija_id && $inventoija_id != 'null')) {
		    $query->join('inventointiprojekti_kiinteisto', 'inventointiprojekti_kiinteisto.kiinteisto_id' , '=', 'kiinteisto.id')
		          ->whereNull('inventointiprojekti_kiinteisto.poistettu');

			if($inventointiprojekti_id && $inventointiprojekti_id != 'null') {
				$query->where('inventointiprojekti_kiinteisto.inventointiprojekti_id', '=', $inventointiprojekti_id)
				->groupBy([
						'rakennus.id', 'arvotustyyppi.'.self::getLocalizedfieldname("nimi"), 'rak_osoitteet.osoitteet', 'rak_rakennustyypit.rakennustyypit',
						'rak_suunnittelijat.kaikki_suunnittelijat', 'kunta.id', 'kyla.id', 'rak_inventointiprojektit.inventointiprojektit_str',
						'kiinteisto.nimi', 'kiinteisto.kiinteistotunnus'
				]);
			}
			if($inventoija_id && $inventoija_id != 'null') {
				$query->where('inventointiprojekti_kiinteisto.inventoija_id', '=', $inventoija_id);
			}
		}
		return $query;
	}

	public function scopeWithArvotustyyppiId($query, $keyword) {
	    $keyword = explode(',' , $keyword);
	    return $query->whereIn('rakennus.arvotustyyppi_id', function($q) use ($keyword) {
	        $q->select('id')
	        ->from('arvotustyyppi')
	        ->whereNull('arvotustyyppi.poistettu')
	        ->where(function($query) use ($keyword) {
	            return $query->whereIn('arvotustyyppi.id', $keyword);
	        });
	    });
	}

	public function scopeWithLuoja($query, $luojaId) {
		return $query->where('rakennus.luoja', '=', $luojaId);
	}

	public function scopeWithRakennustyypin_kuvaus($query, $keyword) {
		return $query->where("rakennus.rakennustyyppi_kuvaus" , "ILIKE", $keyword.'%');
	}
	// Haku rakennusvuoden alkamisvuoden mukaan. Alkuvuosi osuttava annettuun väliin.
	public function scopeWithRakennusvuosi_aikajakso($query, $alkuvuosi, $paatosvuosi) {
	    $query->where('rakennus.rakennusvuosi_alku', '>=', $alkuvuosi)
	    ->where('rakennus.rakennusvuosi_alku', '<=', $paatosvuosi);

	    return $query;
	}

	public function scopeWithRakennusvuosi_alku($query, $keyword) {
		return $query->where("rakennus.rakennusvuosi_alku" , "=", $keyword);
	}
	public function scopeWithRakennusvuosi_lopetus($query, $keyword) {
		return $query->where("rakennus.rakennusvuosi_loppu" , "=", $keyword);
	}
	public function scopeWithMuutosvuosi_alku($query, $keyword) {
		return $query->whereIn('rakennus.id', function($q) use ($keyword) {
			$q->select('rakennus_id')
				->from('rakennus_muutosvuosi')
				->where('rakennus_muutosvuosi.alkuvuosi', '>=', $keyword);
		});
	}
	public function scopeWithMuutosvuosi_lopetus($query, $keyword) {
		return $query->whereIn('rakennus.id', function($q) use ($keyword) {
			$q->select('rakennus_id')
				->from('rakennus_muutosvuosi')
				->where('rakennus_muutosvuosi.loppuvuosi', '<=', $keyword);
		});
	}
	public function scopeWithRakennusvuosi_kuvaus($query, $keyword) {
		return $query->where("rakennus.rakennusvuosi_selite" , "ILIKE", $keyword.'%');
	}
	public function scopeWithMuutosvuosi_kuvaus($query, $keyword) {
		return $query->whereIn('rakennus.id', function($q) use ($keyword) {
			$q->select('rakennus_id')
			->from('rakennus_muutosvuosi')
			->where('rakennus_muutosvuosi.selite', 'ILIKE', $keyword.'%');
		});
	}
	public function scopeWithAlkuperainenkaytto($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.id', function($q) use ($keyword){
			$q->select('rakennus_id')
			->from('rakennus_alkuperainenkaytto')
			->join('kayttotarkoitus', 'kayttotarkoitus.id', '=', 'rakennus_alkuperainenkaytto.kayttotarkoitus_id')
			->whereNull('kayttotarkoitus.poistettu')
			->where(function($query) use ($keyword) {
				return $query->whereIn('kayttotarkoitus.id', $keyword);
			});
		});
	}
	public function scopeWithNykykaytto($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.id', function($q) use ($keyword){
			$q->select('rakennus_id')
			->from('rakennus_nykykaytto')
			->join('kayttotarkoitus', 'kayttotarkoitus.id', '=', 'rakennus_nykykaytto.kayttotarkoitus_id')
			->whereNull('kayttotarkoitus.poistettu')
			->where(function($query) use ($keyword) {
				return $query->whereIn('kayttotarkoitus.id', $keyword);
			});
		});
	}
	public function scopeWithPerustus($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.id', function($q) use ($keyword){
			$q->select('rakennus_id')
			->from('rakennus_perustustyyppi')
			->join('perustustyyppi', 'perustustyyppi.id', '=', 'rakennus_perustustyyppi.perustustyyppi_id')
			->whereNull('perustustyyppi.poistettu')
			->where(function($query) use ($keyword) {
				return $query->whereIn('perustustyyppi.id', $keyword);
			});
		});
	}
	public function scopeWithRunko($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.id', function($q) use ($keyword){
			$q->select('rakennus_id')
			->from('rakennus_runkotyyppi')
			->join('runkotyyppi', 'runkotyyppi.id', '=', 'rakennus_runkotyyppi.runkotyyppi_id')
			->whereNull('runkotyyppi.poistettu')
			->where(function($query) use ($keyword) {
				return $query->whereIn('runkotyyppi.id', $keyword);
			});
		});
	}
	public function scopeWithVuoraus($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.id', function($q) use ($keyword){
			$q->select('rakennus_id')
			->from('rakennus_vuoraustyyppi')
			->join('vuoraustyyppi', 'vuoraustyyppi.id', '=', 'rakennus_vuoraustyyppi.vuoraustyyppi_id')
			->whereNull('vuoraustyyppi.poistettu')
			->where(function($query) use ($keyword) {
				return $query->whereIn('vuoraustyyppi.id', $keyword);
			});
		});
	}
	public function scopeWithKatto($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.id', function($q) use ($keyword){
			$q->select('rakennus_id')
			->from('rakennus_kattotyyppi')
			->join('kattotyyppi', 'kattotyyppi.id', '=', 'rakennus_kattotyyppi.kattotyyppi_id')
			->whereNull('kattotyyppi.poistettu')
			->where(function($query) use ($keyword) {
				return $query->whereIn('kattotyyppi.id', $keyword);
			});
		});
	}
	public function scopeWithKate($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.id', function($q) use ($keyword){
			$q->select('rakennus_id')
			->from('rakennus_katetyyppi')
			->join('katetyyppi', 'katetyyppi.id', '=', 'rakennus_katetyyppi.katetyyppi_id')
			->whereNull('katetyyppi.poistettu')
			->where(function($query) use ($keyword) {
				return $query->whereIn('katetyyppi.id', $keyword);
			});
		});
	}
	public function scopeWithKunto($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.kuntotyyppi_id', $keyword);
	}
	public function scopeWithNykytyyli($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.nykyinen_tyyli_id', $keyword);
	}
	public function scopeWithPurettu($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.purettu', $keyword);
	}
	public function scopeWithKulttuurihistorialliset_arvot($query, $keyword) {
		$keyword = explode(',' , $keyword);
		return $query->whereIn('rakennus.id', function($q) use ($keyword){
			$q->select('rakennus_id')
			->from('rakennus_rakennuskulttuurihistoriallinenarvo')
			->join('rakennuskulttuurihistoriallinenarvo', 'rakennuskulttuurihistoriallinenarvo.id', '=', 'rakennus_rakennuskulttuurihistoriallinenarvo.kulttuurihistoriallinenarvo_id')
			->whereNull('rakennuskulttuurihistoriallinenarvo.poistettu')
			->where(function($query) use ($keyword) {
				return $query->whereIn('rakennuskulttuurihistoriallinenarvo.id', $keyword);
			});
		});
	}
	public function scopeWithKuvaukset($query, $keyword) {
		//$keyword = explode(',' , $keyword);
		return $query->where('rakennus.rakennushistoria', 'LIKE', '%'.$keyword.'%')
					 ->orWhere('rakennus.erityispiirteet', 'LIKE', '%'.$keyword.'%')
					 ->orWhere('rakennus.sisatilakuvaus', 'LIKE', '%'.$keyword.'%')
					 ->orWhere('rakennus.muut_tiedot', 'LIKE', '%'.$keyword.'%');
	}
	/*
	 * Haku rakennusten id listalla. Koritoiminnallisuus käyttää
	 */
	public function scopeWithRakennusIdLista($query, $keyword) {
	    return $query->whereIn('rakennus.id', $keyword);
	}

}
