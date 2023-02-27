<?php

namespace App;

use App\Ark\ArkKartta;
use App\Ark\ArkKuva;
use App\Ark\ArkTiedosto;
use App\Ark\Loyto;
use App\Ark\Nayte;
use App\Ark\Rontgenkuva;
use App\Ark\Tutkimus;
use App\Ark\Tutkimusalue;
use App\Ark\TutkimusalueYksikko;
use App\Ark\TutkimusKayttaja;
use App\Rak\Kuva;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;


class Kayttaja extends Model implements JWTSubject, Authenticatable {


	use SoftDeletes;

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

	protected $table = "kayttaja";

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	 protected $hidden = [
	 	'salasana', 'salasana_avain',
	 ];


	public function luoja() {
		return $this->belongsTo('App\Kayttaja', 'luoja');
	}

	public function muokkaaja() {
		return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}

	public function poistaja() {
		return $this->belongsTo('App\Kayttaja', 'poistaja');
	}

	public static function getAll($jarjestys_kentta, $jarjestys_suunta) {
		return Kayttaja::select('kayttaja.*')->orderBy($jarjestys_kentta, $jarjestys_suunta);
	}

	/**
	 * Limit query results by given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithKeyword($query, $keyword) {
		return $query->orwhere('etunimi', 'ILIKE', "%".$keyword."%")
		->orwhere('sukunimi', 'ILIKE', "%".$keyword."%")
		->orWhere('sahkoposti', 'ILIKE', "%".$keyword."%")
		->orWhere('organisaatio', 'ILIKE', "%".$keyword."%");
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

		$name = explode(' ', $keyword);

		foreach($name as $key => $value) {
			$value = $value.'%';
			$name[$key] = $value;
		}

		foreach($name as $key => $value) {
			if($key == 0) {
				$query->where('sukunimi', 'ILIKE', $value)
				->orWhere('etunimi', 'ILIKE', $value);
			} else {
				$query->where('sukunimi', 'ILIKE', $value)
				->orWhere('etunimi', 'ILIKE', $value);
			}
		}

		return $query;


		//return $query->orWhere("sukunimi", 'ILIKE', '%'.$keyword.'%')->orWhere("etunimi", 'ILIKE', '%'.$keyword.'%');
	}



	/**
	 * Limit results to entities with EMAIL matching the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithEmail($query, $keyword) {
		return $query->where("sahkoposti", 'ILIKE', '%'.$keyword.'%');
	}

	/**
	 * Limit results to entities with ORGANIZATION matching the given keyword
	 *
	 * @param  $query
	 * @param String $keyword
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithOrganization($query, $keyword) {
		return $query->where("organisaatio", 'ILIKE', '%'.$keyword.'%');
	}


	public function scopeWithAktiivinen($query, $keyword) {
		return $query->where('aktiivinen', '=', $keyword);
	}

	public function scopeWithNoKatselijat($query) {
		return $query->where('rooli', '!=', 'katselija');
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
		return $query->where('id', '=', $id);
	}

	/**
	 * Limit results by inventointiprojekti_id, users who have been as inventorer in kiinteisto, arvoalue, alue
	 * Note! This does NOT get those users who are straight connected to inventointiprojekti (by looking from table inventointiprojekti_inventoija).
	 *
	 * @param  $query
	 * @param int $inventointiprojekti_id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithInventointiprojektiID($query, $inventointiprojekti_id) {

		$query->where(function($q) use ($inventointiprojekti_id) {
			$q->whereIn('id', function($q) use ($inventointiprojekti_id) {
				$q->select('inventoija_id')
				->from('inventointiprojekti_kiinteisto')
				->where('inventointiprojekti_id', '=', $inventointiprojekti_id);
			}, 'or');
			$q->whereIn('id', function($q) use ($inventointiprojekti_id) {
				$q->select('inventoija_id')
				->from('inventointiprojekti_alue')
				->where('inventointiprojekti_id', '=', $inventointiprojekti_id);
			}, 'or');
			$q->whereIn('id', function($q) use ($inventointiprojekti_id) {
				$q->select('inventoija_id')
				->from('inventointiprojekti_arvoalue')
				->where('inventointiprojekti_id', '=', $inventointiprojekti_id);
			}, 'or');
		});
		$query->whereNotIn('id', function($q) use ($inventointiprojekti_id) {
			$q->select('inventoija_id')
			->from('inventointiprojekti_inventoija')
			->where('inventointiprojekti_id', '=', $inventointiprojekti_id);
		});

		return $query;
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
	 * DEPRECATED!
	 * Tätä ei pitäisi enää tarvita mihinkään
	 * Limit results to users who have any role in given project
	 *
	 */
	public function scopeHavingRoleInProject($query, $projekti_id) {
		return $query->whereHas('projektiKayttajaRooli', function ($query) use ($projekti_id) {
			$query->where('projekti_id', '=', $projekti_id);
		});
	}



