<?php

namespace App\Http\Controllers\Auth;

use App\Kayttaja;
use App\Http\Controllers\Controller;
use App\Library\String\MipJson;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller {
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    //use AuthenticatesAndRegistersUsers, ThrottlesLogins;
//     use ThrottlesLogins, AuthenticatesUsers {
//         ThrottlesLogins::hasTooManyLoginAttempts insteadof AuthenticatesUsers;
//         ThrottlesLogins::incrementLoginAttempts insteadof AuthenticatesUsers;
//         ThrottlesLogins::sendLockoutResponse insteadof AuthenticatesUsers;
//         ThrottlesLogins::clearLoginAttempts insteadof AuthenticatesUsers;
//     }
    use AuthenticatesUsers;

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct() {
        //$this->middleware('guest', ['except' => 'logout']);


        /*
         * Maybe it is cleaner idea to define middlewares in routes file?
         * However, our AuthController is a little special controller isn't it?
         */

    	/*
    	$this->middleware(
    		'throttle:2,1', ['only' => ['throttle']],
    		'jwt', ['except' => ['doLogin']]
    	);
    	*/

    }

    /**
     * Invalidate the JWT token of given user
     *
     * @return MipJson
     * @author
     * @version 1.0
     * @since 1.0
     */
    public function logout() {

    	if(JWTAuth::invalidate( JWTAuth::getToken() )) {
    		MipJson::addMessage(Lang::get('auth.custom.logout_success'));
    		MipJson::setResponseStatus(Response::HTTP_OK);
    	}
    	else {
    		MipJson::addMessage(Lang::get('auth.custom.logout_failed'));
    		MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    	}

    	return MipJson::getJson();
    }

    /**
     * Authenticate the user login attempt
     *
     * @param Request $request
     * @return MipJson
     * @author
     * @version 1.0
     * @since 1.0
     */
    public function login(Request $request) {

    	/*
    	 * User input validation
    	 *
    	 * If user input does not meet our requirements below
    	 * set the error message
    	 */
    	$validator = Validator::make($request->all(), [
    			'kayttajatunnus' => 'required|email',
    			'salasana' => 'required',
    	]);
    	if ($validator->fails()) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
    		foreach($validator->errors()->all() as $error)
    			MipJson::addMessage($error);
    		MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    	}
    	else {

    		/*
    		 * Login Throttling
    		 *
    		 * If the class is using the ThrottlesLogins trait, we can automatically throttle
    		 * the login attempts for this application. We'll key this by the username and
    		 * the IP address of the client making these requests into this application.
    		 */
    		//$throttles = $this->isUsingThrottlesLoginsTrait();
    		/*
    		 * If user has tried to log in too many times in defined time
    		 * Lock the user out for defined amount of time
    		 * Also set error message
    		*/
    		if ($this->hasTooManyLoginAttempts($request)) {

    			// Get the remaining seconds before user can attempt next login
    			$seconds = app(RateLimiter::class)->availableIn(
    				$request->input($this->loginUsername()).$request->ip()
    			);
    			MipJson::setGeoJsonFeature();
    			MipJson::addMessage($this->getLockoutErrorMessage($seconds));
    			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		}

    		/*
    		 * If validator and Throttler has succeed
    		 * continue and attempt to log user in...
    		 */
    		else {

    			/*
    			 * Note:
    			 * The login PASSWORD field (in db) is defined in User model (getAuthPassword() -method)
    			 * So the PASSWORD field "key" MUST MATCH the one defined in User model!
    			 *
    			 * The login USERNAME field cannot be defined there - Laravel always uses the "key" of $credentials array
    			 *
    			 * While attempting to login, the $credentials array should ONLY containt 2 key/value pairs:
    			 * - username
    			 * - password
    			 */
    			$credentials = array(
    				// key			=>	value
    				'sahkoposti' 	=> $request->kayttajatunnus,
    				'password' 		=> $request->salasana,
    				'aktiivinen'	=> 't',
    				'poistettu'		=> null
    			);
    			
    			/*
    			 * Attempt to login
    			 * If login was successful clear the login attempt counter
    			 * and set "success" message
    			 */
    			if ( ! $token = JWTAuth::attempt($credentials)) {
					$this->incrementLoginAttempts($request);
					
    				MipJson::setGeoJsonFeature();
    				MipJson::addMessage("Tarkasta käyttäjätunnus ja salasana.");
    				MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
    			}
    			else {
    				//if ($throttles) {
    					$this->clearLoginAttempts($request);
    					MipJson::addMessage(Lang::get('auth.custom.login_success'));

    					//Get the user from the token
    					//$user = JWTAuth::toUser($token);
    					$user = JWTAuth::User();
    					//Get the permissions for the user
    					$user->oikeudet = Kayttaja::getAllPermissions();

    					//Remove password and password key from the users data as they are not needed
    					unset($user['salasana']);
    					unset($user['salasana_avain']);

    					MipJson::setGeoJsonFeature(null, array("kayttaja" =>$user, "token" => $token));
    				//}
    			}
    		} // end else (throttles)
    	} // end else (if validating user input succeed)

    	/*
    	 * Finally format and return the request response JSON
    	 */
    	return MipJson::getJson();
    }
}
