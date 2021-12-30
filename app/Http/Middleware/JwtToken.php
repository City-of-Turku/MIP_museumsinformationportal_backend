<?php

namespace App\Http\Middleware;

use App\Library\String\MipJson;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Http\Middleware\BaseMiddleware;
use Closure;

class JwtToken extends BaseMiddleware {
	
	
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
    	
    	if (! $token = $this->auth->setRequest($request)->getToken()) {
    		MipJson::setGeoJsonFeature();
    		MipJson::addMessage(Lang::get('auth.custom.token_not_provided'));
    		MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    		return MipJson::getJson();	
        }
        
        try {
            $user = $this->auth->authenticate($token);
        } catch (TokenExpiredException $e) {
        	
			/*
			 * If token has expired try to refresh it
			 * If the ttl_refresh time has ALSO expired the refresh() attempt will throw an "TokeExpiredException" (which we will catch here)
			 */
        	try {
	        	$new_token = $this->auth->refresh($this->auth->getToken());
	        	MipJson::setGeoJsonFeature(null, array("token" => $new_token));
	        	
	        		/*
	        		 * If these are shown, would be resonable to have endpoint to refresh the token
	        		"token_created" => Carbon::createFromTimestamp($this->auth->getPayload()->get('iat'))->format("Y.m.d H:i:s"),
        			"token_expires" => Carbon::createFromTimestamp($this->auth->getPayload()->get('exp'))->format("Y.m.d H:i:s"),
	        		"token_refresh" => Carbon::createFromTimestamp($this->auth->getPayload()->get('iat')+(Config::get('jwt.refresh_ttl')*60))->format("Y.m.d H:i:s"),
	        		*/
	        	
	        	MipJson::addMessage(Lang::get('auth.custom.token_has_expired'));
        		MipJson::setResponseStatus(Response::HTTP_UNAUTHORIZED); // 401 - unauthorized
        	}
        	catch(TokenExpiredException $e) {
        		// authentication token cannot be refreshed anymore... refresh_ttl has expired!";
        		MipJson::addMessage(Lang::get('auth.custom.token_not_refreshable'));
        		MipJson::setResponseStatus(Response::HTTP_UNAUTHORIZED); // 401 - Unauthorized
        		MipJson::setGeoJsonFeature(null, array("token" => null));
        	}
        	return MipJson::getJson();
        }
        
        
        
        catch (JWTException $e) {
        	//return "JWTE";
        	// TODO: test if this is the case when token TTL and REFRESH_TTL has expired?
        	// --> if so, return status code that indicates that "re-authentication" is required!
        	MipJson::setGeoJsonFeature();
        	MipJson::addMessage(Lang::get('auth.custom.token_not_valid'));
        	MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
        	return MipJson::getJson();
        }

        if (! $user) {
        	MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('auth.custom.user_not_found'));
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            return MipJson::getJson();
        }

        $this->events->fire('tymon.jwt.valid', $user);
        return $next($request);
    }
}
