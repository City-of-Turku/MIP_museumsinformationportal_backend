<?php

namespace App\Http\Controllers;


use App\Kayttaja;
use App\Utils;
use App\Library\String\MipJson;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

class KayttajaController extends Controller {

    /**
     * Display a listing of the users matching given keyword, if no keyword is given return ALL users
     *
	 * @param Request $request
	 * @return MipJson
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
    public function index(Request $request) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.kayttaja.katselu')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$validator = Validator::make($request->all(), [
    			'rivi'				=> 'numeric',
    			'rivit'				=> 'numeric',
    			'jarjestys'			=> 'string',
    			'jarjestys_suunta'	=> 'string',
    			'etunimi'			=> 'string',
    			'sukunimi'			=> 'string',
    			'organisaatio'		=> 'string',
    			'sahkoposti'		=> 'string',
    			'id'				=> 'numeric',
    	]);

    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		foreach($validator->errors()->all() as $error) {
    			MipJson::addMessage($error);
    		}
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    		return MipJson::getJson();
    	}

   		try {
	    	/*
	    	 * By default return ALL items from db (with LIMIT and ORDER options)
	    	 */
	    	$rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? $request->rivi : 0;
	    	$riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? $request->rivit : 100;
	    	$jarjestys_kentta = (isset($request->jarjestys)) ? $request->jarjestys : "sukunimi";
	    	$jarjestys_suunta = (isset($request->jarjestys_suunta)) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

	    	/*
	    	 * US#7137: Inventoijalistausta varten järjestetään nimet etunimen mukaan.
	    	 */
	    	if($request->inventoijat) {
	    		$users = Kayttaja::getAll('etunimi', $jarjestys_suunta);
	    	} else {
	    		//Get all first instead of only the active ones.
	    		$users = Kayttaja::getAll($jarjestys_kentta, $jarjestys_suunta);
	    	}
	    	/*
	    	 * If ANY search terms are given limit results by them
	    	 */
	    	if($request->id) {
	    		$users->withID($request->id);
	    	}
	    	if($request->etunimi) {
	    		$users->withFirstName($request->etunimi);
	    	}
	    	if($request->sukunimi) {
	    		$users->withLastname($request->sukunimi);
	    	}
	    	if($request->sahkoposti) {
	    		$users->withEmail($request->sahkoposti);
	    	}
	    	if($request->organisaatio) {
	    		$users->withOrganization($request->organisaatio);
	    	}
	    	if($request->aktiivinen) {
	    		$users->withAktiivinen($request->aktiivinen);
	    	}
	    	if($request->inventoijat == true) {
	    		$users->withNoKatselijat();
	    	}
	    	if($request->nimi) {
	    		$users->withName($request->nimi);
	    	}

	    	// calculate the total rows of the search results
	    	$total_rows = Utils::getCount($users);

	    	// limit the results rows by given params
	    	$users->withLimit($rivi, $riveja);

	    	// Execute the query
	    	$users = $users->get();

