<?php

namespace App\Http\Controllers;

use App\Kayttaja;
use App\Library\Reports\ReportServer;
use App\Library\String\MipJson;
use App\Rak\Alue;
use App\Rak\Kiinteisto;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ReportController extends Controller {

	//'Supported' content types
	private static $content_types = [
			'pdf' => 'application/pdf',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
	];

	/*
	 * Get user reports from ReportServer
	 */
	public static function index($userid) {

	  $url = config('app.mip_reportserver_url') . "reportRequests/$userid";

		$client = new Client();
		$res = $client->request("GET", $url);

		if ($res->getStatusCode()!="200") {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('raportti.getting_reports_failed') . " " . $res->getStatusCode()." ".$res->getReasonPhrase());

			return MipJson::getJson();
		}

		// Reverse the response array (newest to oldest is better!)
		$json = $res->getBody();
		$json = json_decode($json, true);
		$result = array_reverse($json['data']);
		$json['data'] = $result;

		return json_encode($json);
	}

	public static function createReportRequest(Request $request) {

		$validator = Validator::make($request->all(), [
				'kayttajaId'		=> 'required|numeric',
				'tyyppi'			=> 'required|string',
				'parameters'		=> 'required|array'
		]);

		$tyyppi = $request->tyyppi;

		switch ($tyyppi) {
			case 'KohdeRaportti':
				$parameters = ReportServer::generateKohderaporttiParameters($request->parameters);
				break;
			case 'Alueraportti':
				$parameters = ReportServer::generateAlueraporttiParameters($request->parameters);
				break;
			case 'Kiinteistoraportti':
				$parameters = ReportServer::generateKiinteistoraporttiParameters($request->parameters);
				break;
			case 'Kuntaraportti':
				$parameters = ReportServer::generateKuntaraporttiParameters($request->parameters);
				//Pyydetään Kuntaraportista tiettyä "lajia" (kiinteistöt, rakennukset, alueet, arvoalueet)
				if($request->parameters['laji'] == 'kiinteistot') {
					$tyyppi = 'Kunta_kiinteistoraportti';
				} else if($request->parameters['laji'] == 'rakennukset') {
					$tyyppi = 'Kunta_rakennusraportti';
				} else if($request->parameters['laji'] == 'alueet') {
					$tyyppi = 'Kunta_alueraportti';
				} else if($request->parameters['laji'] == 'arvoalueet') {
					$tyyppi = 'Kunta_arvoalueraportti';
				}
				break;
			case 'Yhteenvetoraportti':
				$parameters = ReportServer::generateYhteenvetoraporttiParameters($request->parameters);
				break;
			case 'Matkaraportti':
				$parameters = ReportServer::generateMatkaraporttiParameters($request->parameters);
				break;
			case 'Matkaraportti_ilman_karttaa':
				$parameters = ReportServer::generateMatkaraporttiParameters($request->parameters);
				break;
			case 'Matkaraporttikooste':
				$parameters = ReportServer::generateMatkaraporttikoosteParameters($request->parameters);
				break;
			case 'Inventointiprojektiraportti':
				$parameters = ReportServer::generateInventointiprojektiraporttiParameters($request->parameters);
				//Pyydetään Inventointiprojektiraportista tiettyä "lajia" (kiinteistöt, rakennukset, alueet, arvoalueet)
				if($request->parameters['laji'] == 'kiinteistot') {
					$tyyppi = 'Inventointiprojekti_kiinteistoraportti';
				} else if($request->parameters['laji'] == 'rakennukset') {
					$tyyppi = 'Inventointiprojekti_rakennusraportti';
				} else if($request->parameters['laji'] == 'alueet') {
					$tyyppi = 'Inventointiprojekti_alueraportti';
				} else if($request->parameters['laji'] == 'arvoalueet') {
					$tyyppi = 'Inventointiprojekti_arvoalueraportti';
				}
				break;
			case 'Vuosiraportti':
				$parameters = ReportServer::generateVuosiraporttiParameters($request->parameters);
				//Pyydetään Vuosiraportista tiettyä "lajia" (kiinteistöt, rakennukset, alueet, arvoalueet)
				if($request->parameters['laji'] == 'kiinteistot') {
					$tyyppi = 'Vuosi_kiinteistoraportti';
				} else if($request->parameters['laji'] == 'rakennukset') {
					$tyyppi = 'Vuosi_rakennusraportti';
				} else if($request->parameters['laji'] == 'alueet') {
					$tyyppi = 'Vuosi_alueraportti';
				} else if($request->parameters['laji'] == 'arvoalueet') {
					$tyyppi = 'Vuosi_arvoalueraportti';
				}
				break;
			case 'Loytoraportti':
			     $parameters = ReportServer::generateLoytoraporttiParameters($request->parameters);
			     if($request->parameters['mode'] == 'loyto') {
			         $tyyppi = 'Loytoraportti';
			     } else if($request->parameters['mode'] == 'poistetut_loydot') {
			         $tyyppi = 'Poistetutloytoraportti';
			     }
					break;
			case 'Nayteluettelo':
					$parameters = ReportServer::generateNayteluetteloParameters($request->parameters);
					break;
			case 'Karttaluettelo':
					$parameters = ReportServer::generateKarttaluetteloParameters($request->parameters);
					break;
			case 'Valokuvaluettelo':
					$parameters = ReportServer::generateValokuvaluetteloParameters($request->parameters);
					break;
			case 'Loyto_luettelointikortit':
				$parameters = ReportServer::generateLoytoLuettelointikortitParameters($request->parameters);
				break;
			case 'Koriraportti':
                // Välitetään korin id. Jasper:ssa haetaan korista id lista.
			    $parameters = ReportServer::generateKoriraporttiParameters($request->parameters, $request->parameters['koriId']);

			    // Koriraportin lajit (kiinteistöt, rakennukset, alueet, arvoalueet, löydöt, näytteet)
			    if($request->parameters['laji'] == 'kiinteistot') {
			        $tyyppi = 'Kori_kiinteistoraportti';
			    } else if($request->parameters['laji'] == 'rakennukset') {
			        $tyyppi = 'Kori_rakennusraportti';
			    } else if($request->parameters['laji'] == 'alueet') {
			        $tyyppi = 'Kori_alueraportti';
			    } else if($request->parameters['laji'] == 'arvoalueet') {
			        $tyyppi = 'Kori_arvoalueraportti';
			    } else if($request->parameters['laji'] == 'loydot') {
			        $tyyppi = 'Kori_loytoraportti';
			    } else if($request->parameters['laji'] == 'naytteet') {
			        $tyyppi = 'Kori_nayteraportti';
			    }
			    break;
			case 'Tarkastusraportti':
			    $parameters = ReportServer::generateTarkastusraporttiParameters($request->parameters);
			    $tyyppi = 'Tarkastusraportti';
			    break;
			case 'Loyto_konservointiraportti':
			    // Ainoastaan tutkijat ja pääkäyttäjät voivat tehdä ko. raportin
			    if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
			        MipJson::setGeoJsonFeature();
			        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			        MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			        return MipJson::getJson();
			    }

			    $parameters = ReportServer::generateLoytoKonservointiraporttiParameters($request->parameters);
					break;
			case 'Kuntoraportti':
				// Ainoastaan tutkijat ja pääkäyttäjät voivat tehdä ko. raportin
				if(Auth::user()->ark_rooli != 'tutkija' && Auth::user()->ark_rooli != 'pääkäyttäjä') {
						MipJson::setGeoJsonFeature();
						MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
						MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
						return MipJson::getJson();
				}

				$parameters = ReportServer::generateKuntoraporttiParameters($request->parameters);
				break;
				case 'Tutkimusraportti':
					$parameters = ReportServer::generateTutkimusraporttiParameters($request->parameters);
					if($request->parameters['laji'] == 'inventointitutkimus') {
						$tyyppi = 'Inventointitutkimusraportti';
					} else if($request->parameters['laji'] == 'koekaivaus-kaivaus-konekaivuun_valvonta') {
						$tyyppi = 'Tutkimusraportti';
					} else {
						MipJson::setGeoJsonFeature();
						MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
						MipJson::addMessage(Lang::get('Invalid report type'));
						return MipJson::getJson();
					}
					break;
			default:
				MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
				MipJson::addMessage(Lang::get('raportti.invalid_report_type'));
				return MipJson::getJson();
		}

		$requestedOutputType = $request->parameters['requestedOutputType'];

		$url = config('app.mip_reportserver_url') . "reportRequest";
		$client = new Client();

		$user = auth()->user();
		$rr = array(
				'owner' => $user->id,
				'ownerEmail' => $user->sahkoposti,
				'requestedOutputType' => $requestedOutputType,
				'report' => array(
						'name' => $tyyppi
				),
				'parameters' => $parameters
		);

		$rr = json_encode($rr);

		// Log::debug($rr);

		$res = $client->request("POST", $url, [
				'http_errors' => false,
				'headers' => ['Content-Type' => 'application/json'],
				"body" => $rr
		]);

		if ($res->getStatusCode()!="200") {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('raportti.create_report_failed') . " " . $res->getStatusCode()." ".$res->getReasonPhrase());

			return MipJson::getJson();
		}

		return $res->getBody();
	}

	public static function deleteReportRequest($reportId) {

		$url = config('app.mip_reportserver_url') . "reportRequest/$reportId";
		$client = new Client();
		$res = $client->request("DELETE", $url);

		if ($res->getStatusCode()!="200") {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('raportti.delete_report_failed') . " " . $res->getStatusCode()." ".$res->getReasonPhrase());

			return MipJson::getJson();
		}

		return $res->getBody();
	}

	public static function getReportRequestStatus($reportId) {

		$url = config('app.mip_reportserver_url') . "reportRequest/$reportId";
		$client = new Client();
		$res = $client->request("GET", $url);

		if ($res->getStatusCode()!="200") {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('raportti.getting_report_status_failed') . " " . $res->getStatusCode()." ".$res->getReasonPhrase());

			return MipJson::getJson();
		}

		return $res->getBody();
	}

	public static function downloadReport($reportId, Request $request) {

		$url = config('app.mip_reportserver_url') . "reportRequest/$reportId/download";
		//echo $report;

		$client = new Client();
		$res = $client->request("GET", $url);

		if ($res->getStatusCode()!="200") {
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('raportti.getting_file_failed') . " " . $res->getStatusCode()." ".$res->getReasonPhrase());

			return MipJson::getJson();
		}

		$report =  $res->getBody();

		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename='.$request->filename);
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . strlen($report));
		header('Content-Type: application/octet');

		echo $report;
	}



	/*
	 * OLD REPORT FUNCTIONALITY BELOW
	 */
	public static function kiinteistotRaportti() {

		//Get the user from the token in url
		$user = JWTAuth::parseToken()->authenticate();

		/*
		 * Role check
		 */

		if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$c = ReportServer::getClient();
		$report = $c->reportService()->runReport('/MIP/Reports/kiinteistot', 'pdf');

		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename=KiinteistotRaportti.pdf');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . strlen($report));
		header('Content-Type: application/pdf');

		echo $report;
	}

	//Create a report, default type is pdf
	public static function kiinteistoRaportti($id, $type='pdf') {

		//Get the user from the token in url
		$user = JWTAuth::parseToken()->authenticate();

		/*
		 * Role check
		 */

		if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		/*
		 * Validations
		 */
		if(!is_numeric($id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}

		//Check that the kiinteisto is found
		$kiinteisto = Kiinteisto::getSingle($id)->first();

		if(!$kiinteisto) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
			MipJson::addMessage(Lang::get('kiinteisto.search_not_found'));
		}


		/*
		 * Set content type according to the requested type. If it's not 'supported'
		 * use octet-stream.
		 */
		if(array_key_exists($type, self::$content_types)) {
			$content_type = self::$content_types[$type];
		} else {
			$content_type = 'application/octet-stream';
		}

		/*
		 * Set the filename
		 */
		if($kiinteisto->palstanumero) {
			$filename = 'Kiinteistoraportti_' . $kiinteisto->kiinteistotunnus . '_' . $kiinteisto->palstanumero;
		} else {
			$filename = 'Kiinteistoraportti_' . $kiinteisto->kiinteistotunnus;
		}

		// do the report!
		$params = array(
				'kiinteisto_id' => $id,
				'MIP_BACKEND_URL' => config('app.mip_backend_url'),
				'MIP_MAPSERVER_URL' => config('app.geoserver_reporturl')
		);


		$c = ReportServer::getClient();
		$kohde_report_url = config('app.kohde_raportti_url');
		$report = $c->reportService()->runReport($kohde_report_url, $type, null, null, $params);

		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename='. $filename . '.'.$type);
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . strlen($report));
		header('Content-Type: ' . $content_type);

		echo $report;
	}

	//Create a report, default type is pdf
	public static function alueRaportti($id, $type='pdf') {

		//Get the user from the token in url
		$user = JWTAuth::parseToken()->authenticate();

		/*
		 * Role check
		 */

		if(!Kayttaja::hasPermission('rakennusinventointi.alue.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		/*
		 * Validations
		 */
		if(!is_numeric($id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}

		//Check that the alue is found
		$alue = Alue::getSingle($id)->first();

		if(!$alue) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
			MipJson::addMessage(Lang::get('alue.search_not_found'));
		}


		/*
		 * Set content type according to the requested type. If it's not 'supported'
		 * use octet-stream.
		 */
		if(array_key_exists($type, self::$content_types)) {
			$content_type = self::$content_types[$type];
		} else {
			$content_type = 'application/octet-stream';
		}

		/*
		 * Set the filename
		 */

		$filename = 'Alueraportti_' . $alue->nimi;


		// do the report!
		$params = array(
				'alue_id' => $id,
				'MIP_BACKEND_URL' => config('app.mip_backend_url'),
				'MIP_MAPSERVER_URL' => config('app.geoserver_reporturl')
		);


		$c = ReportServer::getClient();
		$alue_report_url = config('app.alue_raportti_url');
		$report = $c->reportService()->runReport($alue_report_url, $type, null, null, $params);

		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename='. $filename . '.'.$type);
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . strlen($report));
		header('Content-Type: ' . $content_type);

		echo $report;
	}

	//Create a report, default type is xlsx
	public function kiinteistoPerustietoRaportti(Request $request, $type = 'xlsx') {

		//Get the user from the token in url
		$user = JWTAuth::parseToken()->authenticate();

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		//The fields the user has selected to be shown
		$valitutKentat = $request->valitutKentat;

		//The values for filtering the data
		$paikkakunnat = $request->paikkakunnat;
		$kylat = $request->kylat;
		$kunnat = $request->kunnat;

		/*
		 * Set content type according to the requested type. If it's not 'supported'
		 * use octet-stream.
		 */
		if(array_key_exists($type, self::$content_types)) {
			$content_type = self::$content_types[$type];
		} else {
			$content_type = 'application/octet-stream';
		}

		/*
		 * Set the filename
		 */
		$filename = 'Kiinteistöraportti';

		//Set the parameters
		$params = array(
				'paikkakunnat' => $paikkakunnat,
				'kylat' => $kylat,
				'kunnat' => $kunnat,
				'valitutKentat' => $valitutKentat
		);

		// do the report!
		$c = ReportServer::getClient();
		$kiinteisto_raportti_url = config('app.kiinteisto_raportti_url');
		$report = $c->reportService()->runReport($kiinteisto_raportti_url, $type, null, null, $params);

		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename='. $filename . '.'.$type);
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . strlen($report));
		header('Content-Type: ' . $content_type);

		echo $report;

	}

}