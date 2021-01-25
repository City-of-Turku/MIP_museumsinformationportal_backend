<?php

namespace App\Http\Controllers;

use App\Kayttaja;
use App\Utils;
use App\Ark\ArkTiedosto;
use App\Ark\Kohde;
use App\Ark\KonsToimenpiteet;
use App\Ark\KonservointiKasittely;
use App\Ark\Loyto;
use App\Ark\Nayte;
use App\Ark\Rontgenkuva;
use App\Ark\Tutkimus;
use App\Ark\TutkimusalueYksikko;
use App\Library\Geometry\GeomFileReader;
use App\Library\String\MipJson;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

/**
 * Ark puolen tiedostojen controller.
 */
class ArkTiedostoController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {

		/*
		 * Role check
		 */
	    $hasPermission = false;
	    if($request->input('ark_kohde_id')){
	        if(!Kayttaja::hasPermission('arkeologia.ark_kohde.katselu')) {
	            MipJson::setGeoJsonFeature();
	            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
	            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
	            return MipJson::getJson();
	        }
	    }
	    else{
	        if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_tiedosto.katselu', $request->input('ark_tutkimus_id'))) {
    	        MipJson::setGeoJsonFeature();
    	        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
    	        MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
    	        return MipJson::getJson();
	        }
	    }

		$validator = Validator::make ( $request->all(), [
				"ark_rontgenkuva_id" => "numeric|exists:ark_rontgenkuva,id",
		] );

		if ($validator->fails ()) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			foreach ( $validator->errors ()->all () as $error ) {
				MipJson::addMessage ( $error );
			}
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			return MipJson::getJson ();
		}
 		try {

			$rivi = (isset ( $request->rivi ) && is_numeric ( $request->rivi )) ? $request->rivi : 0;
			$riveja = (isset ( $request->rivit ) && is_numeric ( $request->rivit )) ? $request->rivit : 100;
			$jarjestys_kentta = (isset ( $request->jarjestys )) ? $request->jarjestys : "id";
			$jarjestys_suunta = (isset ( $request->jarjestys_suunta )) ? ($request->jarjestys_suunta == "nouseva" ? "desc" : "asc") : "asc";

			$entities = ArkTiedosto::orderBy ( $jarjestys_kentta, $jarjestys_suunta );

			// Haetaan tutkimus id:n avulla vain, jos ei löydy muita
			if ($request->get ("ark_kohde_id")) {
			    $entities->withKohdeId ( $request->get ( "ark_kohde_id" ) );
			}
			else {
			    if ($request->get ( "ark_rontgenkuva_id" )) {
			        $entities->withXrayId ( $request->get ( "ark_rontgenkuva_id" ) );
			    }
    			else if ($request->get ("ark_kons_toimenpiteet_id")) {
    			    $entities->withToimenpiteetId ( $request->get ( "ark_kons_toimenpiteet_id" ) );
    			}
    			else if ($request->get ("ark_kons_kasittely_id")) {
    			    $entities->withKasittelyId ( $request->get ( "ark_kons_kasittely_id" ) );
    			}
    			else if ($request->get ("ark_loyto_id")) {
    			    $entities->withLoytoId ( $request->get ( "ark_loyto_id" ) );
    			}
    			else if ($request->get ("ark_nayte_id")) {
    			    $entities->withNayteId ( $request->get ( "ark_nayte_id" ) );
    			} else if($request->input("ark_yksikko_id")) {
    			    $entities->withYksikkoId($request->input("ark_yksikko_id"));
    			}
    			else {
    			    if ($request->get ("ark_tutkimus_id")) {
    			        $entities->withTutkimusId ( $request->get ( "ark_tutkimus_id" ) );
    			    }
    			}
			}

			$total_rows = Utils::getCount ( $entities );
			$entities->withLimit ( $rivi, $riveja );
			$entities = $entities->with ( array (
					'luoja',
					'muokkaaja',
			        'tiedostoloydot.loyto',
			        'tiedostonaytteet.nayte',
			        'tiedostoyksikot.yksikko',
			        'tiedostotutkimus.tutkimus'
			) )->get ();

			if (count ( $entities ) <= 0) {
				MipJson::initGeoJsonFeatureCollection(count($entities), $total_rows);
				MipJson::addMessage ( Lang::get ( 'tiedosto.search_not_found' ) );
				return MipJson::getJson ();
			}

			MipJson::initGeoJsonFeatureCollection ( count ( $entities ), $total_rows );

			foreach ( $entities as $entity ) {
				$entity->url = config ( 'app.attachment_server' ) . "/" . config ( 'app.attachment_server_baseurl' ) . $entity->polku . $entity->tiedostonimi;

				//Fiksataan myös löydöt ja yksiköt
				$tmpLoydot = [];
				foreach($entity->tiedostoloydot as $kl) {
				    array_push($tmpLoydot, $kl->loyto);
				}
				$tmpNaytteet = [];
				foreach($entity->tiedostonaytteet as $kn) {
				    array_push($tmpNaytteet, $kn->nayte);
				}
				$tmpYksikot = [];
				foreach($entity->tiedostoyksikot as $ky) {
				    array_push($tmpYksikot, $ky->yksikko);
				}

				if($entity->tiedostotutkimus) {
				    $entity->tutkimus = $entity->tiedostotutkimus->tutkimus;
				    unset($entity->tiedostotutkimus);
				}

				$entity->loydot = $tmpLoydot;
				$entity->naytteet = $tmpNaytteet;
				$entity->yksikot = $tmpYksikot;
				unset($entity->tiedostoloydot);
				unset($entity->tiedostonaytteet);
				unset($entity->tiedostoyksikot);

				MipJson::addGeoJsonFeatureCollectionFeaturePoint ( null, $entity->toArray () );
			}
			MipJson::addMessage ( Lang::get ( 'tiedosto.found_count', [
					"count" => count ( $entities )
			] ) );

 		} catch ( Exception $e ) {
 			MipJson::setGeoJsonFeature ();
 			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
 			MipJson::addMessage ( Lang::get ( 'tiedosto.search_failed' ) );
 		}

		return MipJson::getJson ();
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {
		if (isset ( $request->mode ) && $request->mode === 'lisaa_tutkimusalue' && isset ( $request->entiteetti_tyyppi ) && $request->entiteetti_tyyppi == 16) {
			// Luetaan tutkimusalueet tiedostosta.
			return self::lisaaTutkimusalueTiedostosta ( $request );
		} else if (isset ( $request->mode ) && $request->mode === 'lisaa_kohde' && isset ( $request->entiteetti_tyyppi ) && $request->entiteetti_tyyppi == 15) {
			// Luetaan kohteen sijainnit tiedostosta.
			return self::lisaaKohdeTiedostosta ( $request );
		} else if (isset ( $request->mode ) && $request->mode === 'lisaa_koordinaatit' && isset ( $request->entiteetti_tyyppi ) && ($request->entiteetti_tyyppi == 18 || $request->entiteetti_tyyppi == 17)) {
		    // Luetaan koordinaatit tiedostosta.
		    return self::lisaaKoordinaatitTiedostosta ( $request );
		}
		else {
			if(!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_tiedosto.luonti', $request->input('ark_tutkimus_id'))) {
                MipJson::setGeoJsonFeature();
                MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
                MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
                return MipJson::getJson();
            }

			$maxFileSize = config ( 'app.max_file_size' );

			/*
			 * TODO: Validoinnit
			 */
			$validator = Validator::make ( $request->all (), [
					"tiedosto" => "required|max:" . $maxFileSize,
					'entiteetti_tyyppi' => 'required|numeric', // |exists:entiteetti_tyyppi,id',
					'entiteetti_id' => 'required|numeric'
			] );
			if ($validator->fails ()) {
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
				foreach ( $validator->errors ()->all () as $error )
					MipJson::addMessage ( $error );
				MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			} else {

				// wrap the operations into a transaction
				DB::beginTransaction ();
				Utils::setDBUser ();

				try {

					/*
					 * Muiden kuin tutkimusalueen lisäämistiedostojen käsittely.
					 */
					if ($request->hasFile ( "tiedosto" )) {
						$file = $request->file ( 'tiedosto' );
						$file_extension = $file->getClientOriginalExtension ();
						$file_originalname = $file->getClientOriginalName ();
						$file_name = Str::random ( 32 ); // .".".$file_extension;
						$file_basepath = storage_path () . "/" . config ( 'app.attachment_upload_path' );
						$file_subpath = Carbon::now ()->format ( "Y/m/" );
						$file_path = $file_basepath . $file_subpath;
						$file_fullname = $file_path . $file_name . "." . $file_extension;
						$user_id = JWTAuth::toUser ( JWTAuth::getToken () )->id;

						/*
						 * Create the directory if it does not exist
						 */
						if (! File::exists ( $file_path )) {
							File::makeDirectory ( $file_path, 0775, true );
						}

						// Make sure the name is unique
						while ( File::exists ( $file_path . "/" . $file_name . "." . "$file_extension" ) ) {
							$file_name = Str::random ( 32 );
						}

						/*
						 * Move the uploaded file to its final destination
						 */
						$file->move ( $file_path, $file_name . "." . $file_extension );

						/*
						 * Create the file and store it into DB and filesystem
						 */

						$entity = new ArkTiedosto ( $request->all () );
						$entity->tiedostonimi = $file_name . "." . $file_extension;
						$entity->alkup_tiedostonimi = $file_originalname;
						$entity->polku = $file_subpath;

						$author_field = ArkTiedosto::CREATED_BY;
						$entity->$author_field = Auth::user ()->id;

						$entity->save ();

						switch ($request->get ( 'entiteetti_tyyppi' )) {

							  //19: Rontgenkuva
							  case 19:
							      $xray = Rontgenkuva::find($request->input('entiteetti_id'));
							      $xray->files()->attach($entity->id, ['luoja' => $entity->$author_field]);
							      break;
							  //14: Tutkimus
							  case 14:
							      $tutkimus = Tutkimus::find($request->input('entiteetti_id'));
							      $tutkimus->files()->attach($entity->id, ['luoja' => $entity->$author_field]);
							      break;
							  //15: Kohde
							  case 15:
							      $kohde = Kohde::find($request->input('entiteetti_id'));
							      $kohde->files()->attach($entity->id, ['luoja' => $entity->$author_field]);
							      break;
							  //17: Löytö
							  case 17:
							      $loyto = Loyto::find($request->input('entiteetti_id'));
							      $loyto->files()->attach($entity->id, ['luoja' => $entity->$author_field]);
							      break;
							  //18: Näyte
							  case 18:
							      $nayte = Nayte::find($request->input('entiteetti_id'));
							      $nayte->files()->attach($entity->id, ['luoja' => $entity->$author_field]);
							      break;
							  //20: Toimenpide
							  case 20:
							      $toimenpide = KonsToimenpiteet::find($request->input('entiteetti_id'));
							      $toimenpide->files()->attach($entity->id, ['luoja' => $entity->$author_field]);
							      break;
							  //21: Käsittely
							  case 21:
							      $kasittely = KonservointiKasittely::find($request->input('entiteetti_id'));
							      $kasittely->files()->attach($entity->id, ['luoja' => $entity->$author_field]);
							      break;
							  //13: Yksikko
							  case 13:
							      $yksikko = TutkimusalueYksikko::find($request->input('entiteetti_id'));
							      $yksikko->files()->attach($entity->id, ['luoja' => $entity->$author_field]);
							      break;
							  /* TODO: Loput tyypit, mitä ikinä ne ovatkaan
							  case 2:
							  $rakennus = Rakennus::find($request->input('entiteetti_id'));
							  $rakennus->files()->attach($entity->id);
							  break;
                              */


						}

						DB::commit ();

						MipJson::setGeoJsonFeature ();
						MipJson::addMessage ( Lang::get ( 'tiedosto.save_success' ) );
						MipJson::setGeoJsonFeature ( null, array (
								"id" => $entity->id
						) );
					}
				} catch ( Exception $e ) {
					DB::rollback ();
					MipJson::setGeoJsonFeature ();
					MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
					MipJson::addMessage ( Lang::get ( 'tiedosto.save_failed' ) );
				}
			}
		}
		return MipJson::getJson ();
	}

	private static function lisaaKoordinaatitTiedostosta(Request $request) {
	    /*
	     * Role check
	     * HUOM! Tässä lisätään tutkimusalueita, mutta entiteetti_id on silti tutkimuksen. Tämä johtuu siitä, että tutkimusalueet lisätään
	     * ko. tutkimukseen.
	     */

	    if (!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_tutkimusalue.luonti', $request->input('entiteetti_id'))) {
	        MipJson::setGeoJsonFeature ();
	        MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
	        MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
	        return MipJson::getJson ();
	    }

	    $file = $request->file ( 'tiedosto' );
	    $entityType = $request->get ( 'entiteetti_tyyppi' );
	    $tableName = '';
	    if ($entityType == 17)
	        $tableName = 'ark_loyto';
	    else if ($entityType == 18)
            $tableName = 'ark_nayte';

	    //Luetaan yksittäinen .dxf tai .csv tiedosto
        if (!is_array ( $file )) {
            $file = $request->file ( 'tiedosto' );
            $file_extension = $file->getClientOriginalExtension ();
            try {
                $json = GeomFileReader::readGeometriesFromFile ('coordinate_'. $file_extension, $file);
            } catch ( Exception $e ) {
                if ($e->getCode () == '7' || $e->getCode () == '8' || $e->getCode () == '1') {
                    MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
                    $errorMessage = $e->getMessage ();
                } else {
                    MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
                    $errorMessage = $e->getMessage();
                }

                MipJson::setGeoJsonFeature ();
                MipJson::addMessage ( $errorMessage );

                return MipJson::getJson ();
            }
            $jsonData = json_decode($json);
            $geometries = $jsonData[0]->geometry;

            DB::beginTransaction ();
            Utils::setDBUser ();
            try {
                $rowsUpdated = 0;
                $rowsNotFound = [];
                foreach($geometries as $point) {
                    $rowAdded = DB::table($tableName)
                    ->where('luettelointinumero', $point->name)
                    ->update(array('koordinaatti_e' => round($point->lat, 3), 'koordinaatti_n' => round($point->lon, 3), 'koordinaatti_z' => round($point->ele, 3)));

                    if ($rowAdded == 1)
                        $rowsUpdated++;
                    else
                        array_push($rowsNotFound, $point->name);

                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
            }
            $jsonData[0]->rowsUpdated = $rowsUpdated;
            $jsonData[0]->rowsNotFound = $rowsNotFound;
        }
        // Jos $file on array, luetaan shapefile. Arrayn koon tulee olla 3 ja sisältää shp, shx, dbf -tiedostot.
        // Ei tueta prj tiedoston lataamista.
	    else if (is_array ( $file ) && sizeof ( $file ) == 3) {
	        // Otetaan tiedostot ja niiden tiedot käsittelyyn
	        $file1 = $file [0];
	        $file2 = $file [1];
	        $file3 = $file [2];

	        // Tiedostopäätteet
	        $file1_extension = $file1->getClientOriginalExtension ();
	        $file2_extension = $file2->getClientOriginalExtension ();
	        $file3_extension = $file3->getClientOriginalExtension ();

	        // Alkuperäiset nimet
	        $file1_originalname = $file1->getClientOriginalName ();
	        $file2_originalname = $file2->getClientOriginalName ();
	        $file3_originalname = $file3->getClientOriginalName ();

	        // Annetaan tiedostoille uusi nimi.
	        // 1. tiedosto saa random nimen, kaksi muuta saavat saman nimen.
	        // Shapefilen lukukirjastolle annetaan tiedostopolku ja nimi, tämän takia nimien tulee olla kaikille sama
	        // Tiedostopäätteet ovat eri, ei tapahdu ylikirjoittamista.
	        $file1_name = Str::random ( 32 ); // .".".$file_extension;
	        $file2_name = $file1_name;
	        $file3_name = $file1_name;

	        /*
	         * Alla olevissa ei välttämättä ole kauheasti järkeä, mutta ei ole keretty tutkimaan onko niillä jotain muuta tekoa,
	         * sen takia kulkevat mukana mipin alkuperäisestä toteutuksesta otettuna.
	         */

	        // Asetetaan basepath
	        $file1_basepath = storage_path () . "/" . config ( 'app.attachment_upload_path' );
	        $file2_basepath = storage_path () . "/" . config ( 'app.attachment_upload_path' );
	        $file3_basepath = storage_path () . "/" . config ( 'app.attachment_upload_path' );

	        // Asetetaan subpath
	        $file1_subpath = Carbon::now ()->format ( "Y/m/" );
	        $file2_subpath = $file1_subpath;
	        $file3_subpath = $file1_subpath;

	        // Asetetaan path
	        $file1_path = $file1_basepath . $file1_subpath;
	        $file2_path = $file1_path;
	        $file3_path = $file1_path;

	        // Asetetaan fullname
	        $file1_fullname = $file1_path . $file1_name . "." . $file1_extension;
	        $file2_fullname = $file1_path . $file1_name . "." . $file2_extension;
	        $file3_fullname = $file1_path . $file1_name . "." . $file3_extension;

	        // KäyttäjäID
	        $user_id = JWTAuth::toUser ( JWTAuth::getToken () )->id;

	        /*
	         * Create the directory if it does not exist
	         */
	        if (! File::exists ( $file1_path )) {
	            File::makeDirectory ( $file1_path, 0775, true );
	        }

	        // Make sure the name is unique
	        // Jos tiedostonimi ei ole uniikki, annetaan sille uusi nimi. Samalla annetaan uusi nimi myös 2. ja 3. tiedostoille.
	        while ( File::exists ( $file1_path . "/" . $file1_name . "." . "$file1_extension" ) ) {
	            $file1_name = Str::random ( 32 );
	            $file2_name = $file1_name;
	            $file3_name = $file1_name;
	        }

	        /*
	         * Move the uploaded file to its final destination
	         */
	        $file1->move ( $file1_path, $file1_name . "." . $file1_extension );
	        $file2->move ( $file2_path, $file2_name . "." . $file2_extension );
	        $file3->move ( $file3_path, $file3_name . "." . $file3_extension );
	        // TODO: Liitteiden tallennus kantaan
	        try {
	            // Lopulta hoidetaan tiedoston parsiminen ja palautetaan json
	            $json = GeomFileReader::readGeometriesFromFile ( 'CoordinateSHP', $file1_path . $file1_name );
	        } catch ( Exception $e ) {
	            if ($e->getCode () == '7' || $e->getCode () == '8' || $e->getCode () == '1') {
	                MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
	                $errorMessage = $e->getMessage ();
	            } else {
	                $errorMessage = Lang::get ( 'tiedosto.unknown_error' ) . ": " . $e->getMessage ();
	                MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
	            }

	            MipJson::setGeoJsonFeature ();
	            MipJson::addMessage ( $errorMessage );

	            return MipJson::getJson ();
	        }
	        $jsonData = json_decode($json);

	        DB::beginTransaction ();
	        Utils::setDBUser ();
	        try {
	            $rowsUpdated = 0;
	            $rowsNotFound = [];
	            foreach(json_decode($json) as $point) {
	                $coordinates = $point->geometry->coordinates;
	                $rowAdded = DB::table($tableName)
	                ->where('luettelointinumero', $point->properties->Text)
	                ->update(array('koordinaatti_e' => round($coordinates[1], 3), 'koordinaatti_n' => round($coordinates[0], 3), 'koordinaatti_z' => round($coordinates[2], 3)));

	                if ($rowAdded == 1)
	                    $rowsUpdated++;
                    else
                        array_push($rowsNotFound, $point->name);
	            }
	            DB::commit();
	        } catch (\Exception $e) {
	            DB::rollback();
	        }
	        $jsonData[0]->rowsUpdated = $rowsUpdated;
	        $jsonData[0]->rowsNotFound = $rowsNotFound;
	    }
	    return json_encode($jsonData);
	}

	private static function lisaaTutkimusalueTiedostosta(Request $request) {
		/**
		 * Tutkimusalueiden parsintaa - muilta osin ei toteutettu mitään.
		 * Tämäkin on aikamoinen proto, siirretään muualle kun on visio miten tämä menee lopullisesti.
		 */
		/*
		 * Role check
		 * HUOM! Tässä lisätään tutkimusalueita, mutta entiteetti_id on silti tutkimuksen. Tämä johtuu siitä, että tutkimusalueet lisätään
		 * ko. tutkimukseen.
		 */
		if (!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_tutkimusalue.luonti', $request->input('entiteetti_id'))) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}

		$file = $request->file ( 'tiedosto' );

		// Jos $file on array, luetaan shapefile. Arrayn koon tulee olla 3 ja sisältää shp, shx, dbf -tiedostot.
		// Ei tueta prj tiedoston lataamista.
		if (is_array ( $file ) && sizeof ( $file ) == 3) {
			// Otetaan tiedostot ja niiden tiedot käsittelyyn
			$file1 = $file [0];
			$file2 = $file [1];
			$file3 = $file [2];

			// Tiedostopäätteet
			$file1_extension = $file1->getClientOriginalExtension ();
			$file2_extension = $file2->getClientOriginalExtension ();
			$file3_extension = $file3->getClientOriginalExtension ();

			// Alkuperäiset nimet
			$file1_originalname = $file1->getClientOriginalName ();
			$file2_originalname = $file2->getClientOriginalName ();
			$file3_originalname = $file3->getClientOriginalName ();

			// Annetaan tiedostoille uusi nimi.
			// 1. tiedosto saa random nimen, kaksi muuta saavat saman nimen.
			// Shapefilen lukukirjastolle annetaan tiedostopolku ja nimi, tämän takia nimien tulee olla kaikille sama
			// Tiedostopäätteet ovat eri, ei tapahdu ylikirjoittamista.
			$file1_name = Str::random ( 32 ); // .".".$file_extension;
			$file2_name = $file1_name;
			$file3_name = $file1_name;

			/*
			 * Alla olevissa ei välttämättä ole kauheasti järkeä, mutta ei ole keretty tutkimaan onko niillä jotain muuta tekoa,
			 * sen takia kulkevat mukana mipin alkuperäisestä toteutuksesta otettuna.
			 */

			// Asetetaan basepath
			$file1_basepath = storage_path () . "/" . config ( 'app.attachment_upload_path' );
			$file2_basepath = storage_path () . "/" . config ( 'app.attachment_upload_path' );
			$file3_basepath = storage_path () . "/" . config ( 'app.attachment_upload_path' );

			// Asetetaan subpath
			$file1_subpath = Carbon::now ()->format ( "Y/m/" );
			$file2_subpath = $file1_subpath;
			$file3_subpath = $file1_subpath;

			// Asetetaan path
			$file1_path = $file1_basepath . $file1_subpath;
			$file2_path = $file1_path;
			$file3_path = $file1_path;

			// Asetetaan fullname
			$file1_fullname = $file1_path . $file1_name . "." . $file1_extension;
			$file2_fullname = $file1_path . $file1_name . "." . $file2_extension;
			$file3_fullname = $file1_path . $file1_name . "." . $file3_extension;

			// KäyttäjäID
			$user_id = JWTAuth::toUser ( JWTAuth::getToken () )->id;

			/*
			 * Create the directory if it does not exist
			 */
			if (! File::exists ( $file1_path )) {
				File::makeDirectory ( $file1_path, 0775, true );
			}

			// Make sure the name is unique
			// Jos tiedostonimi ei ole uniikki, annetaan sille uusi nimi. Samalla annetaan uusi nimi myös 2. ja 3. tiedostoille.
			while ( File::exists ( $file1_path . "/" . $file1_name . "." . "$file1_extension" ) ) {
				$file1_name = Str::random ( 32 );
				$file2_name = $file1_name;
				$file3_name = $file1_name;
			}

			/*
			 * Move the uploaded file to its final destination
			 */
			$file1->move ( $file1_path, $file1_name . "." . $file1_extension );
			$file2->move ( $file2_path, $file2_name . "." . $file2_extension );
			$file3->move ( $file3_path, $file3_name . "." . $file3_extension );
			// TODO: Liitteiden tallennus kantaan
			try {
				// Lopulta hoidetaan tiedoston parsiminen ja palautetaan json
				$json = GeomFileReader::readGeometriesFromFile ( 'shp', $file1_path . $file1_name );
			} catch ( Exception $e ) {
				if ($e->getCode () == '7' || $e->getCode () == '8' || $e->getCode () == '1') {
					MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
					$errorMessage = $e->getMessage ();
				} else {
					$errorMessage = Lang::get ( 'tiedosto.unknown_error' ) . ": " . $e->getMessage ();
					MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
				}

				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( $errorMessage );

				return MipJson::getJson ();
			}
		} else {
			$file = $request->file ( 'tiedosto' );
			$file_extension = $file->getClientOriginalExtension ();
			$file_originalname = $file->getClientOriginalName ();
			$file_name = Str::random ( 32 ); // .".".$file_extension;
			$file_basepath = storage_path () . "/" . config ( 'app.attachment_upload_path' );
			$file_subpath = Carbon::now ()->format ( "Y/m/" );
			$file_path = $file_basepath . $file_subpath;
			$file_fullname = $file_path . $file_name . "." . $file_extension;
			$user_id = JWTAuth::toUser ( JWTAuth::getToken () )->id;

			/*
			 * Create the directory if it does not exist
			 */
			if (! File::exists ( $file_path )) {
				File::makeDirectory ( $file_path, 0775, true );
			}

			// Make sure the name is unique
			while ( File::exists ( $file_path . "/" . $file_name . "." . "$file_extension" ) ) {
				$file_name = Str::random ( 32 );
			}

			/*
			 * Move the uploaded file to its final destination
			 */
			$file->move ( $file_path, $file_name . "." . $file_extension );

			// TODO: Liitteen tallentaminen kantaan
			try {
				$json = GeomFileReader::readGeometriesFromFile ( $file_extension, $file_path . $file_name . "." . $file_extension );
			} catch ( Exception $e ) {
				if ($e->getCode () == '7' || $e->getCode () == '8' || $e->getCode () == '1') {
					MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
					$errorMessage = $e->getMessage ();
				} else {
					MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
				}

				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( $errorMessage );

				return MipJson::getJson ();
			}
		}
		// Ei tehdä tässä vaiheessa muuta, koska muuta ei ole toteutettu
		return $json;
	/**
	 * Tutkimusalueiden parsinta päättyy.
	 * TODO: Liitteen tallentaminen kantaan tulee toteuttaa kun kannan taulut yms on tehty.
	 */
	}
	private static function lisaaKohdeTiedostosta(Request $request) {
		// TODO: Toteutetaan kohteen sijaintien lukeminen tiedostosta myöhemmin

		/*
		 * Role check
		 * HUOM! Tässä lisätään tutkimusalueita, mutta entiteetti_id on silti tutkimuksen. Tämä johtuu siitä, että tutkimusalueet lisätään
		 * ko. tutkimukseen.
		 */
		if (!Kayttaja::hasPermission('arkeologia.ark_kohde.luonti')) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}


		return "TODO";
	}
	/**
	 * Display the specified resource.
	 *
	 * @param int $id
	 * @return \Illuminate\Http\Response
	 */
	public function show($id) {

		/*
		 * Role check
		 */
	    if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_tiedosto.katselu', $id)) {
	        MipJson::setGeoJsonFeature();
	        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
	        MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
	        return MipJson::getJson();
	    }

		if (! is_numeric ( $id )) {
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
		} else {
			try {
				$entity = ArkTiedosto::find ( $id )->with ( 'luoja' )->with ( 'muokkaaja' )->first ();

				if (! $entity) {
					MipJson::setGeoJsonFeature ();
					MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
					MipJson::addMessage ( Lang::get ( 'tiedosto.search_not_found' ) );
				} else {
					$entity->first ();
					$entity->url = config ( 'app.attachment_server' ) . "/" . config ( 'app.attachment_server_baseurl' ) . $entity->polku . $entity->tiedostonimi;
					MipJson::setGeoJsonFeature ( null, $entity );
					MipJson::addMessage ( Lang::get ( 'tiedosto.search_success' ) );
				}
			} catch ( QueryException $e ) {
				MipJson::addMessage ( Lang::get ( 'tiedosto.search_failed' ) );
				MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
			}
		}
		return MipJson::getJson ();
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param int $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id) {

		/*
		 * Role check
		 */
	    if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_tiedosto.muokkaus', $id)) {
	        MipJson::setGeoJsonFeature();
	        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
	        MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
	        return MipJson::getJson();
	    }

		$validator = Validator::make ( $request->all (), [
				'otsikko' => 'required',
				'kuvaus' => 'nullable|string'
		] );

		if ($validator->fails ()) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			foreach ( $validator->errors ()->all () as $error )
				MipJson::addMessage ( $error );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			return MipJson::getJson ();
		}
		// wrap the operations into a transaction
		DB::beginTransaction ();
		Utils::setDBUser ();
		try {
			$entity = ArkTiedosto::find ( $id );
			if (! $entity) {
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'tiedosto.search_not_found' ) );
				MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
			} else {
				$entity->fill ( $request->all () );
				$author_field = ArkTiedosto::UPDATED_BY;
				$entity->$author_field = Auth::user ()->id;
				$entity->update ();

				//Linkitetään muihin
				//Tallennetaan linkattavat löydöt ja yksikot
				ArkTiedosto::linkita_loydot($entity->id, $request->loydot);
				ArkTiedosto::linkita_naytteet($entity->id, $request->naytteet);
				ArkTiedosto::linkita_yksikot($entity->id, $request->yksikot);

				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'tiedosto.save_success' ) );
				MipJson::setGeoJsonFeature ( null, array (
						"id" => $entity->id
				) );
			}

    		DB::commit ();
	    } catch ( Exception $e ) {
 			DB::rollback ();

 			MipJson::setGeoJsonFeature ();
 			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
 			MipJson::addMessage ( Lang::get ( 'tiedosto.save_failed' ) );
 		}
		return MipJson::getJson ();
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param int $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(Request $request, $id) {
	    /*
	     * Role check
	     */
	    if(!Kayttaja::hasPermissionForEntity('arkeologia.ark_tiedosto.poisto', $id)) {
	        MipJson::setGeoJsonFeature();
	        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
	        MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
	        return MipJson::getJson();
	    }

		$validator = Validator::make ( $request->all (), [
				'entiteetti_tyyppi' => 'required|numeric',
				'entiteetti_id' => 'required|numeric'
		] );

		if ($validator->fails ()) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );

			foreach ( $validator->errors ()->all () as $error ) {
				MipJson::addMessage ( $error );
			}

			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );

			return MipJson::getJson ();
		}

		$entity = ArkTiedosto::find ( $id );

		if (! $entity) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'tiedosto.search_not_found' ) );
			MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
			return MipJson::getJson ();
		}

		try {

			DB::beginTransaction ();
			Utils::setDBUser ();

			$author_field = ArkTiedosto::DELETED_BY;
			$when_field = ArkTiedosto::DELETED_AT;
			$entity->$author_field = Auth::user ()->id;
			$entity->$when_field = \Carbon\Carbon::now ();
			$entity->save ();

			//Poistetaan linkitykset
			ArkTiedosto::linkita_loydot($entity->id, []);
			ArkTiedosto::linkita_naytteet($entity->id, []);
			ArkTiedosto::linkita_yksikot($entity->id, []);

			switch ($request->get ( 'entiteetti_tyyppi' )) {
				case 19 :
					Rontgenkuva::find ( $request->get ( 'entiteetti_id' ) )->files ()->detach ( $id );
					break;
				case 14:
				    Tutkimus::find ( $request->get ( 'entiteetti_id' ) )->files ()->detach ( $id );
				    break;
				case 15:
				    Kohde::find ( $request->get ( 'entiteetti_id' ) )->files ()->detach ( $id );
				    break;
				case 17:
				    Loyto::find ( $request->get ( 'entiteetti_id' ) )->files ()->detach ( $id );
				    break;
				case 18:
				    Nayte::find ( $request->get ( 'entiteetti_id' ) )->files ()->detach ( $id );
				    break;
				case 20:
				    KonsToimenpiteet::find ( $request->get ( 'entiteetti_id' ) )->files ()->detach ( $id );
				    break;
				case 21:
				    KonservointiKasittely::find ( $request->get ( 'entiteetti_id' ) )->files ()->detach ( $id );
				    break;
				case 13:
				    TutkimusalueYksikko::find ( $request->get ( 'entiteetti_id' ) )->files ()->detach ( $id );
				    break;
				/* TODO: Loput
				case 2 :
					Rakennus::find ( $request->get ( 'entiteetti_id' ) )->files ()->detach ( $id );
					break;
				*/
			}

			DB::commit ();

			MipJson::addMessage ( Lang::get ( 'tiedosto.delete_success' ) );
			MipJson::setGeoJsonFeature ( null, array (
					"id" => $entity->id
			) );
		} catch ( Exception $e ) {
			DB::rollback ();
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'tiedosto.delete_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
		}

		return MipJson::getJson ();
	}
}
