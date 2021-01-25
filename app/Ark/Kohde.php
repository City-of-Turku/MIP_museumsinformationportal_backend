<?php

namespace App\Ark;

use App\Utils;
use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Kohde extends Model {

	use SoftDeletes;

	protected $table = "ark_kohde";

	/** fillable elements, otherwise we will get MassAssignemntException **/

	protected $fillable = array(
			'muinaisjaannostunnus', 'nimi', 'muutnimet', 'maakuntanimi',
			'tyhja', 'tuhoutumissyy_id', 'tuhoutumiskuvaus',
			'virallinenkatselmus', 'mahdollisetseuraamukset', 'tarkenne',
			'jarjestysnumero', 'ark_kohdelaji_id', 'vedenalainen',
			'suojelukriteeri', 'rauhoitusluokka_id', 'lukumaara',
			'haaksirikkovuosi', 'alkuperamaa', 'alkuperamaanperustelu',
			'koordselite', 'etaisyystieto', 'korkeus_min', 'korkeus_max',
			'syvyys_min', 'syvyys_max', 'peruskarttanumero', 'peruskarttanimi',
			'koordinaattijarjestelma', 'sijainti_ei_tiedossa',
			'alkuperaisyys_id', 'rajaustarkkuus_id', 'maastomerkinta_id', 'kunto_id',
			'hoitotarve_id', 'huomautus', 'lahteet', 'avattu', 'avaaja',
			'muutettu', 'muuttaja', 'yllapitoorganisaatiotunnus', 'yllapitoorganisaatio',
			'julkinenurl', 'viranomaisurl', 'kuvaus', 'taustatiedot', 'havainnot',
			'tulkinta', 'lisatiedot', 'rajattu', 'vaatii_tarkastusta', 'tarkastus_muistiinpano', 'kyppi_status',
			'luotu', 'luoja', 'muokattu', 'muokkaaja', 'poistettu', 'poistaja'
	);

	/**
	 * Piilotetaan palautettavista tiedoista sijainti, palautetaan se erikseen.
	 */
	protected $hidden = ['sijainti'];

	/**
	 * Laravel ei ymmärrä kaikkia SQL-tyyppejä. Castataan kentät, jotta erityisesti doublet menevät oikein.
	 */
	protected $casts = ['korkeus_min' => 'double', 'korkeus_max' => 'double', 'syvyys_min' => 'double', 'syvyys_max' => 'double'];

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



	public static function getSingle($id) {
		return self::select('ark_kohde.*')->where('id', '=', $id);
	}

	public static function getAll() {
		$kuntakyla_sql  = "( select ark_kohde_id, ";
		$kuntakyla_sql .= "  string_agg(ku.nimi, '\n') as kunnat, string_agg(ky.nimi, '\n') as kylat ";
		$kuntakyla_sql .= "  from ark_kohde_kuntakyla akk, kunta ku, kyla ky ";
		$kuntakyla_sql .= "  where akk.kunta_id = ku.id ";
		$kuntakyla_sql .= "  and akk.kyla_id = ky.id ";
		$kuntakyla_sql .= "  group by ark_kohde_id ";
		$kuntakyla_sql .= ") as kohde_kuntakyla ";

		$tyyppi_sql  = "( select akt.ark_kohde_id, ";
		$tyyppi_sql .= "  string_agg(kt.nimi_fi, '\n') as tyypit_fi, string_agg(kt.nimi_se, '\n') as tyypit_se ";
		$tyyppi_sql .= "  from ark_kohde_tyyppi akt, ark_kohdetyyppi kt ";
		$tyyppi_sql .= "  where akt.tyyppi_id = kt.id ";
		$tyyppi_sql .= "  group by akt.ark_kohde_id ";
		$tyyppi_sql .= ") as kohde_tyypit ";

		$tyyppitarkenne_sql  = "( select akt.ark_kohde_id, ";
		$tyyppitarkenne_sql .= "  string_agg(ktt.nimi_fi, '\n') as tyyppitarkenteet_fi, string_agg(ktt.nimi_se, '\n') as tyyppitarkenteet_se ";
		$tyyppitarkenne_sql .= "  from ark_kohde_tyyppi akt, ark_kohdetyyppitarkenne ktt ";
		$tyyppitarkenne_sql .= "  where akt.tyyppitarkenne_id = ktt.id ";
		$tyyppitarkenne_sql .= "  group by akt.ark_kohde_id ";
		$tyyppitarkenne_sql .= ") as kohde_tyyppitarkenteet ";

		$ajoitus_sql  = "( select aka.ark_kohde_id, ";
		$ajoitus_sql .= "  string_agg(a.nimi_fi, '\n') as ajoitukset_fi, string_agg(a.nimi_se, '\n') as ajoitukset_se ";
		$ajoitus_sql .= "  from ark_kohde_ajoitus aka, ajoitus a ";
		$ajoitus_sql .= "  where aka.ajoitus_id = a.id ";
		$ajoitus_sql .= "  group by aka.ark_kohde_id ";
		$ajoitus_sql .= ") as kohde_ajoitukset ";

		$qry = Kohde::select("ark_kohde.*")
		/* seuraavat ovat mukana vain sorttaustarkoituksessa */
		->leftJoin('ark_kohdelaji', 'ark_kohde.ark_kohdelaji_id', '=', 'ark_kohdelaji.id')
		->leftJoin(DB::raw($kuntakyla_sql), 'ark_kohde.id', '=', 'kohde_kuntakyla.ark_kohde_id')
		->leftJoin(DB::raw($tyyppi_sql), 'ark_kohde.id', '=', 'kohde_tyypit.ark_kohde_id')
		->leftJoin(DB::raw($tyyppitarkenne_sql), 'ark_kohde.id', '=', 'kohde_tyyppitarkenteet.ark_kohde_id')
		->leftJoin(DB::raw($ajoitus_sql), 'ark_kohde.id', '=', 'kohde_ajoitukset.ark_kohde_id');

		/* todo:
		 * 	kiinteisto (kiinteistotunnus)
		 */
		return $qry;
	}

	/**
	 * Hakee kohteet polygonin sisältä.
	 * @param String $polygon LatLon string array.
	 */
	public static function haeAlueenKohteet($polygon) {

	    return Kohde::select("ark_kohde.*")->leftJoin('ark_kohde_sijainti', 'ark_kohde.id', '=', 'ark_kohde_sijainti.kohde_id')
	    ->whereRaw(MipGis::getGeometryFieldPolygonQueryWhereString($polygon, "ark_kohde_sijainti.sijainti", "ark_kohde_sijainti.sijainti"));
	}

	public function scopeWithOrderBy($query, $bbox=null, $order_field=null, $order_direction=null) {
		// Sijainti ei ole sijainti-kentässä, vaan ark_kohde_sijainti taulussa (mahdollisesti useita rivejä)
		if ($order_field == "bbox_center" && !is_null($bbox)) {
			return $query->orderByRaw(MipGis::getGeometryFieldOrderByBoundingBoxCenterString("ark_kohde_sijainti.sijainti", $bbox));
		}

		$order_table = "ark_kohde";

		if (is_null($order_field) && is_null($order_direction)) {
			$order_table = "kohde_kuntakyla";
			$order_field = "kunnat";
		} else if ($order_field == "kunta") {
			$order_table = "kohde_kuntakyla";
			$order_field = "kunnat";
		} else if ($order_field == "kyla") {
			$order_table = "kohde_kuntakyla";
			$order_field = "kylat";
		} else if ($order_field == "laji") {
			$order_table = "ark_kohdelaji";
			$order_field = Utils::getLocalizedfieldname('nimi');
		} else if ($order_field == "tyyppi") {
			$order_table = "kohde_tyypit";
			$order_field = Utils::getLocalizedfieldname('tyypit');
		} else if ($order_field == "tyyppitarkenne") {
			$order_table = "kohde_tyyppitarkenteet";
			$order_field = Utils::getLocalizedfieldname('tyyppitarkenteet');
		} else if ($order_field == "ajoitus") {
			$order_table = "kohde_ajoitukset";
			$order_field = Utils::getLocalizedfieldname('ajoitukset');
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

	public function scopeWithLimit($query, $start_row, $row_count) {
		return $query->skip($start_row)->take($row_count);
	}

	public function scopeWithKuntaNimi($query, $keyword) {
		return $query->whereIn('ark_kohde.id', function($q) use ($keyword) {
			$q->select('ark_kohde_id')
			->from('ark_kohde_kuntakyla')
			->whereIn('ark_kohde_kuntakyla.kunta_id', function($q) use ($keyword) {
				$q->select('id')
				->from('kunta')
				->where('kunta.nimi', 'ILIKE', "%".$keyword."%")
				->orWhere('kunta.nimi_se', 'ILIKE', "%".$keyword."%");
			});
		});
	}

	public function scopeWithKuntaId($query, $keyword) {
	    return $query->whereIn('ark_kohde.id', function($q) use ($keyword) {
	        $q->select('ark_kohde_id')
	        ->from('ark_kohde_kuntakyla')
	        ->whereIn('ark_kohde_kuntakyla.kunta_id', function($q) use ($keyword) {
	            $q->select('id')
	            ->from('kunta')
	            ->where('kunta.id', '=', $keyword);
	        });
	    });
	}

	public function scopeWithKylaNimi($query, $keyword) {
		return $query->whereIn('ark_kohde.id', function($q) use ($keyword) {
			$q->select('ark_kohde_id')
			->from('ark_kohde_kuntakyla')
			->whereIn('ark_kohde_kuntakyla.kyla_id', function($q) use ($keyword) {
				$q->select('id')
				->from('kyla')
				->where('kyla.nimi', 'ILIKE', "%".$keyword."%")
				->orWhere('kyla.nimi_se', 'ILIKE', "%".$keyword."%");
			});
		});
	}

	public function scopeWithKylaId($query, $keyword) {
	    return $query->whereIn('ark_kohde.id', function($q) use ($keyword) {
	        $q->select('ark_kohde_id')
	        ->from('ark_kohde_kuntakyla')
	        ->whereIn('ark_kohde_kuntakyla.kyla_id', function($q) use ($keyword) {
	            $q->select('id')
	            ->from('kyla')
	            ->where('kyla.id', '=', $keyword);
	        });
	    });
	}

	public function scopeWithKuntaNumero($query, $keyword) {
		return $query->whereIn('ark_kohde.id', function($q) use ($keyword) {
			$q->select('ark_kohde_id')
			->from('ark_kohde_kuntakyla')
			->whereIn('ark_kohde_kuntakyla.kunta_id', function($q) use ($keyword) {
				$q->select('id')
				->from('kunta')
				->where('kunta.kuntanumero', 'ILIKE', "%".$keyword."%");
			});
		});
	}

	public function scopeWithKylaNumero($query, $keyword) {
		return $query->whereIn('ark_kohde.id', function($q) use ($keyword) {
			$q->select('ark_kohde_id')
			->from('ark_kohde_kuntakyla')
			->whereIn('ark_kohde_kuntakyla.kyla_id', function($q) use ($keyword) {
				$q->select('id')
				->from('kyla')
				->where('kyla.kylanumero', 'ILIKE', "%".$keyword."%");
			});
		});
	}

	public function scopeWithName($query, $keyword) {
		return $query->where('ark_kohde.nimi', 'ILIKE', "%".$keyword."%");
	}

	public function scopeWithRelicId($query, $keyword) {
		return $query->where(DB::raw('cast(ark_kohde.muinaisjaannostunnus as varchar)'), 'ILIKE', "%".$keyword."%");
	}

	public function scopeWithKohdeLajit($query, $keyword) {
		// $keyword is a comma delimited string of id values
		return $query->whereIn('ark_kohde.ark_kohdelaji_id', explode(',', $keyword));
	}

	public function scopeWithKohdeTyypit($query, $keyword) {
		// $keyword is a comma delimited string of id values
		return $query->whereIn('ark_kohde.id', function($q) use ($keyword) {
			$q->select('ark_kohde_id')
			  ->from('ark_kohde_tyyppi')
			  ->whereIn('ark_kohde_tyyppi.tyyppi_id', explode(',', $keyword));
		});
	}

	public function scopeWithKohdeTyyppiTarkenteet($query, $keyword) {
		// $keyword is a comma delimited string of id values
		return $query->whereIn('ark_kohde.id', function($q) use ($keyword) {
			$q->select('ark_kohde_id')
			  ->from('ark_kohde_tyyppi')
			  ->whereIn('ark_kohde_tyyppi.tyyppitarkenne_id', explode(',', $keyword));
		});
	}

	public function scopeWithAjoitukset($query, $keyword) {
		// $keyword is a comma delimited string of id values
		return $query->whereIn('ark_kohde.id', function($q) use ($keyword) {
			$q->select('ark_kohde_id')
			  ->from('ark_kohde_ajoitus')
			  ->whereIn('ark_kohde_ajoitus.ajoitus_id', explode(',', $keyword));
		});
	}

	public function scopeWithKiinteistotunnus($query, $keyword) {
        return $query->whereIn('ark_kohde.id', function($q) use ($keyword) {
            $q->select('ark_kohde_id')
            ->from('ark_kohde_kiinteistorakennus')
            /* ->whereNull('ark_kohde_kiinteisto.poistettu') */ // enable when column exists!
            ->where('ark_kohde_kiinteistorakennus.kiinteistotunnus', 'ILIKE', "%".$keyword."%");
        });
	}

	public function scopeWithTyhjaKohde($query, $keyword) {
	    if($keyword == 1){
	        return $query->where('ark_kohde.tyhja', '=', false);
	    }else{
	        return $query->where('ark_kohde.tyhja', '=', true);
	    }
	}

	public function scopeWithVaatiiTarkastusta($query, $keyword) {
	    if($keyword == 1){
	        return $query->where('ark_kohde.vaatii_tarkastusta', '=', false);
	    }else{
	        return $query->where('ark_kohde.vaatii_tarkastusta', '=', true);
	    }
	}

	public function scopeWithKyppitilat($query, $keyword) {
	    // $keyword is a comma delimited string of id values
	    return $query->whereIn('ark_kohde.kyppi_status', explode(',', $keyword));
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
	    $query->leftJoin('ark_kohde_sijainti', 'ark_kohde.id', '=', 'ark_kohde_sijainti.kohde_id');
	    return $query->whereRaw(MipGis::getGeometryFieldBoundingBoxQueryWhereStringFromAreaAndPoint("ark_kohde_sijainti.sijainti", "ark_kohde_sijainti.sijainti", $bbox));
	}

	public function scopeWithPolygon($query, $polygon) {
	    $query->leftJoin('ark_kohde_sijainti', 'ark_kohde.id', '=', 'ark_kohde_sijainti.kohde_id');
	    return $query->whereRaw(MipGis::getGeometryFieldPolygonQueryWhereString($polygon, "ark_kohde_sijainti.sijainti", "ark_kohde_sijainti.sijainti"));
	}

	/**
	 * Define relationships
	 */

	/**
	 * Palauttaa kohteen ajoitukset
	 */
	public function ajoitukset() {
		return $this->hasMany('App\Ark\KohdeAjoitus', 'ark_kohde_id');
	}

	public function alkuperaisyys() {
		return $this->belongsTo('App\Ark\Alkuperaisyys');
	}

	public function hoitotarve() {
		return $this->belongsTo('App\Ark\Hoitotarve');
	}

	public function kunnatkylat() {
		return $this->hasMany('App\Ark\KohdeKuntaKyla', 'ark_kohde_id');
	}

	public function kunto() {
		return $this->belongsTo('App\Ark\Kunto');
	}

	public function laji() {
		return $this->belongsTo('App\Ark\Kohdelaji', 'ark_kohdelaji_id');
	}

	public function luoja() {
		return $this->belongsTo('App\Kayttaja', 'luoja');
	}

	public function maastomerkinta() {
		return $this->belongsTo('App\Ark\Maastomerkinta');
	}

	public function muokkaaja() {
		return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}

	public function poistaja() {
		return $this->belongsTo('App\Kayttaja', 'poistaja');
	}

	public function rajaustarkkuus() {
		return $this->belongsTo('App\Ark\Rajaustarkkuus');
	}

	public function rauhoitusluokka() {
		return $this->belongsTo('App\Ark\Rauhoitusluokka');
	}

	//Palauttaa sijainnit valmiiksi arrayssä. "Custom?" toteutus on KohdeSijainti.php luokassa.
	public function sijainnit() {
		return $this->hasMany('App\Ark\KohdeSijainti', 'kohde_id', 'id');
	}

	public function suojelutiedot() {
		return $this->hasMany('App\Ark\KohdeSuojelutieto', 'ark_kohde_id', 'id');
	}

	public function tuhoutumissyy() {
		return $this->belongsTo('App\Ark\Tuhoutumissyy');
	}

	public function alakohteet() {
		return $this->hasMany('App\Ark\KohdeAlakohde', 'ark_kohde_id', 'id');
	}

	/**
	 * Muinaisjäännösrekisteristä tulleiden tutkimusten palautus.
	 */
	public function mjrtutkimukset() {
	   return $this->hasMany('App\Ark\KohdeMjrTutkimus', 'ark_kohde_id');
	}


	public function vanhatKunnat() {
	    return $this->hasMany('App\Ark\KohdeVanhaKunta', 'ark_kohde_id');
	}

	/**
	 * Palauttaa kohteen tyyppitarkenteet
	 */
	public function tyypit() {
		return $this->hasMany('App\Ark\KohdeTyyppi', 'ark_kohde_id');
	}

	/**
	 *
	 */
	public function kiinteistotrakennukset() {
		return $this->hasMany('App\Ark\KiinteistoRakennus', 'ark_kohde_id');
	}

	public function images() {
	    return $this->belongsToMany('App\Ark\ArkKuva', 'ark_kuva_kohde', 'id', 'ark_kohde_id');
	}


	public function files() {
	    return $this->belongsToMany('App\Ark\ArkTiedosto', 'ark_tiedosto_kohde', 'ark_kohde_id');
	}

	/**
	 * Kohteen tutkimukset
	 */
	public function tutkimukset() {
	    //return $this->hasMany('App\Ark\KohdeTutkimus', 'ark_kohde_id');
	    return $this->belongsToMany('App\Ark\Tutkimus' ,'ark_kohde_tutkimus' ,'ark_kohde_id', 'ark_tutkimus_id');
	}

	public function inventointiTutkimukset() {
	    return $this->belongsToMany('App\Ark\Tutkimus' ,'ark_tutkimus_inv_kohteet' ,'ark_kohde_id' ,'ark_tutkimus_id')->withPivot('inventointipaiva', 'inventoija_id');
	}

}
