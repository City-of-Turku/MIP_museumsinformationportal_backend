<?php
namespace App\Library\Reports;

use App\Kyla;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;

class ReportServer {

	//Not used in future anymore
	public static function getClient() {

		$url = config('app.report_server_url');
		$username = config('app.report_server_user');
		$password = config('app.report_server_password');

		return new Client(
			$url,
			$username,
			$password,
			"" // some org??
		);
	}

	public static function generateKiinteistoraporttiParameters($inputParams) {
		//The fields the user has selected to be shown
		$valitutKentat = $inputParams['valitutKentat'];

		//The values for filtering the data
		$paikkakunnat = $inputParams['paikkakunnat'];
		$kylat = $inputParams['kylat'];
		$kunnat = $inputParams['kunnat'];

		$parameters = array(
				array('name' => 'reportDisplayName', 'value' => $inputParams['reportDisplayName'])
		);

		if(sizeof($paikkakunnat) > 0) {
			array_push($parameters, array('name' => 'paikkakunnat', 'value' => implode(',', $paikkakunnat)));
		}
		if(sizeof($kylat) > 0) {
			array_push($parameters, array('name' => 'kylat', 'value' => implode(',', $kylat)));
		}
		if(sizeof($kunnat) > 0) {
			array_push($parameters, array('name' => 'kunnat', 'value' => implode(',', $kunnat)));
		}
		if(sizeof($valitutKentat) > 0) {
			array_push($parameters, array('name' => 'valitutKentat', 'value' => implode(',', $valitutKentat)));
		}

		return $parameters;
	}

	public static function generateKuntaraporttiParameters($inputParams) {
		//The fields the user has selected to be shown
		$valitutKentat = $inputParams['valitutKentat'];

		//The values for filtering the data
		$paikkakunta =  $inputParams['paikkakunta'];
		$kylat = $inputParams['kylat'];
		$kunnat = $inputParams['kunnat'];

		$parameters = array(
				array('name' => 'reportDisplayName', 'value' => $inputParams['reportDisplayName'])
		);

		if(!empty($paikkakunta)){
		    array_push($parameters, array('name' => 'paikkakunta_input', 'value' => $paikkakunta));
		}
		if(sizeof($kylat) > 0) {
			array_push($parameters, array('name' => 'kylat', 'value' => implode(',', $kylat)));
		}
		if(sizeof($kunnat) > 0) {
			array_push($parameters, array('name' => 'kunnat', 'value' => implode(',', $kunnat)));
		}
		if(sizeof($valitutKentat) > 0) {
			array_push($parameters, array('name' => 'valitutKentat', 'value' => implode(',', $valitutKentat)));
		}

		return $parameters;
	}

	public static function generateYhteenvetoraporttiParameters($inputParams) {
	    $parameters = array(
	        array('name' => 'reportDisplayName', 'value' => $inputParams['reportDisplayName'])
	    );

	    $kylat = null;//$inputParams['kylat'];
	    $kunta = null;//$inputParams['kunta'];

	    if(isset($inputParams['inventointiprojekti_id'])) {
	        //kaikki mahdolliset kylät mukaan, koska tätä tietoa ei tule muutoin
	        $k = Kyla::getAll('id', 'asc')->get();
	        $kylat = [];
	        foreach ($k as $kyla) {
	            array_push($kylat, $kyla->id);
	        }
	        //Lisäksi tarvitaan "jokin kunta" jotta saadaan raportti luotua.
	        $kunta = $k[0]->kunta_id;

	        array_push($parameters, array('name' => 'inventointiprojekti_id', 'value' => $inputParams['inventointiprojekti_id']));
	    } else {
	        $kylat = $inputParams['kylat'];
	        $kunta = $inputParams['kunta'];
	    }

	    if(sizeof($kylat) > 0) {
	        array_push($parameters, array('name' => 'kyla_idt', 'value' => implode(',', $kylat)));
	    }

	    array_push($parameters, array('name' => 'kunta_id', 'value' => $kunta));

		return $parameters;
	}


	public static function generateKohderaporttiParameters($inputParams) {
		$parameters = array(
				array(
						'name' => 'kiinteisto_id',
						'value' => $inputParams['kiinteisto_id']),
				array(
						'name' => 'reportDisplayName',
						'value' => $inputParams['reportDisplayName']
				)
		);
		return $parameters;
	}

	public static function generateAlueraporttiParameters($inputParams) {
		$parameters = array(
				array(
						'name' => 'alue_id',
						'value' => $inputParams['alue_id']
				),
				array(
						'name' => 'reportDisplayName',
						'value' => $inputParams['reportDisplayName']
				)
		);
		return $parameters;
	}

