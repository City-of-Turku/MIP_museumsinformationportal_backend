<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;




/*
 * NOT IN USE!
 */



class MigrationController extends Controller {
	
	/*
	 * Configuration:
	 * 
	 * It is MANDATORY that these variables will be changed 
	 * to match the enviroment and server where this script is executed
	 */
	private $migrationFile 		= "../storage/IS_MIGRATED";
	private $schema				= "rakennusinventointi_2011"; // schema to work with 
	private $passwd_reset_url	= "/migration_password"; // url address to include in email where user can set their new password
	
	private $REQUIRED_PHP 		= 5.5;
	private $REQUIRED_POSTGRES 	= 9.4;
	private $REQUIRED_POSTGIS	= 2.1;
	
	
	/**
	 * Method to start executing the migration from old mip version
	 * 
	 * @return string
	 * @author 
	 * @version 1.0
	 * @since
	 */
    public function start(Request $request) {
    	
    	if(self::isMigrated()) {
    		return "Sorry, migration is already done, please delete migration status file [".$this->migrationFile."]";
    	}
    	else {
    		// set the migration status to "migration already done"
    		//self::setMigrated();
    		
    		switch($request->step) {
    			case 0:
    				/*
    				 * Check the server for requirements
    				 */
    				return self::checkServerRequirements();
    				
    				break;
    			
    			case 1:
    				/*
    				 * fix user passwords.
    				 * Here we list all "active" users from DB and send email to them
    				 * and tell about release of the new system.
    				 * In this email there is a link to new MIP where user can set new password
    				 */
    				return self::sendUserPasswordChangeMail();
    				
    				break;
    				
    			case 3: 
    				return "Final step, migration is done!<br>";
    				
    				break;
    			
    			default:
    				print "Error, Unknown Step!";
    				break;
    		}
    	}
    }
    
    /**
     * Migration step0 - check the requirements of the server
     * 
     * @return \Illuminate\View\$this
     * @author 
     * @version 1.0
     * @since
     */
    private function checkServerRequirements() {
    	
    	$requirements_ok = true;
    	if((float)PHP_VERSION < $this->REQUIRED_PHP
    		|| !extension_loaded('pdo') || !extension_loaded('pdo_pgsql') || !extension_loaded('mbstring')
    		|| !extension_loaded('openssl') || !extension_loaded('tokenizer') || !extension_loaded('json')
    		|| ((float)explode(" ",(DB::connection()->getPdo()->query('SELECT version() as version')->fetchColumn()))[1]<$this->REQUIRED_POSTGRES)
    		|| ((float)DB::connection()->getPdo()->query('SELECT PostGIS_lib_version() as version')->fetchColumn() < $this->REQUIRED_POSTGIS)
    		|| (!file_exists( explode(" ", Config::get("mail.sendmail"))[0] ) ) 
    		)  {
    		$requirements_ok = false;
    	}
    	
    	$view_data = array(
    		"php" 					=> PHP_VERSION,
    		"php_status" 			=> ((float)PHP_VERSION >= $this->REQUIRED_PHP) ? "OK" : "FAIL",
    	
    		"php_pdo"				=> (extension_loaded('pdo')) ? "Y" : "N",
    		"php_pdo_status"		=> (extension_loaded('pdo')) ? "OK" : "FAIL",
    	
    		"php_pdopgsql"			=> (extension_loaded('pdo_pgsql')) ? "Y" : "N",
    		"php_pdopgsql_status"	=> (extension_loaded('pdo_pgsql')) ? "OK" : "FAIL",
    	
    		"php_mbstring"			=> (extension_loaded('mbstring')) ? "Y" : "N",
    		"php_mbstring_status"		=> (extension_loaded('mbstring')) ? "OK" : "FAIL",
    	
    		"php_openssl"			=> (extension_loaded('openssl')) ? "Y" : "N",
    		"php_openssl_status"	=> (extension_loaded('openssl')) ? "OK" : "FAIL",
    	
    		"php_tokenizer"			=> (extension_loaded('tokenizer')) ? "Y" : "N",
    		"php_tokenizer_status"	=> (extension_loaded('tokenizer')) ? "OK" : "FAIL",
    	
    		"php_json"				=> (extension_loaded('json')) ? "Y" : "N",
    		"php_json_status"		=> (extension_loaded('json')) ? "OK" : "FAIL",
    	
    		"postgres"				=> explode(" ",DB::connection()->getPdo()->query('SELECT version() as version')->fetchColumn())[1],
    		"postgres_status"		=> (float)explode(" ",(DB::connection()->getPdo()->query('SELECT version() as version')->fetchColumn()))[1]>=$this->REQUIRED_POSTGRES ? "OK" : "FAIL",
    	
    		"postgis"				=> DB::connection()->getPdo()->query('SELECT PostGIS_lib_version() as version')->fetchColumn(),
    		"postgis_status"		=> ((float)DB::connection()->getPdo()->query('SELECT PostGIS_lib_version() as version')->fetchColumn() >= $this->REQUIRED_POSTGIS) ? "OK" : "FAIL",
    	
    		"sendmail" 				=> (file_exists(explode(" ", Config::get("mail.sendmail"))[0])) ? "Y" : "N",
    		"sendmail_status"		=> (file_exists(explode(" ", Config::get("mail.sendmail"))[0])) ? "OK" : "FAIL",
    	
    		"requirements_ok"		=> $requirements_ok,
    		"required_php"			=> $this->REQUIRED_PHP,
    		"required_postgres"		=> $this->REQUIRED_POSTGRES,
    		"required_postgis"		=> $this->REQUIRED_POSTGIS,
    	);
    	
    	return view('migration/step0')->with($view_data);
    }
    
