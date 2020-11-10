<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class LogController extends Controller {
	
	/**
	 * Log errors from front end to the frontend log file. Authorization required.
	 *
	 * @param request    		 
	 */
	public function log(Request $request) {
		
		if (!Auth::check ()) {
			return "Not authorized";
		}
				
		$e = $request->all();
		
		//Append the userid to the error
		$e['userid'] = Auth::id();
		
		// TECH UPGRADE: Logging has changed between Laravel 5.5 - 5.6
		//Log the error. See config/logging.php
		Log::channel('frontend')->warning(implode("|", $e));
		
		return "Logged";

	}
}