	public static function generateMatkaraporttiParameters($inputParams) {
		$parameters = array(
				array(
						'name' => 'matkaraportti_id',
						'value' => $inputParams['matkaraportti_id']
				),
				array(
						'name' => 'reportDisplayName',
						'value' => $inputParams['reportDisplayName']
				)
		);
		return $parameters;
	}

	public static function generateMatkaraporttikoosteParameters($inputParams) {
		//The fields the user has selected to be shown
		$valitutKentat = $inputParams['valitutKentat'];
		$syyt = array();
		//The values for filtering the data
		if(isset($inputParams['syyt'])) {
			$syyt = $inputParams['syyt'];
		}


		$parameters = array(
				array('name' => 'reportDisplayName', 'value' => $inputParams['reportDisplayName']),
				array('name' => 'report_name', 'value' => $inputParams['report_name'])
		);

		if(sizeof($valitutKentat) > 0) {
			array_push($parameters, array('name' => 'valitutKentat', 'value' => implode(',', $valitutKentat)));
		}
		if(sizeof($syyt) > 0) {
			array_push($parameters, array('name' => 'syyt', 'value' => implode(',', $syyt)));
		}
		if(isset($inputParams['kiinteisto_id'])) {
			array_push($parameters, array('name' => 'kiinteisto_id', 'value' => $inputParams['kiinteisto_id']));
		}
		if(isset($inputParams['kayttaja_id'])) {
			array_push($parameters, array('name' => 'kayttaja_id', 'value' => $inputParams['kayttaja_id']));
		}
		if(isset($inputParams['matkapaiva_alku'])) {
			array_push($parameters, array('name' => 'pvm_alku', 'value' => $inputParams['matkapaiva_alku']));
		}
		if(isset($inputParams['matkapaiva_loppu'])) {
			array_push($parameters, array('name' => 'pvm_loppu', 'value' => $inputParams['matkapaiva_loppu']));
		}

		return $parameters;
	}

	public static function generateInventointiprojektiraporttiParameters($inputParams) {
		//The fields the user has selected to be shown
		$valitutKentat = $inputParams['valitutKentat'];

		$inventointipaiva_alku = null;
		$inventointipaiva_loppu = null;

		//The values for filtering the data
		if(isset($inputParams['inventointipaiva_alku'])) {
			$inventointipaiva_alku = $inputParams['inventointipaiva_alku'];
		}
		if(isset($inputParams['inventointipaiva_loppu'])) {
			$inventointipaiva_loppu = $inputParams['inventointipaiva_loppu'];
		}

		$parameters = array(
				array(
						'name' => 'inventointiprojekti_id',
						'value' => $inputParams['inventointiprojekti_id']
				),
				array(
						'name' => 'reportDisplayName',
						'value' => $inputParams['reportDisplayName']
				)
		);

		if($inventointipaiva_alku != null) {
			array_push($parameters, array('name' => 'inventointipaiva_alku', 'value' => $inventointipaiva_alku));
		}
		if($inventointipaiva_loppu != null) {
			array_push($parameters, array('name' => 'inventointipaiva_loppu', 'value' => $inventointipaiva_loppu));
		}
		if(sizeof($valitutKentat) > 0) {
			array_push($parameters, array('name' => 'valitutKentat', 'value' => implode(',', $valitutKentat)));
		}

		return $parameters;
	}

	public static function generateVuosiraporttiParameters($inputParams) {
		//The fields the user has selected to be shown
		$valitutKentat = $inputParams['valitutKentat'];

		$pvm_alku = null;
		$pvm_loppu = null;

		//The values for filtering the data
		if(isset($inputParams['pvm_alku'])) {
			$pvm_alku = $inputParams['pvm_alku'];
		}
		if(isset($inputParams['pvm_loppu'])) {
			$pvm_loppu = $inputParams['pvm_loppu'];
		}

		//The values for filtering the data
		$paikkakunta =  $inputParams['paikkakunta'];
		$kylat = $inputParams['kylat'];
		$kunnat = $inputParams['kunnat'];

		$parameters = array(
				array(
						'name' => 'reportDisplayName',
						'value' => $inputParams['reportDisplayName']
				)
		);

		if($pvm_alku!= null) {
			array_push($parameters, array('name' => 'pvm_alku', 'value' => $pvm_alku));
		}
		if($pvm_loppu != null) {
			array_push($parameters, array('name' => 'pvm_loppu', 'value' => $pvm_loppu));
		}
		if(!empty($paikkakunta)){
		    array_push($parameters, array('name' => 'paikkakunta_input', 'value' => $paikkakunta));
		}
		if(sizeof($kylat) > 0) {
			array_push($parameters, array('name' => 'kylat', 'value' => implode(',', $kylat)));
		}
		if(sizeof($kunnat) > 0) {
			array_push($parameters, array('name' => 'kunnat', 'value' => implode(',', $kunnat)));
		}
		if(sizeof($valitutKentat) > 0) {
			array_push($parameters, array('name' => 'valitutKentat', 'value' => implode(',', $valitutKentat)));
		}

		return $parameters;
	}

