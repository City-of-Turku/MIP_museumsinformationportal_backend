<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App;

class Porrashuone extends Model {

	use SoftDeletes;

	protected $table = "porrashuone";

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = [
	];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	*/
	protected $fillable = [
			'rakennus_id',
			'huoneistojen_maara',
			'porrashuonetyyppi.id',
			'portaiden_muoto',
			'kattoikkuna',
			'hissi',
			'hissin_kuvaus',
			'yleiskuvaus',
			'sisaantulokerros',
			'ovet_ja_ikkunat',
			'portaat_tasanteet_kaiteet',
			'pintamateriaalit',
			'muu_kiintea_sisustus',
			'talotekniikka',
			'tehdyt_korjaukset',
			'esteettomyys',
			'lisatiedot',
			'porrashuoneen_tunnus'
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
		return $this->belongsToMany('App\Rak\Kuva', 'kuva_porrashuone');
	}

	public function files() {
		return $this->belongsToMany('App\Tiedosto', 'tiedosto_porrashuone');
	}

	public function rakennus() {
		return $this->belongsTo('App\Rak\Rakennus');
	}

	public function luoja() {
		return $this->belongsTo('App\Kayttaja', 'luoja');
	}

	public function muokkaaja() {
		return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}

	public function porrashuonetyyppi() {
		return $this->belongsTo('App\Rak\Porrashuonetyyppi', 'porrashuonetyyppi_id');
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

   /** Get all Models from DB - order by given $order_field to given $order_direction
	 *
	 * @param int $order_field field name we want to order results by
	 * @param int $order_direction Direction we want to order results to (ASC/DESC)
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getAll() {
		$order_table = "porrashuone";

		//OSOITTEET: Rakennusten osoitteet saa mukaan kopioimalla logiikan Rakennus.php
		//getAll() metodista.
		//Toisaalta osoitteiden luulisi tulevan mukaan hienosti käyttämällä Kiinteisto.php:ssa
		//olevaa rakennusOsoitteet metodia samalla lailla tässä tiedostossa, mutta ei.
		//Rakennusksen osoitteita yritetään hakea virheellisesti porrashuoneen IDllä jostain syystä.

		$rak_raktyypit_sql  = "( select rakennus_id, ";
		$rak_raktyypit_sql .= "   string_agg(rakennustyyppi.".self::getLocalizedfieldname('nimi').", '\n') as rakennustyypit ";
		$rak_raktyypit_sql .= "   from rakennus_rakennustyyppi, rakennustyyppi ";
		$rak_raktyypit_sql .= "   where rakennus_rakennustyyppi.rakennustyyppi_id = rakennustyyppi.id ";
		$rak_raktyypit_sql .= "   group by rakennus_id ";
		$rak_raktyypit_sql .= ") as rak_rakennustyypit ";

		$qry =  self::select('porrashuone.id', 'porrashuone.porrashuonetyyppi_id','porrashuone.porrashuoneen_tunnus as tunnus', 'porrashuone.luoja',
			'kunta.nimi as kunta', 'kunta.nimi_se as kunta_se', 'kyla.nimi as kyla', 'kyla.kylanumero as kylanumero', 'kiinteisto.nimi as kiinteisto_nimi', 'kiinteisto.kiinteistotunnus', 'kiinteisto.palstanumero as kiinteisto_palstanumero', 'kiinteisto.osoite as kiinteisto_osoite',
				'rakennus.inventointinumero as rakennus_inventointinumero',
				'rak_rakennustyypit.rakennustyypit', 'porrashuone.rakennus_id', 'porrashuonetyyppi.'.self::getLocalizedfieldname('nimi').' as porrashuonetyyppi'
			)
			->join('rakennus', 'rakennus.id', '=', 'porrashuone.rakennus_id')
			->join('kiinteisto', 'kiinteisto.id', '=', 'rakennus.kiinteisto_id')
			->join('kyla', 'kyla.id', '=', 'kiinteisto.kyla_id')
			->join('kunta', 'kunta.id', '=', 'kyla.kunta_id')
			->leftJoin(DB::raw($rak_raktyypit_sql), 'porrashuone.rakennus_id', '=', 'rak_rakennustyypit.rakennus_id')
			->leftJoin('porrashuonetyyppi', 'porrashuonetyyppi.id', '=', 'porrashuone.porrashuonetyyppi_id')
			->groupBy('porrashuone.id', 'kunta.nimi', 'kunta_se', 'kyla.nimi', 'kyla.kylanumero', 'kiinteisto.nimi', 'kiinteisto.kiinteistotunnus', 'kiinteisto.osoite', 'rakennus.inventointinumero', 'rak_rakennustyypit.rakennustyypit', 'kiinteisto.palstanumero', 'porrashuonetyyppi.'. self::getLocalizedfieldname('nimi'));

		//If the user role is katselija, get only rakennukset that belong to public kiinteistot
		if(Auth::user()->rooli == 'katselija') {
			$qry = $qry->where('kiinteisto.julkinen', '=', true);
		}

		return $qry;
	}

	public function rakennusOsoitteet() {
		return $this->hasManyThrough('App\Rak\RakennusOsoite', 'App\Rak\Rakennus', 'id', 'rakennus_id');
	}

	public function scopeWithOrder($query, $order_field=null, $order_direction=null) {

		if(is_null($order_field) && is_null($order_direction)) {
			return $query->orderBy('kunta.nimi', 'asc');
		}

		if($order_field == "kunta") {
			return $query->orderBy('kunta.nimi', $order_direction);
		}
		if($order_field == "kyla") {
			return $query->orderBy('kyla.nimi', $order_direction);
		}
		if($order_field == "kiinteisto_nimi") {
			return $query->orderBy('kiinteisto.nimi', $order_direction);
		}
		if($order_field == "kiinteistotunnus") {
			return $query->orderBy('kiinteisto.kiinteistotunnus', $order_direction);
		}
		if($order_field == "kiinteisto_osoite") {
			return $query->orderBy('kiinteisto.osoite', $order_direction);
		}
		if($order_field == "rakennus_inventointinumero") {
			return $query->orderBy('rakennus.inventointinumero', $order_direction);
		}
		if ($order_field == "rakennus_tyyppi") {
			return $query->orderBy('rak_rakennustyypit.rakennustyypit', $order_direction);
		}
		if($order_field == 'porrashuonetyyppi') {
			return $query->orderBy('porrashuonetyyppi.'.self::getLocalizedfieldname('nimi'), $order_direction);
		}
		if($order_field == 'porrashuoneentunnus') {
			return $query->orderBy('porrashuone.porrashuoneen_tunnus', $order_direction);
		}
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
	 * Limit the results to entities which matches the given keyword for designer of staircase
	 *
	 * @param $query
	 * @param string $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithDesigner($query, $keyword) {
		return $query->join('suunnittelija_porrashuone', 'suunnittelija_porrashuone.porrashuone_id', '=', 'porrashuone.id')
			->join('suunnittelija', 'suunnittelija.id', '=', 'suunnittelija_porrashuone.suunnittelija_id')
			->where("suunnittelija.etunimi", "ILIKE", $keyword."%")
			->orWhere("suunnittelija.sukunimi", "ILIKE", $keyword."%")
			->orWhere(DB::raw("CONCAT(suunnittelija.etunimi, ' ', suunnittelija.sukunimi)"), "ILIKE", $keyword."%")
			->orWhere(DB::raw("CONCAT(suunnittelija.sukunimi, ' ', suunnittelija.etunimi)"), "ILIKE", $keyword."%");
	}

	public function scopeWithID($query, $id){
		return $query->where('porrashuone.id', '=', $id);
	}

	public function scopeWithKuntaNimi($query, $keyword) {
		if(\App::getLocale()=="se"){
		    return $query->where('kunta.nimi_se', 'ILIKE', $keyword . "%")
		    ->orWhere('kunta.nimi', 'ILIKE', $keyword . "%");
		}
		return $query->where('kunta.nimi', 'ILIKE', $keyword . "%");
		//->orWhere('kunta.nimi_se', 'ILIKE', $keyword . "%");
	}

	public function scopeWithKuntaNumero($query, $keyword) {
		return $query->where('kunta.kuntanumero', 'ILIKE', $keyword);
	}
	public function scopeWithKuntaId($query, $keyword) {
	    return $query->where('kunta.id', '=', $keyword);
	}

	public function scopeWithKylaNimi($query, $keyword) {
		return $query->where('kyla.nimi', 'ILIKE', "%" . $keyword . "%");
		//->orWhere('kyla.nimi_se', 'ILIKE',  "%" . $keyword . "%");
	}

	public function scopeWithKylaNumero($query, $keyword) {
		return $query->where('kyla.kylanumero', 'ILIKE', $keyword);
	}
	public function scopeWithKylaId($query, $keyword) {
	    return $query->where('kyla.id', '=', $keyword);
	}

	public function scopeWithKiinteistoNimi($query, $keyword) {
		return $query->where('kiinteisto.nimi', 'ILIKE', "%".$keyword."%");
	}
	public function scopeWithKiinteistotunnus($query, $keyword) {
		return $query->where('kiinteisto.kiinteistotunnus', 'ILIKE', "%".$keyword."%");
	}

	public function scopeWithPalstanumero($query, $keyword) {
	    return $query->where('kiinteisto.palstanumero', '=', $keyword);
	}

	public function scopeWithKiinteistoOsoite($query, $keyword) {
		return $query->where('kiinteisto.osoite', 'ILIKE', "%".$keyword."%");
	}

	public function scopeWithRakennustyyppi($query, $keyword) {
		return $query->where('rakennustyyppi.nimi_fi', 'ILIKE', "%".$keyword."%");
	}

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

	public function scopeWithPorrashuonetyyppiId($query, $keyword) {
		// lets create a subquery
		$keyword = explode(',' , $keyword);

		return $query->whereIn('porrashuone.porrashuonetyyppi_id', function($q) use ($keyword) {
			$q->select('id')
			->from('porrashuonetyyppi')
			->whereNull('porrashuonetyyppi.poistettu')
			->where(function($query) use ($keyword) {
				return $query->whereIn('porrashuonetyyppi.id', $keyword);
			});
		});
	}


	public function scopeWithRakennusInventointinumero($query, $keyword) {
		return $query->where('rakennus.inventointinumero', '=', $keyword);
	}

	public function scopeWithTunnus($query, $keyword) {
		return $query->where('porrashuone.porrashuoneen_tunnus', 'ILIKE', "%".$keyword."%");
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

	public function scopeWithLuoja($query, $luojaId) {
		return $query->where('porrashuone.luoja', '=', $luojaId);
	}

}
