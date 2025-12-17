<?php
namespace App\Http\Middleware;


use Illuminate\Support\Facades\Log;
use Exception;
use App\Library\String\MipJson;
use Closure;

class ApiKeyMiddleware
{
  public function handle($request, Closure $next)
  {
    Log::debug(json_encode($request->query()));
    $params = $request->query();
    $key = $params["api_key"];
    if (!$key || $key !== config('app.prikka_api_key')) {
      Log::channel('prikka')->error("Wrong apikey");
      MipJson::addMessage("Wrong apikey");
      return MipJson::getJson();
    }
    return $next($request);
  }
}