	/** == Relationships == */
	//DEPRECATED!
	public function projektiKayttajaRooli() {
		return $this->belongsToMany('App\Ark\ProjektiRooli', 'projekti_kayttaja_rooli', 'kayttaja_id', 'projekti_rooli_id')
			->withPivot('projekti_id');
		// the withPivot includes the project_id in to the "pivot" section of the returned data
	}

	/**
	 * Method to check permissions by user role for given "feature"
	 *
	 * @param string $permissionName
	 * @return boolean
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function hasPermission($permissionName="rakennusinventointi.rakennus.katselu", $id = null, $tyyppi = null) {

		// Split given "permissionname" into parts
		$osio = explode(".", $permissionName)[0];
		$entiteetti = explode(".", $permissionName)[1];
		$oikeus = explode(".", $permissionName)[2];

		//Rakennusinventointi: if user role is "pääkäyttäjä" everything is allowed
		if(Auth::user()->rooli == "pääkäyttäjä" && $osio == 'rakennusinventointi') {
			return true;
		}
		//Arkeologia: if user role is 'pääkäyttäjä' everythin is allowed
		if(Auth::user()->ark_rooli == 'pääkäyttäjä' && $osio == 'arkeologia') {
			return true;
		}

		// Get the permissions for logged in user
		$permission = false;
		$permissions = self::getPermissions($osio, $entiteetti);

		// find the correct one
		if($permissions) {
			$permission = $permissions[$oikeus];
		}

		/*
		 * If the user has role inventoija, tutkija or ulkopuolinen tutkija, he has permission to edit and delete images
		 * and files uploaded by himself.
		 */
		if($id && $tyyppi) {
			$user = Auth::user();

			if($tyyppi == 'kuva') {
				$entity = Kuva::find($id);
			} else if($tyyppi == 'tiedosto') {
				$entity = Tiedosto::find($id);
			}

			if($entity && $entity->kayttaja_id == $user->id && ($user->rooli == 'inventoija' || $user->rooli == 'tutkija' || $user->rooli == 'ulkopuolinen tutkija')) {
				$permission = true;
			} else if($user->rooli == 'tutkija' && $permission == true) { // 'Bug' 7067
				// Tutkijan oikeuksilla ei pysty muokkaamaan kuin itse lisäämiensä kuvien tietoa. Pidetään kannasta tuleva permissioasetus
				// myös merkitsevänä, jos tätä halutaan vielä joskus tulevaisuudessa muuttaa, niin se onnistuu vaihtamalla oikeutta kannasta.
				$permission = true;
			} else {
				$permission = false;
			}

		}

