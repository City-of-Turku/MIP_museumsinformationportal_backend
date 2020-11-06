<?php

namespace App\Http\Controllers\Rak;

use App\Rak\Inventointijulkaisu;
use App\Rak\Inventointiprojekti;
use App\Entiteettityyppi;
use App\Rak\InventointijulkaisuKuntakyla;
use App\Http\Controllers\Controller;
use App\Kayttaja;
use App\Library\String\MipJson;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use App\Utils;
use App\Integrations\Geoserver;
use App\Rak\InventointijulkaisuMuutoshistoria;

use Exception;

class InventointijulkaisuController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {

		/*
		 * Role check: TODO
		 */

		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointijulkaisu.katselu' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}

		$validator = Validator::make ( $request->all (), [
				'rivi' => 'numeric',
				'rivit' => 'numeric',
				'jarjestys' => 'string',
				'jarjestys_suunta' => 'string',
				'nimi' => 'string',
				'tarkka' => 'numeric'
		] );

		if ($validator->fails ()) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			foreach ( $validator->errors ()->all () as $error ) {
				MipJson::addMessage ( $error );
			}
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
		} else {
			try {

				/*
				 * Initialize the variables that needs to be "reformatted"
				 */
				$rivi = (isset ( $request->rivi ) && is_numeric ( $request->rivi )) ? $request->rivi : 0;
				$riveja = (isset ( $request->rivit ) && is_numeric ( $request->rivit )) ? $request->rivit : 100;
				$jarjestys_kentta = (isset ( $request->jarjestys )) ? $request->jarjestys : "id";
				$jarjestys_suunta = (isset ( $request->jarjestys_suunta )) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";

				/*
				 * By default, initialize the query to get ALL entities
				 */
				$entities = Inventointijulkaisu::getAll ( $jarjestys_kentta, $jarjestys_suunta )->with ( array (
						'inventointiprojektit',
						'tasot',
				        'kunnatkylat.kunta',
				        'kunnatkylat.kyla'
				) );

				/*
				 * If ANY search terms are given limit results by them
				 */
				if ($request->tarkka && $request->nimi) {
					$entities->where ( 'inventointijulkaisu.nimi', 'ILIKE', "$request->nimi" );
				} else if ($request->nimi){
					$entities->withName ( $request->nimi );
				}

				//$entities->withOrder($jarjestys_kentta, $jarjestys_suunta);

				// calculate the total rows of the search results
				$total_rows = Utils::getCount($entities);

				// limit the results rows by given params
				$entities->withLimit ( $rivi, $riveja );

				// Execute the query
				// $entities = $entities->get()->toArray();
				$entities = $entities->get ();

				/*
				 * Set the results into Json object
				 */
				MipJson::initGeoJsonFeatureCollection ( count ( $entities ), $total_rows );
				foreach ( $entities as $entity ) {
					MipJson::addGeoJsonFeatureCollectionFeaturePoint ( null, $entity );
				}
				//TODO tekstit
				MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.found_count', [
						"count" => count ( $entities )
				] ) );

				if (count ( $entities ) <= 0) {
					// MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
					MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.search_not_found' ) );
				} else {
					MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.search_success' ) );
				}
			} catch ( Exception $e ) {
				MipJson::setGeoJsonFeature ();
				MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
				MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.search_failed' ) );
			}
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

		/*
		 * Role check
		 */
		//TODO:
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointijulkaisu.luonti' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}


		$validator = Validator::make ( $request->all (), [
				'nimi' => 'required|string|min:2',
				'kuvaus' => 'nullable|string',
				//'inventointiprojektit' => 'required',
				'tasot' => 'required'
		]
		 );

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
			try {
				DB::beginTransaction();
				Utils::setDBUser();

				$entity = new Inventointijulkaisu( $request->all() );
				$author_field = Inventointijulkaisu::CREATED_BY;
				$entity->$author_field = Auth::user ()->id;
				$entity->save ();

				/*
				 * Store the new inventointiprojekti values
				 */
				$ipt = $request->input( 'inventointiprojektit' );
				if(isset($ipt)) {
    				foreach ( $ipt as $ip ) {

    					$inventointiprojekti = Inventointiprojekti::find($ip['id']);

    					$entity->inventointiprojektit()->attach($inventointiprojekti, ['luoja' => Auth::user()->id]);
    				}
				}

				/*
				 * Store the new taso values
				 */
				$tasot = $request->input('tasot');
				$kentat = json_decode(stripslashes($request->input('kentat')), true);

				foreach ( $tasot as $taso) {
					$entiteetti_tyyppi = Entiteettityyppi::find($taso['id']);

					$entity->tasot()->attach($entiteetti_tyyppi->id, ['luoja' => Auth::user()->id, 'inventointijulkaisu_id' => $entity->id]);
				}

				InventointijulkaisuKuntakyla::paivita_inventointijulkaisu_kunnatkylat($entity->id, $request->input('kunnatkylat'));

				$kunnatKylat = $request->input('kunnatkylat');
				$kuntaIdt = [];
				$kylaIdt = [];
				//Kerää kaikki kuntaIdt, joissa kyla on null $kuntaIdt listaan ja kaikki kylat $kylaIdt listaan
				for($i = 0; $i < count($kunnatKylat); $i++) {
				    if(isset($kunnatKylat[$i]['kyla'])) {
				        if(isset($kunnatKylat[$i]['kyla']['id'])) {
				            array_push($kylaIdt, $kunnatKylat[$i]['kyla']['id']);
				        }
				    } else {
				        array_push($kuntaIdt, $kunnatKylat[$i]['kunta']['id']);
				    }
				}
				/*
				 * Add the layers to the geoserver
				 */
				$geoserver = new Geoserver();

				try {
    				foreach ($tasot as $taso) {
    					$tasonimi = $taso['nimi'];

    					$tasoKentat = $kentat[$tasonimi];
    					$geoserver->publishLayer($entity->nimi, $tasonimi, $ipt, $tasoKentat, $kuntaIdt, $kylaIdt);
    				}
				}
				catch(Exception $e) {
				    DB::rollback();
				    throw $e;
				}

				DB::commit();
			} catch(Exception $e) {
				DB::rollback();
				throw $e;
			}
			MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.save_success' ) );
			MipJson::setGeoJsonFeature ( null, array (
					"id" => $entity->id
			) );
		} catch ( Exception $e ) {

			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
			MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.save_failed' ) );
			MipJson::addMessage ( $e->getMessage());
		}

		return MipJson::getJson ();
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
		//TODO
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointijulkaisu.katselu' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}

		if (! is_numeric ( $id )) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
		} else {
			try {
				$entity = Inventointijulkaisu::getSingle( $id )->with(array('inventointiprojektit', 'tasot', 'luoja', 'muokkaaja', 'kunnatkylat.kunta', 'kunnatkylat.kyla'))->first();

				if (count ( (array)$entity ) <= 0) {
					MipJson::setGeoJsonFeature ();
					MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
					MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.search_not_found' ) );
				} else {

					$properties = $entity;

					MipJson::setGeoJsonFeature ( null, $properties );
					MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.search_success' ) );
				}
			} catch ( QueryException $e ) {
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.search_failed' ) );
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
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointijulkaisu.muokkaus' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}

		$validator = Validator::make ( $request->all (), [
				'nimi' => 'required|string|min:2',
				'kuvaus' => 'nullable|string',
				//'inventointiprojektit' => 'required',
				'tasot' => 'required'
				]
			);

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
			$entity = Inventointijulkaisu::find ( $id );
			if (! $entity) {
				// error, entity not found
				MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.search_not_found' ) );
				MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
				return MipJson::getJson ();
			}
 			try {
				DB::beginTransaction();
				Utils::setDBUser();

				/*
				 * Detach the existing inventointiprojekti relations
				 */

				$existingInvs = DB::table('inventointijulkaisu_inventointiprojekti')->where('inventointijulkaisu_id', $entity->id)->get();



				foreach($existingInvs as $ei) {
					DB::table('inventointijulkaisu_inventointiprojekti')->where('id', '=', $ei->id)->delete();
				}

				/*
				 * Store the new inventointiprojekti values
				 */
				$ipt = $request->input( 'inventointiprojektit' );
				if(isset($ipt)) {
    				foreach ( $ipt as $ip ) {
    					$inventointiprojekti = Inventointiprojekti::find($ip['id']);
    					$entity->inventointiprojektit()->attach($inventointiprojekti, ['luoja' => Auth::user()->id]);
    				}
				}

				InventointijulkaisuKuntakyla::paivita_inventointijulkaisu_kunnatkylat($entity->id, $request->input('kunnatkylat'));

				$kunnatKylat = $request->input('kunnatkylat');
				$kuntaIdt = [];
				$kylaIdt = [];
				//Kerää kaikki kuntaIdt, joissa kyla on null $kuntaIdt listaan ja kaikki kylat $kylaIdt listaan
				for($i = 0; $i < count($kunnatKylat); $i++) {
				    if(isset($kunnatKylat[$i]['kyla'])) {
				        if(isset($kunnatKylat[$i]['kyla']['id'])) {
				            array_push($kylaIdt, $kunnatKylat[$i]['kyla']['id']);
				        }
				    } else {
				        array_push($kuntaIdt, $kunnatKylat[$i]['kunta']['id']);
				    }
				}

				/*
				 * Detach the existing taso relations
				 */
				$existingTasot = DB::table('inventointijulkaisu_taso')->where('inventointijulkaisu_id', $entity->id)->get();

				/*
				 * Delete the published layers from the geoserver before destroying the relations
				 */
				$geoserver = new Geoserver();

				foreach($existingTasot as $et) {

					$taso = Entiteettityyppi::find($et->entiteetti_tyyppi_id);

					$geoserver->deleteLayerAndFeatureType($entity->nimi, $taso->nimi);
				}

				foreach($existingTasot as $et) {
					//$entity->tasot()->detach($et->id, ['poistettu' => \Carbon\Carbon::now(), 'poistaja' => Auth::user()->id]);
					DB::table('inventointijulkaisu_taso')->where('id', '=', $et->id)->delete();
				}

				/*
				 * Store the new taso values
				 */
				$tasot = $request->input( 'tasot' );
				$kentat = json_decode(stripslashes($request->input('kentat')), true);

				foreach ( $tasot as $taso) {
					$entiteetti_tyyppi = Entiteettityyppi::find($taso['id']);
					$entity->tasot()->attach($entiteetti_tyyppi->id, ['luoja' => Auth::user()->id, 'inventointijulkaisu_id' => $entity->id]);
				}

				//Update the inventointijulkaisu values.
				$entity->fill ( $request->all() );

				$author_field = Inventointiprojekti::UPDATED_BY;
				$entity->$author_field = Auth::user ()->id;
				$entity->update ();

				$kuntaId = $request->input('kunta_id');
				$kylaId = $request->input('kyla_id');

				/*
				 * And finally add the layers again to the geoserver using the updated name.
				 */
				try {
				    foreach ($tasot as $taso) {
    					$tasonimi = $taso['nimi'];
    					$tasoKentat = $kentat[$tasonimi];
    					$geoserver->publishLayer($entity->nimi, $tasonimi, $ipt, $tasoKentat, $kuntaIdt, $kylaIdt);
				    }
				} catch (Exception $e) {
				    DB::rollback();
				    throw $e;
				}

				DB::commit();
 			} catch (Exception $e) {
 				DB::rollback();
 				throw $e;
 			}
			MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.save_success' ) );
			MipJson::setGeoJsonFeature ( null, array (
					"id" => $entity->id
			) );
			MipJson::setResponseStatus ( Response::HTTP_OK );

 		} catch ( Exception $e ) {
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
 			MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.save_failed' ) );
 			MipJson::addMessage ( $e->getMessage());
 		}

		return MipJson::getJson ();
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param int $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id) {

		/*
		 * Role check
		 */
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointijulkaisu.poisto' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}

		$entity = Inventointijulkaisu::find ( $id );
		if ($entity) {
			// return: not found
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.search_not_found' ) );
			MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
			return MipJson::getJson ();
		}
		try {
			DB::beginTransaction();
			Utils::setDBUser();

			$author_field = Inventointijulkaisu::DELETED_BY;
			$when_field = Inventointijulkaisu::DELETED_AT;
			$entity->$author_field = Auth::user ()->id;
			$entity->$when_field = \Carbon\Carbon::now();

			$entity->save();

			/*
			 * Detach the existing inventointiprojekti relations
			 */
			$existingInvs = DB::table('inventointijulkaisu_inventointiprojekti')->where('inventointijulkaisu_id', $entity->id)->get();

			foreach($existingInvs as $ei) {
				DB::table('inventointijulkaisu_inventointiprojekti')->where('id', '=', $ei->id)->delete();
			}


			//Get the related tasot
			$existingTasot = DB::table('inventointijulkaisu_taso')->where('inventointijulkaisu_id', $entity->id)->get();

			/*
			 * Delete the published layers from the geoserver before destroying the relations
			 */

			$geoserver = new Geoserver();

			foreach($existingTasot as $et) {

				$taso = Entiteettityyppi::find($et->entiteetti_tyyppi_id);

				$geoserver->deleteLayerAndFeatureType($entity->nimi, $taso->nimi);
			}

			/*
			 * Detach the existing taso relations
			 */
			foreach($existingTasot as $et) {
				DB::table('inventointijulkaisu_taso')->where('id', '=', $et->id)->delete();
			}

			DB::commit();

			MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.delete_success' ) );
			MipJson::setGeoJsonFeature ( null, array ("id" => $entity->id ) );

		}  catch(Exception $e) {
			DB::rollback();

			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'inventointijulkaisu.delete_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
			MipJson::addMessage ( $e->getMessage());
		}

		return MipJson::getJson ();
	}

	public function historia($id) {

		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.inventointijulkaisu.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		if(!is_numeric($id)) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('validation.custom.user_input_validation_failed'));
			MipJson::setResponseStatus(Response::HTTP_BAD_REQUEST);
			return MipJson::getJson();
		}

		return InventointijulkaisuMuutoshistoria::getById($id);

	}
}
