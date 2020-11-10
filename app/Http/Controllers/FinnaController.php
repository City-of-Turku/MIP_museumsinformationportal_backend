<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use App\Integrations\Finna\FinnaService;
use App\Integrations\Finna\FinnaUtils;

class FinnaController extends Controller {

    public function index(Request $request) {

        Log::channel('finna')->info($request->fullUrl());

        // If validation fails, return the generated XML response
        $errorResponse = FinnaUtils::validateRequest($request);
        if($errorResponse) {
            return response($errorResponse, 200)->header('Content-Type', 'text/xml; charset=utf-8');
        }

        $parameters = $request->all(); // Parameters of the request
        $response = null; // Response to be returned
        $finnaService = new FinnaService(); // FinnaService for XML writing

        try {
            switch ($parameters['verb']) {
                case 'Identify':
                    $response = $finnaService->identify();
                    break;
                case 'ListRecords':
                    // Otetaan tarvittavat avaimet ja arvot
                    $from = array_key_exists('from', $parameters) == true ? $parameters['from'] : null;
                    $until = array_key_exists('until', $parameters) == true ? $parameters['until'] : null;
                    $cursorPos = array_key_exists('resumptionToken', $parameters) == true ? FinnaUtils::getCursorPositionFromParameters($parameters['resumptionToken']) : 0;
                    $finnaService->setCursor($cursorPos);
                    $response = $finnaService->listRecords($from, $until);
                    break;
                case 'ListIdentifiers':
                    // Otetaan tarvittavat avaimet ja arvot
                    $from = array_key_exists('from', $parameters) == true ? $parameters['from'] : null;
                    $until = array_key_exists('until', $parameters) == true ? $parameters['until'] : null;
                    $cursorPos = array_key_exists('resumptionToken', $parameters) == true ? FinnaUtils::getCursorPositionFromParameters($parameters['resumptionToken']) : 0;
                    $finnaService->setCursor($cursorPos);
                    $response = $finnaService->listIdentifiers($from, $until);
                    break;
                case 'GetRecord':
                    $identifier = $parameters['identifier'];
                    $response = $finnaService->getRecord($identifier);
                    break;
            }
        } catch(Exception $e) {
            $requestDateTime = Carbon::now();
            Log::channel('finna')->error("OAI-PMH error. Request: " . $request->fullUrl() . "\n" . $e->getTraceAsString());
            return response('Server error. Please contact support (' . $finnaService->ADMINEMAIL. '). Request timestamp: ' . $requestDateTime , 500);
        }

        // Palautetaan OAI-PMH xml response tai error 500.
        if ($response) {
            return response($response, 200)->header('Content-Type', 'text/xml; charset=utf-8');
        } else {
            return response('Server error', 500);
        }
    }
}