	    	/*
	    	 * Set the results into Json object
	    	 */
	    	MipJson::initGeoJsonFeatureCollection(count($users), $total_rows);
	    	MipJson::addMessage(Lang::get('kayttaja.found_count',["count" => count($users)]));
	    	foreach ($users as $user) {
	    		MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $user);
	    	}

   		} catch(Exception $e) {
   			MipJson::setGeoJsonFeature();
   			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
   			MipJson::addMessage(Lang::get('kayttaja.search_failed'));
   		}

		return MipJson::getJson();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return MipJson
     * @author
	 * @version 1.0
	 * @since 1.0
     */
    public function store(Request $request) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.kayttaja.luonti')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}
    	/*
    	 * Sahkoposti validation: Must be unique IF the user has not been soft deleted
    	 */
    	$validator = Validator::make($request->all(), [
    		'sahkoposti' 	=> 'required|email|unique:kayttaja,sahkoposti,NULL,id,' . Kayttaja::DELETED_AT . ',NULL',
    		'salasana' 		=> 'string|min:6|regex:/^(?=.*[a-z])(?=.*[A-ZÅÄÖ])(?=.*\d)[a-zåäöA-ZÅÄÖ!@#%&\d]{6,}$/',
    		'etunimi'		=> 'required|min:1',
    		'sukunimi'		=> 'required|min:1',
    		'kieli'			=> 'required|min:2|size:2',
    		'organisaatio' 	=> "required",
    		'rooli'			=> 'required|string',
    		'ark_rooli'		=> 'required|string'
    	]);
    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		MipJson::addMessage(Lang::get('validation.custom.password_specs'));
    		foreach($validator->errors()->all() as $error)
    			MipJson::addMessage($error);
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {
    		try {
    			 try {
    			 	DB::beginTransaction();
    			 	Utils::setDBUser();

	    			$user = new Kayttaja();
	    			$user->sahkoposti = $request->sahkoposti;
	    			$user->salasana = Hash::make($request->salasana);
	    			$user->etunimi = $request->etunimi;
	    			$user->sukunimi = $request->sukunimi;
	    			$user->kieli	= $request->kieli;
	    			$user->organisaatio = $request->organisaatio;
	    			$user->aktiivinen = $request->aktiivinen;
	    			$user->rooli = $request->rooli;
	    			$user->ark_rooli = $request->ark_rooli;
	    			$user->vanhatKarttavarit = $request->vanhatKarttavarit;
	    			$user->tekijanoikeuslauseke = $request->tekijanoikeuslauseke;

	    			$author_field = Kayttaja::CREATED_BY;
	    			$user->$author_field = Auth::user()->id;
	    			$user->save();

	    			DB::commit();
    			 } catch (Exception $e) {
    			 	DB::rollback();
    			 	throw $e;
    			 }

    			MipJson::addMessage(Lang::get('kayttaja.create_success'));
    			MipJson::setGeoJsonFeature(null, array("id" => $user->id));
    			//MipJson::setData(array("id" => Kayttaja::withEmail($request->sahkoposti)->id));
    			MipJson::setResponseStatus(Response::HTTP_OK);

    		} catch (Exception $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::setMessages(array(Lang::get('kayttaja.create_failed'),$e->getMessage()));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}

    	return MipJson::getJson();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return MipJson
     * @author
	 * @version 1.0
	 * @since 1.0
     */
    public function show($id) {

    	/*
    	 * Role check
    	 */
    	if(Auth::user()->id != $id) {
	    	if(!Kayttaja::hasPermission('rakennusinventointi.kayttaja.katselu')) {
	    		MipJson::setGeoJsonFeature();
	    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
	    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
	    		return MipJson::getJson();
	    	}
    	}

    	$user = Kayttaja::where('id', '=', $id)->with('luoja')->with('muokkaaja')->first();
    	if(!isset($user->id)) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    		MipJson::addMessage(Lang::get('auth.custom.user_not_found'));
    	} else {
    		MipJson::addMessage(Lang::get('kayttaja.found_count',["count" => 1]));
    		MipJson::setGeoJsonFeature(null, $user);
    	}
    	//MipJson::setData(array($user));
    	return MipJson::getJson();
    }

    /**
     * Method to return id of logged user
     *
     * @return MipJson
     * @author
     * @version 1.0
     * @since 1.0
     * @deprecated 22.09.2016
     */
    public function loggedUser() {

    	/*
    	 * Role check is not needed here!
    	 *
    	 * User needs ALWAYS to be logged in to access this endpoint
    	 * Logged user can only access its own data, so no role check is needed
    	 */

    	$token = JWTAuth::getToken();
    	$user = JWTAuth::toUser($token);

    	$user->oikeudet = Kayttaja::getAllPermissions();

    	//Remove password and password key from the users data as they are not needed
    	unset($user['salasana']);
    	unset($user['salasana_avain']);

    	MipJson::addMessage(Lang::get('auth.custom.token_is_valid'));
    	MipJson::setData(array('kayttaja' => $user));
    	return MipJson::getJson();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return MipJson
     * @author
	 * @version 1.0
	 * @since 1.0
     */
    public function update(Request $request, $id) {

    	/*
    	 * Role check
    	 */
    	if(Auth::user()->id != $id) {
	    	if(!Kayttaja::hasPermission('rakennusinventointi.kayttaja.muokkaus')) {
	    		MipJson::setGeoJsonFeature();
	    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
	    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
	    		return MipJson::getJson();
	    	}
    	}


    	/*
    	 * User input validation
    	 *
    	 * If user input does not meet our requirements below
    	 * set the error message
    	 *
    	 * NOTE: The password MUST contain characters from at least three of the following five categories:
    	 * - uppercase characters (A – Z)
    	 * - lowercase characters (a – z)
    	 * - Base 10 digits (0 – 9)
    	 * - Non-alphanumeric (For example: !, $, #, or %)
    	 * - Unicode characters
    	 *
    	 * Sahkoposti validation: Must be unique IF the user has not been soft deleted
    	 */
    	$validator = Validator::make($request->all(), [
    			'sahkoposti' 	=> 'email|unique:kayttaja,sahkoposti,NULL,id,' . Kayttaja::DELETED_AT . ',NULL',
    			'salasana' 		=> 'string|min:6|regex:/^(?=.*[a-z])(?=.*[A-ZÅÄÖ])(?=.*\d)[a-zåäöA-ZÅÄÖ!@#%&\d]{6,}$/',
    			'etunimi' 		=> 'required|min:1',
    			'sukunimi' 		=> 'required|min:1',
    			'kieli'			=> 'required|size:2',
    			'organisaatio' 	=> 'required',
    			'rooli'			=> 'required|string',
    			'ark_rooli'		=> 'required|string'
    	]);



    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		MipJson::addMessage(Lang::get('validation.custom.password_specs'));
    		foreach($validator->errors()->all() as $error)
    			MipJson::addMessage($error);
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {
    		try {
    			$user = Kayttaja::find($id);

    			if(!$user){
    				//error user not found
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage(Lang::get('auth.custom.user_not_found'));
    				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    			}
    			else {

    				try {
    					DB::beginTransaction();
    					Utils::setDBUser();

		    			if($request->sahkoposti) {
		    				$user->sahkoposti = $request->sahkoposti;
		    			}

		    			$user->etunimi = $request->etunimi;
		    			$user->sukunimi = $request->sukunimi;

		    			if($request->salasana) {
		    				$user->salasana = Hash::make($request->salasana);
		    			}

		    			$user->kieli = $request->kieli;
		    			$user->organisaatio = $request->organisaatio;

		    			//Admin can change the role only
		    			if(Auth::user()->rooli == 'pääkäyttäjä' || Auth::user()->ark_rooli == 'pääkäyttäjä') {
		    				$user->rooli = $request->rooli;
		    				$user->ark_rooli = $request->ark_rooli;
		    			}

		    			$user->aktiivinen = $request->aktiivinen;
		    			$user->vanhatKarttavarit = $request->vanhatKarttavarit;
		    			$user->tekijanoikeuslauseke = $request->tekijanoikeuslauseke;

		    			$author_field = Kayttaja::UPDATED_BY;
		    			$user->$author_field = Auth::user()->id;
		    			$user->save();

		    			DB::commit();
    				} catch (Exception $e) {
    					DB::rollback();
    					throw $e;
    				}

    				MipJson::addMessage(Lang::get('kayttaja.update_success'));
    				MipJson::setGeoJsonFeature(null, array("id" => $user->id));
    				MipJson::setResponseStatus(Response::HTTP_OK);
    			}
    		}
    		catch(QueryException $qe) {
    			MipJson::setGeoJsonFeature();
    			MipJson::addMessage($qe->getMessage());
    		}
    		catch (Exception $e) {
    			MipJson::setGeoJsonFeature();
    			MipJson::setMessages(array(Lang::get('kayttaja.update_failed'),$e->getMessage()));
    			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    		}
    	}
    	return MipJson::getJson();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return MipJson
     * @author
	 * @version 1.0
	 * @since 1.0
     */
    public function destroy($id) {

    	/*
    	 * Role check
    	 */
    	if(!Kayttaja::hasPermission('rakennusinventointi.kayttaja.poisto')) {
    		MipJson::setGeoJsonFeature();
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    		return MipJson::getJson();
    	}

    	$user = Kayttaja::find($id);

    	if(!$user) {
    		// return: user not found
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('auth.custom.user_not_found'));
    		MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    		return MipJson::getJson();
    	}

    	try {
    		DB::beginTransaction();
    		Utils::setDBUser();

	    	$author_field = Kayttaja::DELETED_BY;
	    	$when_field = Kayttaja::DELETED_AT;
	    	$user->$author_field = Auth::user()->id;
	    	$user->$when_field = \Carbon\Carbon::now();

	    	//Make the deleted user not active
	    	$user->aktiivinen = false;

	    	$user->save();

	    	DB::commit();

	    	MipJson::addMessage(Lang::get('kayttaja.delete_success'));
	    	MipJson::setGeoJsonFeature(null, array("id" => $user->id));

    	} catch (Exception $e) {
    		DB::rollback();

    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('kayttaja.delete_failed'));
    		MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    	}

    	return MipJson::getJson();
    }

    /**
     * Reset the password of given username and send new password to user email address.
     *
     * @param String $username (email)
     * @return MipJson
     * @author
     * @version 1.0
     * @since 1.0
     */
    public function restorePassword($username) {

    	$user = Kayttaja::where('sahkoposti', '=', $username)->where('aktiivinen', true)->first();

    	// if user was NOT found
    	if(!$user) {
    		MipJson::setGeoJsonFeature();
    		//MipJson::addMessage(Lang::get('auth.custom.user_not_found'));
    		//MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
    		return MipJson::getJson();
    	}
    	// if user WAS found restore the password of user and send it to user email address

    	try {
    		DB::beginTransaction();
   			Utils::setDBUserAsSystem();

   			$password = Str::random(8);
   			$user->salasana = Hash::make($password);
   			$user->save();


    		$message_content = "Hei ".ucfirst(strtolower($user->etunimi)).", \n\n".
    				"sivustolla: https://mip.turku.fi/ \n".
    				"on pyydetty uutta salasanaa tunnuksellesi: ".$user->sahkoposti.".\n\n".
   					"Uusi salasanasi sivustolle on: ".$password."";

   			$mail_sent = Mail::raw($message_content, function($message) use ($user) {
   				$message->from('mip@mip.fi', 'MIP');
   				$message->to($user->sahkoposti)->subject("Uusi MIP salasanasi.");
   			});

    		DB::commit();

    		MipJson::addMessage(Lang::get('kayttaja.password_reset_success'));
    		return MipJson::getJson();

    	} catch (Exception $e) {
	    	DB::rollback();
			MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('kayttaja.password_reset_failed'));
    		MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
	    }

    	return MipJson::getJson();
    }

    public function get_inventoringprojects($id) {
    	/*
    	 * Role check
    	 */

    	if (! Kayttaja::hasPermission ( 'rakennusinventointi.kiinteisto.muokkaus' )) {
    		MipJson::setGeoJsonFeature ();
    		MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
    		MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
    		return MipJson::getJson ();
    	}

    	if (! is_numeric ( $id )) {
    		MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
    		MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
    	} else {
    		try {
    			$kayttaja = Kayttaja::find ( $id );

    			if ($kayttaja) {

    				$inventointiprojektit = $kayttaja->inventointiprojektit;

    				if(count($inventointiprojektit) > 0) {
    					MipJson::addMessage(Lang::get('rakennus.search_success'));

    					$currentDate = date("Y-m-d");

    					$retInventointiprojektit = array();

    					foreach ($inventointiprojektit as $inventointiprojekti) {
    						foreach ($inventointiprojekti->ajanjakso as $ajanjakso) {
    							if($ajanjakso->alkupvm <= $currentDate && ($ajanjakso->loppupvm >= $currentDate || $ajanjakso->loppupvm == null)) {
    								array_push($retInventointiprojektit, $inventointiprojekti);
    								break;
    							}
    						}
    					}

    					//Set the amount of selected rows
    					$total_rows = count($retInventointiprojektit);
    					MipJson::initGeoJsonFeatureCollection($total_rows, $total_rows);

    					//Actually include the selected ones in the return message
    					foreach($retInventointiprojektit as $inventointiprojekti) {
    						MipJson::addGeoJsonFeatureCollectionFeaturePoint(null, $inventointiprojekti);
    					}
    				}

    			} else {
    				MipJson::setGeoJsonFeature ();
    				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
    				MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
    			}
    		} catch ( QueryException $e ) {
    			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_failed' ) );
    			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
    		}
    	}
    	return MipJson::getJson ();
    }
}