    /**
     * Cause old Mip version used md5 password encryption here in new version we do not want to use that
     * So we need to send an email for users which contais URL link where they can set their new password
     * Old password is required to do that!
     * 
     * @author 
     * @version 1.0
     * @since
     */
 	public function sendUserPasswordChangeMail() {
 		$users = DB::table($this->schema.".kayttaja")
 			->where('aktiivinen', 't')
 			->where('sahkoposti', 'joni.hamalainen@varsinais-suomi.fi')
 			//->orWhere('sahkoposti', 'esa.halsti@lounaispaikka.fi')
 			//->orWhere('sahkoposti', 'sanna.jokela@varsinais-suomi.fi')
 			->orderBy('sukunimi', 'ASC')
 			->get();
 		
 		/*
 		 * Go through found users and send email to them about setting their new password.
 		 */
 		//$view_data = array();
 		foreach ($users as $user){
 			
 			$data = array(
 				"firstname" => $user->etunimi,
 				"passwd_change_url" => "http://".$_SERVER['SERVER_NAME'].$this->passwd_reset_url."/".$user->sahkoposti
 			);
 			Mail::send('migration.password_change', $data, function($message) use ($user) {
 				$message->subject('Mip - Museon Inventointiportaali uudistuu!');
 				$message->from('mip@lounaispaikka.fi', 'Mip');
 				$message->to($user->sahkoposti);
 			});
 		}
 		
 		return view("migration/step1")->with("users", $users);
 	}
 	
 	/**
 	 * Show the password changing form for user
 	 * --> user has received email containing link to this url...
 	 * 
 	 * @param Request $request
 	 * @return \Illuminate\View\$this
 	 * @author 
 	 * @version 1.0
 	 * @since
 	 */
 	public function getPasswordReset(Request $request){
 		
 		$data = array(
 			'username' => $request->username,
 			'password' =>md5('joni1hamalainen2'.md5('lougis'))
 		);
 		return view('migration.password_change_form')->with($data);
 	}
 	
 	/**
 	 * User has submitted password change form ... handle the request here
 	 * @param Request $request
 	 * @return multitype:boolean string multitype: Ambigous <multitype:, \Illuminate\Support\MessageBag> 
 	 * @author 
 	 * @version 1.0
 	 * @since
 	 */
 	public function postPasswordReset(Request $request) {
 		
 		$text = "";
 		$errors	= array();
 		$result = false;
 		
 		/*
 		 * Validate the user input
 		 */
 		$validator = Validator::make($request->all(), [
 			'username' 			=> 'required|email',
 			'vanha_salasana' 	=> 'required|min:6',
 			'uusi_salasana'		=> 'required|min:6|confirmed|different:vanha_salasana'
 		]);
 		if ($validator->fails()) {
 			$text = "Lomakkeen validointi ei onnistunut.";
 			$errors = $validator->errors();
 		}
 		else {
 			// check if old password matches with DB
 			$oldpwd = DB::table($this->schema.'.kayttaja')
 				->where('sahkoposti', $request->username)->pluck('salasana');
 			if(md5($request->vanha_salasana.md5('lougis')) == $oldpwd) {
 				
 				// set new password for user in DB
		 		DB::table($this->schema.".kayttaja")
		 			->where('sahkoposti', $request->username)
		 			->update(['salasana' => Hash::make($request->uusi_salasana)]);
		 		
 				$result = true;
 				$text = "Salasanasi on nyt vaihdettu!";
 				array_push($errors, $text);
 			}
 			else {
 				$text = "Vanha salasanasi on virheellinen!";
 				array_push($errors, $text);
 			}
 		}
 		
 		/*
 		 * Return the result here
 		 */
 		return array(
 				'result' 	=> $result,
 				'text'		=> $text,
 				'login'		=> Auth::check(),
 				"errors"	=> $errors,
 				'request'	=> $request->all()
 		);
 	}
    
 	/**
 	 * Method to check if migration is done already
 	 * 
 	 * @return boolean
 	 * @author 
 	 * @version 1.0
 	 * @since
 	 */
    public function isMigrated() {
    	return (file_exists($this->migrationFile));
    }
    
    /**
     * Method to set migration as "done" already (so we wont do migration again!)
     * 
     * @author 
     * @version 1.0
     * @since
     */
    public function setMigrated() {
    	/*
    	 * Create migration file so we can determine if migration is already done!
    	 */
    	$fp = fopen($this->migrationFile, 'w') or die("can't create migration status file [".$this->migrationFile."]");
    	fclose($fp);
    }
}