	//Samat parametrit molemmille löyröraporttityypeille.
	public static function generateLoytoraporttiParameters($inputParams) {
	    $parameters = array(
	        array(
	            'name' => 'tutkimusId',
	            'value' => $inputParams['tutkimus_id']),
	        array(
	            'name' => 'reportDisplayName',
	            'value' => $inputParams['reportDisplayName']
	        )
	    );
	    return $parameters;
	}

	public static function generateNayteluetteloParameters($inputParams) {
		$parameters = array(
				array(
					'name' => 'tutkimusId',
					'value' => $inputParams['tutkimusId']
				),
				array(
					'name' => 'naytekoodi',
					'value' => $inputParams['naytekoodi']
				),
				array(
					'name' => 'reportDisplayName',
					'value' => $inputParams['reportDisplayName']
				)
			);
		return $parameters;
	}

	public static function generateKarttaluetteloParameters($inputParams) {
		$parameters = array(
				array(
					'name' => 'tutkimusId',
					'value' => $inputParams['tutkimusId']
				),
				array(
					'name' => 'reportDisplayName',
					'value' => $inputParams['reportDisplayName']
				)
			);
		return $parameters;
	}

	public static function generateValokuvaluetteloParameters($inputParams) {
		$parameters = array(
				array(
					'name' => 'tutkimusId',
					'value' => $inputParams['tutkimusId']
				),
				array(
					'name' => 'reportDisplayName',
					'value' => $inputParams['reportDisplayName']
				)
			);
		return $parameters;
	}

	public static function generateLoytoLuettelointikortitParameters($inputParams) {
		$idStr = implode(",", $inputParams['loyto_idt']);
		$parameters = array(
				array(
						'name' => 'loyto_idt',
						'value' => $idStr),
				array(
						'name' => 'reportDisplayName',
						'value' => $inputParams['reportDisplayName']
				)
		);
		return $parameters;
	}
	/*
	 * Koriraportin parametrit.
	 */
	public static function generateKoriraporttiParameters($inputParams, $koriId) {

	    $valitutKentat = $inputParams['valitutKentat'];

	    $parameters = array(
	        array(
	            'name' => 'koriId',
	            'value' => $koriId),
	        array(
	            'name' => 'reportDisplayName',
	            'value' => $inputParams['reportDisplayName']
	        )
	    );

	    if(sizeof($valitutKentat) > 0) {
	        array_push($parameters, array('name' => 'valitutKentat', 'value' => implode(',', $valitutKentat)));
	    }

	    return $parameters;
	}

	//Tarkastustutkimusraportti
	public static function generateTarkastusraporttiParameters($inputParams) {
	    $parameters = array(
	        array(
	            'name' => 'tutkimusId',
	            'value' => $inputParams['tutkimus_id']),
	        array(
	            'name' => 'reportDisplayName',
	            'value' => $inputParams['reportDisplayName']
	        )
	    );
	    return $parameters;
	}

	// Löydön konservointiraportti
	public static function generateLoytoKonservointiraporttiParameters($inputParams) {
	      $parameters = array(
	          array('name' => 'loytoId', 'value' => $inputParams['loytoId']),
	          array('name' => 'reportDisplayName', 'value' => $inputParams['reportDisplayName']),
	          array('name' => 'kons_toimenpiteet', 'value' => $inputParams['kons_toimenpiteet']),
	          array('name' => 'raportti_luoja', 'value' => Auth::user()->etunimi . " " . Auth::user()->sukunimi)
	      );
	      return $parameters;
	}

	// Kuntoraportti
	public static function generateKuntoraporttiParameters($inputParams) {
		$parameters = array(
			array('name' => 'kuntoraporttiId', 'value' => $inputParams['kuntoraporttiId']),
			array('name' => 'reportDisplayName', 'value' => $inputParams['reportDisplayName']),
			array('name' => 'konservaattori', 'value' => $inputParams['konservaattori'])
		);
		return $parameters;
	}

	// Tutkimusraportti
	public static function generateTutkimusraporttiParameters($inputParams) {
		$parameters = array(
			array('name' => 'tutkimusraporttiId', 'value' => $inputParams['tutkimusraporttiId']),
			array('name' => 'reportDisplayName', 'value' => $inputParams['reportDisplayName']),
			array('name' => 'laji', 'value' => $inputParams['laji'])
		);
		return $parameters;
	}

}