		return $permission;
	}

	/**
	 * Method to check permissions by user role for given "feature"
	 *
	 * @param string $permissionName
	 * @return boolean
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function hasPermissionForEntity($permissionName="arkeologia.ark_tutkimus.katselu", $id) {

		// if user role is "pääkäyttäjä" OR "tutkija" everything is allowed
		//Tässä vaiheessa on speksattu ainoastaan niin, että
		//katselijoiden pääsyä rajoitetaan.
		//Jos tätä halutaan muutettavan, poistetaan alta seuraavat 3 riviä ja
		//määritetään oikeudet kannan perusteella
		//Alla olevat 3 riviä ovat ainoastaan nopeuttamaan järjestelmää, koska kantakyselyä
		//ei tarvitse tehdä
		if(Auth::user()->ark_rooli == "pääkäyttäjä" || Auth::user()->ark_rooli == "tutkija") {
			return true;
		}

		// For all others check the permissions

		// Split given "permissionname" into parts
		$osio = explode(".", $permissionName)[0];
		$entiteetti = explode(".", $permissionName)[1];
		$oikeus = explode(".", $permissionName)[2];

		// Get the permissions for logged in user
		$permission = false;
		$permissions = self::getPermissionsByEntity($osio, $entiteetti, $id);

		// find the correct one
		if($permissions) {
			$permission = $permissions[$oikeus];
		}

		return $permission;
	}

	/**
	 * Get permissions for given section and entity for logged in user.
	 *
	 * @param string $section
	 * @param string $entity
	 * @return array of data containing permissions for luonti, katselu, muokkaus, poisto
	 * @author ATR Soft Oy
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getPermissions($section, $entity) {

		// if user is superuser, everything is allowed
		if(Auth::user()->rooli == "pääkäyttäjä" && $section == 'rakennusinventointi') {
			return self::allPermissions();
		}
		if(Auth::user()->ark_rooli == "pääkäyttäjä" && $section == 'arkeologia') {
			return self::allPermissions();
		}

		$rooli = null;
		if($section == 'arkeologia') {
		    $rooli = Auth::user()->ark_rooli;
		} else {
		    $rooli = Auth::user()->rooli;
		}

		// get permissions from db
		$perms = DB::table('jarjestelma_roolit')
			->select("luonti", "katselu", "muokkaus", "poisto")
			->where('rooli', '=', $rooli)
			->where('osio', '=', $section)
			->where('entiteetti', '=', $entity)->first();

		// if there were none defined, return a no-access permissions table
		$permissions = self::noPermissions();
		if ($perms) {
			$permissions = array(
					"katselu" 	=> $perms->katselu,
					"luonti"	=> $perms->luonti,
					"muokkaus" 	=> $perms->muokkaus,
					"poisto" 	=> $perms->poisto
			);
		}
		return $permissions;
	}

	/**
	 * Hakee kaikki oikeudet annetulle osiolle.
	 */
	public static function getAllPermissionsBySection($section) {

		//Get all entity types
		$entiteetit = DB::select("select distinct entiteetti from jarjestelma_roolit where osio=?", array($section));

		$allPermissions = array();

		//Get permissions for each of the entity types
		foreach($entiteetit as $key => $value) {
			foreach($value as $val) {
				$allPermissions[$val] = self::getPermissions($section, $val);
			}
		}

		return $allPermissions;
	}

	/**
	 * Hakee kaikki oikeudet.
	 */
	public static function getAllPermissions() {

	    //Get all entity types
	    $entiteetit = DB::select("select distinct entiteetti from jarjestelma_roolit");

	    $allPermissions = array();

	    //Get permissions for each of the entity types
	    foreach($entiteetit as $key => $value)
	    {
	        foreach($value as $val) {
	            $allPermissions[$val] = self::getPermissionsByEntityOnly($val);
	        }
	    }

	    return $allPermissions;
	}

	/**
	 * Hakee annetun entiteetin oikeudet
	 */
	public static function getPermissionsByEntityOnly($entity) {

	    // Tutkitaan osio eli ARK tai RAK jonka oikeudet haetaan roolin mukaan
	    if(substr($entity, 0, 4) === 'ark_'){
	        // ARK oikeudet
	        $perms = DB::table('jarjestelma_roolit')
	        ->select("luonti", "katselu", "muokkaus", "poisto")
	        ->where('rooli', '=', Auth::user()->ark_rooli)
	        ->where('entiteetti', '=', $entity)->first();
	    }else{
	        // RAK oikeudet
	        $perms = DB::table('jarjestelma_roolit')
	        ->select("luonti", "katselu", "muokkaus", "poisto")
	        ->where('rooli', '=', Auth::user()->rooli)
	        ->where('entiteetti', '=', $entity)->first();
	    }

	    // if there were none defined, return a no-access permissions table
	    $permissions = self::noPermissions();

	    if ($perms) {
	        $permissions = array(
	            "katselu" 	=> $perms->katselu,
	            "luonti"	=> $perms->luonti,
	            "muokkaus" 	=> $perms->muokkaus,
	            "poisto" 	=> $perms->poisto
	        );
	    }
	    return $permissions;
	}

	/*
	 * Tarkastetaan onko käyttäjällä tiettyyn arkeologisen tutkimukseen tietty oikeus
	 */
	public static function hasArkTutkimusSubPermission($permissionName, $tutkimusId) {
		//Haetaan ensin kaikki oikeudet
		$permissions = self::getArkTutkimusSubPermissions($tutkimusId);

		// Split given "permissionname" into parts
		$osio = explode(".", $permissionName)[0];
		$entiteetti = explode(".", $permissionName)[1];
		$oikeus = explode(".", $permissionName)[2];

		// Get the permissions for logged in user
		$permission = false;
		$permission = $permissions[$oikeus];
		//Lopulta palautetaan ainoastaan pyydetty oikeus
		return $permission;
	}

	//Palauttaa käyttäjän oikeudet jotka hänellä on tiettyyn tutkimukseen liittyviin entiteetteihin
	//Käyttäjällä on täydet oikeudet esimerkiksi yksiköihin, tutkimusalueisiin jne
	//1) Jos hän on pääkäyttäjä
	//2) Jos hän on tutkija
	//3) Jos hän on katselija JA hänet on lisätty tutkimuksen käyttäjiksi JA tutkimusta ei ole merkitty valmiiksi
	public static function getArkTutkimusSubPermissions($tutkimusId) {
		/*
		 * Alla annetaan pääkäyttäjälle ja tutkijalle kaikki oikeudet suoraan (tiketti 8610).
		 * Katselijalle haetaan erikseen kaikki katselijalle näkyvät tutkimukset, ja näistä tarkastetaan halutun tutkimuksen
		 * tila. Jos tila on ei valmis (valmis == false), niin tällöin katselija on ko. tutkimukseen
		 * lisätty käyttäjäksi ja hän saa täydet muokkausoikeudet siihen liittyviin tietoihin.
		 */
		// Arkeologiapuolen pääkäyttäjälle sallitaan kaikki
		if(Auth::user()->ark_rooli == "pääkäyttäjä") {
			return self::allPermissions();
		}
		// Arkeologiapuolen tutkijalle sallitaan kaikki tutkimukseen liittyvien tietojen muokkaus.
		if(Auth::user()->ark_rooli == 'tutkija') {
			return self::allPermissions();
		}

		//Haetaan tietty tutkimus.
		$tutkimus = self::getArkTutkimus($tutkimusId);
		//Asetetaan tutkimuksen oikeudet
		$permissions = self::getArkTutkimusPermissions($tutkimus);

		return $permissions;
	}

	//Palauttaa tutkimuksen tai
	//kaikki oikeudet false, jos pyydettyä tutkimusta ei löydy.
	private static function getArkTutkimus($tutkimusId) {
		$tutkimus = Tutkimus::getAll()->get();
		for($i = 0; $i<sizeof($tutkimus); $i++) {
			if($tutkimus[$i]->id == $tutkimusId) {
				$tutkimus = $tutkimus[$i];
				return $tutkimus;
			}
		}
		//Jos tutkimusta ei löydy, palautetaan "ei oikeuksia"
		return null;
	}

	//Haetaan käyttäjälle oikeudet tiettyyn tutkimukseen
	private static function getArkTutkimusPermissions($tutkimus) {
	    if(!$tutkimus) {
	        return self::noPermissions();
	    }

		//Käyttäjällä on ainakin katseluoikeudet tutkimukseen, koska tutkimus on löytynyt
		$permissions['katselu'] = true;

		/*
		 * US10291
		 * Tutkimukseen liitetty katselija saa aina muokata tietoja, jos hän kuuluu tutkimuksen käyttäjiin
		 */
			$tutkimusKayttaja = TutkimusKayttaja::getSingleByTutkimusIdAndUserId($tutkimus->id, Auth::user()->id)->first();
			if($tutkimusKayttaja && $tutkimusKayttaja->id) {
				$permissions['luonti'] = true;
				$permissions['muokkaus'] = true;
				$permissions['poisto'] = true;
			} else {
				$permissions['luonti'] = false;
				$permissions['muokkaus'] = false;
				$permissions['poisto'] = false;

			}
      return $permissions;
	}

	/*
	 * Palautetaan tietyn entiteetin oikeudet
	 */
	public static function getPermissionsByEntity($section, $entity, $id) {
		//if user ark_role is pääkäyttäjä, everything is allowed
		if(Auth::user()->ark_rooli == "pääkäyttäjä") {
			return self::allPermissions();
		}

		//if user ark_role tutkija, everything is allowed
		if(Auth::user()->ark_rooli == "tutkija") {
			return self::allPermissions();
		}

		//Else: Haetaan oikeudet muille rooleille (=katselija) tutkimuksen tilan perusteella
		if($section == 'arkeologia'){
			if($entity == 'ark_tutkimus') {
				//Tarkasta onko kayttajan id ark_tutkimus_kayttaja-taulussa
				//Jos on, niin käyttäjällä on ainakin katseluoikeus. Eli, tutkimus saadaan avata katselua varten
				$result = Tutkimus::getAll()->where('id', '=', $id)->first();
				if($result != null) {
					//tällä ainoastaan asetetaan kaikki oikeudet falseksi (tai jos niitä on
					//kantatasolla annettu käyttäjälle, ne menevät oikein suoraan)
					$permissions = self::getPermissions($section, $entity);
					//Säädä katseluoikeus kohdilleen
					//Vaikka käyttäjä olisikin lisätty tutkimukselle erikseen, se ei muuta
					//käyttäjän oikeuksia tutkimukseen, ainoastaan tutkimukseen liittyviin asioihin.
					$permissions['katselu'] = true;
				} else {
					$permissions = self::noPermissions();
				}
			} else if($entity == 'ark_yksikko') {
				$yksikko = TutkimusalueYksikko::getSingle($id)->first();
				$tutkimusalue = $yksikko->tutkimusalue()->first();
				$tutkimus = self::getArkTutkimus($tutkimusalue->ark_tutkimus_id);
				$permissions = self::getArkTutkimusPermissions($tutkimus);
			} else if($entity == 'ark_loyto') {
				$loyto = Loyto::getSingle($id)->first();
				if($loyto['ark_tutkimusalue_id']) { //CASE irtolöytö
				    $tutkimusalue = Tutkimusalue::getSingle($loyto['ark_tutkimusalue_id'])->first();
				} else {
				    $yksikko = TutkimusalueYksikko::getSingle($loyto['ark_tutkimusalue_yksikko_id'])->first();
				    $tutkimusalue = $yksikko->tutkimusalue()->first();
				}
				$tutkimus = self::getArkTutkimus($tutkimusalue->ark_tutkimus_id);
				$permissions = self::getArkTutkimusPermissions($tutkimus);
			} else if($entity == 'ark_nayte') {
			    $nayte = Nayte::getSingle($id)->first();
			    if($nayte['ark_tutkimusalue_id']) { //CASE irtolöytö
			        $tutkimusalue = Tutkimusalue::getSingle($nayte['ark_tutkimusalue_id'])->first();
			    } else {
			        $yksikko = TutkimusalueYksikko::getSingle($nayte['ark_tutkimusalue_yksikko_id'])->first();
			        $tutkimusalue = $yksikko->tutkimusalue()->first();
			    }
			    $tutkimus = self::getArkTutkimus($tutkimusalue->ark_tutkimus_id);
			    $permissions = self::getArkTutkimusPermissions($tutkimus);
			}
			else if($entity == 'ark_tutkimusalue') {
				$tutkimusalue = Tutkimusalue::getSingle($id)->first();
				$tutkimus = self::getArkTutkimus($tutkimusalue->ark_tutkimus_id);
				$permissions = self::getArkTutkimusPermissions($tutkimus);
			} else if($entity == 'ark_kuva') {
			    $tutkimus = ArkKuva::tutkimus($id)->first();
			    $permissions = self::getArkTutkimusPermissions($tutkimus);
			} else if($entity == 'ark_kartta') {
                $kartta = ArkKartta::getSingle($id)->first();
			    $tutkimus = self::getArkTutkimus($kartta->ark_tutkimus_id);
			    $permissions = self::getArkTutkimusPermissions($tutkimus);
			} else if($entity == 'ark_rontgenkuva') {
			    $loyto = Rontgenkuva::joinloyto($id)->first();
			    $nayte = null;
			    $yksikko = null;
			    $tutkimusalue = null;
			    if($loyto) {
    			    if($loyto['ark_tutkimusalue_id']) { //CASE irtolöytö
    			        $tutkimusalue = Tutkimusalue::getSingle($loyto['ark_tutkimusalue_id'])->first();
    			    } else {
    			        $yksikko = TutkimusalueYksikko::getSingle($loyto['ark_tutkimusalue_yksikko_id'])->first();
    			        $tutkimusalue = $yksikko->tutkimusalue()->first();
    			    }
			    } else {//näyte
			        $nayte = Rontgenkuva::joinnayte($id)->first();
			        if($nayte['ark_tutkimusalue_id']) { //CASE irtolöytö
			            $tutkimusalue = Tutkimusalue::getSingle($nayte['ark_tutkimusalue_id'])->first();
			        } else {
			            $yksikko = TutkimusalueYksikko::getSingle($nayte['ark_tutkimusalue_yksikko_id'])->first();
			            $tutkimusalue = $yksikko->tutkimusalue()->first();
			        }
			    }
			    $tutkimus = self::getArkTutkimus($tutkimusalue->ark_tutkimus_id);
			    $permissions = self::getArkTutkimusPermissions($tutkimus);
			} else if($entity == 'ark_tiedosto') {
			    //Tarkistetaan mihin tiedosto liittyy
			    //Toimenpiteelle ja käsittelylle annetaan oikeudet roolin mukaan
					$tiedosto = ArkTiedosto::getSingle($id)->with(array('loyto', 'nayte', 'tiedostotutkimus', 'toimenpide', 'kasittely'))->first();
			    //Löytö
			    $tiedostoloyto = $tiedosto->loyto;
			    if($tiedostoloyto){
			        $tutkimus = self::getTutkimusByType('loyto', $tiedostoloyto->ark_loyto_id);
			        $permissions = self::getArkTutkimusPermissions($tutkimus);
			        return $permissions;
			    }
			    //Näyte
			    $tiedostonayte = $tiedosto->nayte;
			    if ($tiedostonayte) {
			        $tutkimus = self::getTutkimusByType('nayte', $tiedostonayte->ark_nayte_id);
			        $permissions = self::getArkTutkimusPermissions($tutkimus);
			        return $permissions;
			    }
			    //Tutkimus
			    $tiedostotutkimus = $tiedosto->tiedostotutkimus;
			    if ($tiedostotutkimus) {
			        $tutkimus = self::getArkTutkimus($tiedostotutkimus->ark_tutkimus_id);
			        $permissions = self::getArkTutkimusPermissions($tutkimus);
			        return $permissions;
			    }
			    //Toimenpide
			    $tiedostotoimenpide = $tiedosto->toimenpide;
			    if($tiedostotoimenpide){
			        if(Auth::user()->ark_rooli == 'tutkija' || Auth::user()->ark_rooli == 'pääkäyttäjä'){
			            return self::allPermissions();
			        }
			        else{
			            return self::noPermissions();
			        }
			    }
			    //Käsittely
			    $tiedostokasittely = $tiedosto->kasittely;
			    if($tiedostokasittely){
			        if(Auth::user()->ark_rooli == 'tutkija' || Auth::user()->ark_rooli == 'pääkäyttäjä'){
			            return self::allPermissions();
			        }
			        else{
			            return self::noPermissions();
			        }
			    }

					//Haetaan mihin tiedosto liittyy
					//Tällä hetkellä voi olla ark_rontgenkuva, mutta tulevaisuudessa myös ark_loyto, ark_nayte, ark_tutkimus, ark_kohde
					$rontgenkuva = ArkTiedosto::joinrontgenkuva($id)->first();
					$loyto = Rontgenkuva::joinloyto($rontgenkuva->id)->first();
					$nayte = null;
					$yksikko = null;
					$tutkimusalue = null;
					if($loyto) {
							if($loyto['ark_tutkimusalue_id']) { //CASE irtolöytö
									$tutkimusalue = Tutkimusalue::getSingle($loyto['ark_tutkimusalue_id'])->first();
							} else {
									$yksikko = TutkimusalueYksikko::getSingle($loyto['ark_tutkimusalue_yksikko_id'])->first();
									$tutkimusalue = $yksikko->tutkimusalue()->first();
							}
					} else {//näyte
							$nayte = Rontgenkuva::joinnayte($id)->first();
							if($nayte['ark_tutkimusalue_id']) { //CASE irtolöytö
									$tutkimusalue = Tutkimusalue::getSingle($nayte['ark_tutkimusalue_id'])->first();
							} else {
									$yksikko = TutkimusalueYksikko::getSingle($nayte['ark_tutkimusalue_yksikko_id'])->first();
									$tutkimusalue = $yksikko->tutkimusalue()->first();
							}
					}
					$tutkimus = self::getArkTutkimus($tutkimusalue->ark_tutkimus_id);
					$permissions = self::getArkTutkimusPermissions($tutkimus);
			}
			else { //Ei tunnettu entiteettityyppi, silloin ei ole myöskään oikeuksia
				$permissions = self::noPermissions();
			}
		} else { //Ei olla arkeologia puolella, tätä ei tällä hetkellä käytetä missään muualla
			$permissions = self::noPermissions();
		}
		return $permissions;
	}

	// Palauttaa tutkimuksen tyypin perusteella (löytö, näyte...)
	// id on tiedoston id, esim. ark_loyto_id
	private static function getTutkimusByType($tyyppi, $id){
	    if($tyyppi == 'loyto'){
	        $loyto = Loyto::getSingle($id)->first();
	        if($loyto['ark_tutkimusalue_id']) { //CASE irtolöytö
	            $tutkimusalue = Tutkimusalue::getSingle($loyto['ark_tutkimusalue_id'])->first();
	        } else {
	            $yksikko = TutkimusalueYksikko::getSingle($loyto['ark_tutkimusalue_yksikko_id'])->first();
	            $tutkimusalue = $yksikko->tutkimusalue()->first();
	        }
	        $tutkimus = self::getArkTutkimus($tutkimusalue->ark_tutkimus_id);
	        return $tutkimus;
	    }
	    else if($tyyppi == 'nayte'){
	        $nayte = Nayte::getSingle($id)->first();
	        if($nayte['ark_tutkimusalue_id']) {
	            $tutkimusalue = Tutkimusalue::getSingle($nayte['ark_tutkimusalue_id'])->first();
	        } else {
	            $yksikko = TutkimusalueYksikko::getSingle($nayte['ark_tutkimusalue_yksikko_id'])->first();
	            $tutkimusalue = $yksikko->tutkimusalue()->first();
	        }
	        $tutkimus = self::getArkTutkimus($tutkimusalue->ark_tutkimus_id);
	        return $tutkimus;
	    }
	    // Jos joskus tarvitaan oikeuksien tarkastelua tutkimuksen perusteella toimenpiteelle tai käsittelylle
	    /*
	    else if($tyyppi == 'toimenpide'){
	        $toimenpide = KonsToimenpiteet::getSingle($id)->first();
	        if($toimenpide['ark_tutkimusalue_id']) {
	            $tutkimusalue = Tutkimusalue::getSingle($toimenpide['ark_tutkimusalue_id'])->first();
	        } else {
	            $yksikko = TutkimusalueYksikko::getSingle($toimenpide['ark_tutkimusalue_yksikko_id'])->first();
	            $tutkimusalue = $yksikko->tutkimusalue()->first();
	        }
	        $tutkimus = self::getArkTutkimus($tutkimusalue->ark_tutkimus_id);
	        return $tutkimus;
	    }
	    else if ($tyyppi == 'kasittely'){
	        $kasittely = KonservointiKasittely::getSingle($id)->first();
	        if($kasittely['ark_tutkimusalue_id']) {
	            $tutkimusalue = Tutkimusalue::getSingle($kasittely['ark_tutkimusalue_id'])->first();
	        } else {
	            $yksikko = TutkimusalueYksikko::getSingle($kasittely['ark_tutkimusalue_yksikko_id'])->first();
	            $tutkimusalue = $yksikko->tutkimusalue()->first();
	        }
	        $tutkimus = self::getArkTutkimus($tutkimusalue->ark_tutkimus_id);
	        return $tutkimus;
	    }
	    */

	}

	private static function allPermissions() {
		return array(
				"katselu" 	=> true,
				"luonti"	=> true,
				"muokkaus" 	=> true,
				"poisto" 	=> true
		);
	}

	private static function noPermissions() {
		return array(
			"katselu" 	=> false,
			"luonti"	=> false,
			"muokkaus" 	=> false,
			"poisto" 	=> false
		);
	}

	//Get the inventointiprojektit of the user with the ajanjaksos
	public function inventointiprojektit() {
		return $this->belongsToMany('App\Rak\Inventointiprojekti', 'inventointiprojekti_inventoija', 'inventoija_id', 'inventointiprojekti_id')->with('ajanjakso');
	}
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getAuthIdentifier()
    {
        return $this->id;
    }

    public function getRememberToken()
    {
        return null;
    }

    public function getRememberTokenName()
    {
        return '';
    }

    public function setRememberToken($value)
    {
    }

    public function getAuthPassword()
    {
        return $this->salasana;
    }

    public function getAuthIdentifierName()
    {
        return 'id';
    }



}
