<?php

namespace App\Http\Controllers\Rak;

use App\Rak\Alue;
use App\Rak\Inventointiprojekti;
use App\Rak\InventointiprojektiInventoija;
use App\Rak\InventointiprojektiKiinteisto;
use App\Rak\InventointiprojektiKunta;
use App\Rak\InventointiprojektiAjanjakso;
use App\Rak\InventointiprojektiAlue;
use App\Rak\InventointiprojektiArvoalue;
use App\Rak\Arvoalue;
use App\Rak\Kiinteisto;
use App\Http\Controllers\Controller;
use App\Kayttaja;
use App\Kyla;
use App\Library\String\MipJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use App\Utils;
use App\Library\Gis\MipGis;
use App\Rak\InventointiprojektiMuutoshistoria;
use Illuminate\Support\Facades\App;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

class InventointiprojektiController extends Controller {
    use AuthenticatesUsers;
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
		
		/*
		 * Role check
		 */
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.katselu' )) {
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
			foreach ( $validator->errors ()->all () as $error )
				MipJson::addMessage ( $error );
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
				$entities = Inventointiprojekti::getAll ( $jarjestys_kentta, $jarjestys_suunta )->with ( array (
						'inventointiprojektityyppi',
						'inventointiprojektilaji',
						'ajanjakso' => function ($query) use ($jarjestys_kentta, $jarjestys_suunta) { 
							if($jarjestys_kentta == 'inventointiaika') {
								$query->orderBy('alkupvm', $jarjestys_suunta);
								$query->orderBy('loppupvm', $jarjestys_suunta);
							}
						},
						'inventoijat',
						'luoja'
				) );
				
				/*
				 * If ANY search terms are given limit results by them
				 */
				if ($request->tarkka && $request->nimi) {
					$entities->where ( 'inventointiprojekti.nimi', 'ILIKE', $request->nimi);
				} else if ($request->nimi){
					$entities->withName ( $request->nimi );
				}
				
				if ($request->tekninen_projekti && $request->tekninen_projekti=='false') {
					$entities->withExcludeTechProjects();
				}
				
				/*
				 * If aloitusika has been defined, limit the results using it
				 */
				if($request->inventointiaika_aloitus && $request->inventointiaika_aloitus != 'null') {
					$entities->withInventointiAloitusaika($request->inventointiaika_aloitus);	
				}
				
				/*
				 * If lopetusaika has been defined, limit the results using it
				 */				
				if($request->inventointiaika_lopetus && $request->inventointiaika_lopetus != 'null') {
					$entities->withInventointiLopetusaika($request->inventointiaika_lopetus);
				}
				
				/*
				 * If toimeksiantaja has been defined, limit the results using it
				 */
				if($request->toimeksiantaja) {
					$entities->withToimeksiantaja($request->toimeksiantaja);
				}
						
				/*
				 * If inventointiprojekti tyyppi has been defined, limit the results using it
				 */
				if($request->inventointiprojektityyppi) {
				    $entities->withTyyppiId($request->inventointiprojektityyppi);					
				}			

				/*
				 * If inventointiprojekti laji has been defined, limit the results using it
				 */
				if($request->inventointiprojektilaji) {
					$entities->withLajiId($request->inventointiprojektilaji);
				}
				
				if($request->inventointiprojektiId) {
					$entities->withId($request->inventointiprojektiId);
				}
				
				if($request->luoja) {
					$entities->withLuoja($request->luoja);
				}
				
				$entities->withOrder($jarjestys_kentta, $jarjestys_suunta);
				
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
				
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.found_count', [ 
						"count" => count ( $entities ) 
				] ) );
				
				if (count ( $entities ) <= 0) {
					// MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
					MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
				} else {
					MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_success' ) );
				}
			} catch ( Exception $e ) {
				MipJson::setGeoJsonFeature ();
				MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
				MipJson::addMessage ( Lang::get ( 'alue.search_failed' ) );
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
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.luonti' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
		
		$validator = Validator::make ( $request->all (), [ 
				'nimi' => 'required|string|min:2',
				'kuvaus' => 'string',
				// 'inventointiaika' => 'required|string|min:4',
				'toimeksiantaja' => 'required|string',
				'tyyppi_id' => 'required|numeric',
				'laji_id' => 'required|numeric',
				'ajanjakso' => 'required',
				'kunnat' => 'required' 
		]
		// 'inventointitunnus' => 'required|string'
		 );
		
		if ($validator->fails ()) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			foreach ( $validator->errors ()->all () as $error )
				MipJson::addMessage ( $error );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
		} else {
			try {
				try {
					DB::beginTransaction();
					Utils::setDBUser();
				
					$entity = new Inventointiprojekti ( $request->all () );
					$author_field = Inventointiprojekti::CREATED_BY;
					$entity->$author_field = Auth::user ()->id;
					$entity->save ();
					
					/*
					 * Store the new ajanjakso values
					 */
					$ajanjaksoArray = $request->get ( 'ajanjakso' );
					foreach ( $ajanjaksoArray as $ajanjakso ) {
						
						$projektiAjanjakso = new InventointiprojektiAjanjakso ( $ajanjakso );
						
						$projektiAjanjakso->inventointiprojekti_id = $entity->id;
						$projektiAjanjakso->luoja = Auth::user ()->id;
						$projektiAjanjakso->save ();
					}						
	
					/*
					 * Store the inventoijat
					 */
					$inventoijat = $request->input('inventoijat');
					foreach($inventoijat as $inventoija) {					
						$projektiInventoija = new InventointiprojektiInventoija($inventoija);
						$projektiInventoija->inventointiprojekti_id = $entity->id;				
						$projektiInventoija->save();
					}					
					
					/*
					 * store municipalities
					 */
					$kunnat = $request->get ( 'kunnat' );
					foreach ( $kunnat as $kunta ) {
						$projektiKunta = new InventointiprojektiKunta ( $kunta );
						$projektiKunta->inventointiprojekti_id = $entity->id;
						$projektiKunta->kunta_id = $kunta ['id'];
						$projektiKunta->save ();
					}
					
					DB::commit();
				} catch(Exception $e) {
					DB::rollback();
					throw $e;
				}
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.save_success' ) );
				MipJson::setGeoJsonFeature ( null, array (
						"id" => $entity->id 
				) );
			} catch ( Exception $e ) {
				MipJson::setGeoJsonFeature ();
				MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.save_failed' ) );
			}
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
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.katselu' )) {
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
				$entity = Inventointiprojekti::getSingle( $id )
					->with(array('inventointiprojektityyppi', 'inventointiprojektilaji', 'kunnat', 'ajanjakso', 'luoja', 'muokkaaja'))
					->first();
				
					
					/*
					 * TODO: With inventoijat 
					 */
					
				//$inventoijat = ($request->inventoija_id) ? $project->inventoijat()->where ( 'inventoija_id', '=', $request->inventoija_id )->get () : $project->inventoijat()->get ();	
				
				if (!$entity) {
					MipJson::setGeoJsonFeature ();
					MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
					MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
				} else {
					
					$properties = $entity;
					
					MipJson::setGeoJsonFeature ( null, $properties );
					MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_success' ) );
				}
			} catch ( QueryException $e ) {
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_failed' ) );
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
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.muokkaus' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
		
		$validator = Validator::make ( $request->all (), [ 
				'nimi' => 'required|string|min:2',
				'kuvaus' => 'nullable|string',
				// 'inventointiaika' => 'required|string|min:4',
				'toimeksiantaja' => 'required|string',
				'tyyppi_id' => 'required|numeric',
				'laji_id' => 'required|numeric',
				'ajanjakso' => 'required',
				'kunnat' => 'required' 
		]
		// 'inventointitunnus' => 'required|string'
		 );
		
		if ($validator->fails ()) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			foreach ( $validator->errors ()->all () as $error )
				MipJson::addMessage ( $error );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
		} else {
			try {
				$entity = Inventointiprojekti::find ( $id );
				if (! $entity) {
					// error, entity not found
					MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
					MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
				} else {
					try {
						DB::beginTransaction();
						Utils::setDBUser();
					
						$entity->fill ( $request->all () );
						
						/*
						 * Delete all of the existing ajanjakso values
						 */
						$existingAjanjakso = InventointiprojektiAjanjakso::where ( 'inventointiprojekti_id', $entity->id )->get ();
						foreach ( $existingAjanjakso as $ajanjakso ) {
							$ajanjakso->delete ();
						}
						
						/*
						 * Store the new ajanjakso values
						 */
						$ajanjaksoArray = $request->get ( 'ajanjakso' );
						foreach ( $ajanjaksoArray as $ajanjakso ) {
							
							$projektiAjanjakso = new InventointiprojektiAjanjakso ( $ajanjakso );
							
							$projektiAjanjakso->inventointiprojekti_id = $entity->id;
							$projektiAjanjakso->luoja = Auth::user ()->id;
							$projektiAjanjakso->save ();
						}
											
						//Delete all inventoijat
						$existingInventoijat = InventointiprojektiInventoija::where('inventointiprojekti_id', $entity->id)->get();
						foreach($existingInventoijat as $existingInventoija) {
							$existingInventoija->delete();
						}
						
						//And store the new values.
						$inventoijat = $request->input('inventoijat');
						foreach($inventoijat as $inventoija) {
							$projektiInventoija = new InventointiprojektiInventoija($inventoija);						
							$projektiInventoija->inventointiprojekti_id = $entity->id;
							
							$projektiInventoija->save();						
						}
												
						/*
						 * update municipalities
						 */
						DB::statement ( "DELETE FROM inventointiprojekti_kunta WHERE inventointiprojekti_id = ?", [ 
								$entity->id 
						] );
						$kunnat = $request->get ( 'kunnat' );
						foreach ( $kunnat as $kunta ) {
							$projektiKunta = new InventointiprojektiKunta();
							$projektiKunta->inventointiprojekti_id = $entity->id;
							$projektiKunta->kunta_id = $kunta ['id'];
							$projektiKunta->save ();
							// DB::statement("INSERT INTO inventointiprojekti_kunta (kunta_id, inventointiprojekti_id) VALUES (?,?)", [$kunta, $entity->id]);
						}
						
						$author_field = Inventointiprojekti::UPDATED_BY;
						$entity->$author_field = Auth::user ()->id;
						$entity->update ();
						
						DB::commit();
					} catch (Exception $e) {
						DB::rollback();
						throw $e;
					}
					MipJson::addMessage ( Lang::get ( 'inventointiprojekti.save_success' ) );
					MipJson::setGeoJsonFeature ( null, array (
							"id" => $entity->id 
					) );
					MipJson::setResponseStatus ( Response::HTTP_OK );
				}
			} catch ( Exception $e ) {
				MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.save_failed' ) );
			}
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
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.poisto' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
		
		$entity = Inventointiprojekti::find ( $id );
		if (!$entity) {
			// return: not found
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
			MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
			return MipJson::getJson ();
		}
		try {
			DB::beginTransaction();
			Utils::setDBUser();
			
			$author_field = Inventointiprojekti::DELETED_BY;
			$when_field = Inventointiprojekti::DELETED_AT;
			$entity->$author_field = Auth::user ()->id;
			$entity->$when_field = \Carbon\Carbon::now();
			
			$entity->save();
			
			DB::commit();
			
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.delete_success' ) );
			MipJson::setGeoJsonFeature ( null, array ("id" => $entity->id ) );
			
		}  catch(Exception $e) {
			DB::rollback();
			
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.delete_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
		}

		return MipJson::getJson ();
	}
	
	/**
	 * List the inventorers linked to given inventoring project
	 *
	 * @param int $id        	
	 * @return Mixed <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @version 1.0
	 * @since 1.0
	 */
	public function listInventorers(Request $request, $id) {
		
		/*
		 * Role check
		 */
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.katselu' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
		
		if (! is_numeric ( $id )) {
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
		} else {
			try {
				$project = Inventointiprojekti::find ( $id );
				
				if ($project) {
					$entities = ($request->inventoija_id) ? $project->inventoijat()->where ( 'inventoija_id', '=', $request->inventoija_id )->get () : $project->inventoijat()->get ();
					
					// calculate the total rows of the search results
					$total_rows = count ( $entities );
					if (count ( $entities ) > 0) {
						MipJson::initGeoJsonFeatureCollection ( count ( $entities ), $total_rows );
						MipJson::addMessage ( Lang::get ( 'inventoija.search_success' ) );
						foreach ( $entities as $entity ) {
							MipJson::addGeoJsonFeatureCollectionFeaturePoint ( null, $entity );
						}
					} else if ($entities->count () <= 0) {
						MipJson::setGeoJsonFeature ();
						// MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
						MipJson::addMessage ( Lang::get ( 'inventoija.search_not_found' ) );
					}
				} else {
					MipJson::setGeoJsonFeature ();
					MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
					MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
				}
			} catch ( QueryException $e ) {
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_failed' ) );
				MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
			}
		}
		return MipJson::getJson ();
	}
	
	public function listEntityInventorers(Request $request, $id) {
	
		/*
		 * Role check
		 */
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.katselu' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
	
		if (! is_numeric ( $id )) {
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			return MipJson::getJson ();
		}
		
		try {
			$project = Inventointiprojekti::find ( $id );

			if (!$project) {
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
				MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
				return MipJson::getJson ();
			}
			
			$entities = Kayttaja::withInventointiprojektiID($id)->get();
				
			// calculate the total rows of the search results
			$total_rows = count( $entities );
			
			if ($total_rows > 0) {
				MipJson::initGeoJsonFeatureCollection( count($entities), $total_rows );
				MipJson::addMessage ( Lang::get( 'inventoija.search_success' ) );
				foreach ( $entities as $entity ) {
					MipJson::addGeoJsonFeatureCollectionFeaturePoint( null, $entity );
				}
			} else {
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage( Lang::get( 'inventoija.search_not_found' ) );
			}

		} catch ( QueryException $e ) {
			MipJson::addMessage( Lang::get( 'inventointiprojekti.search_failed' ) );
			MipJson::setResponseStatus( Response::HTTP_INTERNAL_SERVER_ERROR );
		}
		
		return MipJson::getJson();
	}
	
	/**
	 * List the areas linked to given inventoring project
	 *
	 * @param int $id        	
	 * @return Mixed  <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @version 1.0
	 * @since 1.0
	 */
	public function listAreas(Request $request, $id) {
		
		/*
		 * Role check
		 */
		if (!Kayttaja::hasPermission( 'rakennusinventointi.inventointiprojekti.katselu' )) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus( Response::HTTP_FORBIDDEN );
			MipJson::addMessage( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson();
		}
		
		if (!is_numeric( $id )) {
			MipJson::addMessage( Lang::get( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus( Response::HTTP_BAD_REQUEST );
		} else {
			try {
				
				$validator = Validator::make( $request->all(), [ 
						'rivi' => 'numeric',
						'rivit' => 'numeric',
						'jarjestys' => 'string',
						'jarjestys_suunta' => 'string',
						'inventoija_id' => 'numeric|exists:kayttaja,id'
				] );
				
				if ($validator->fails()) {
					MipJson::setGeoJsonFeature();
					MipJson::addMessage( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
					foreach ( $validator->errors()->all() as $error ) {
						MipJson::addMessage( $error );
					}
					MipJson::setResponseStatus( Response::HTTP_BAD_REQUEST );
				} else {
					
					$rivi = (isset ( $request->rivi ) && is_numeric ( $request->rivi )) ? $request->rivi : 0;
					$riveja = (isset ( $request->rivit ) && is_numeric ( $request->rivit )) ? $request->rivit : 100;
					$jarjestys_kentta = (isset ( $request->jarjestys )) ? $request->jarjestys : "alue_id";
					$jarjestys_suunta = (isset ( $request->jarjestys_suunta )) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";
					
					$order_table = "inventointiprojekti_alue";
					$order_field = $jarjestys_kentta;
					
					// check that project exists
					$project = Inventointiprojekti::find( $id );
					
					if ($project) {
					    $entities = Alue::getAll();
					    $entities->withOrderBy(null, $jarjestys_kentta, $jarjestys_suunta);
					    
						$entities->withInventointiprojektiOrInventoija($id, $request->inventoija_id)
							->with(array( 
									'kylat', 
									'kylat.kunta'
							));
						
						// Kuntien suodatuksen parametrit
						$suodata_kunnat = $request->suodata_kunnat;
						
						if(strlen($suodata_kunnat) > 0){
						    // Kylien suodatus valittujen mukaan, muodostetaan pilkulla eroteltu array
						    $kuntaArray = explode(',', $suodata_kunnat);
						    $entities->withMunicipalityIds($kuntaArray);
						}
						
						// Kylien suodatuksen parametrit
						$suodata_kylat = $request->suodata_kylat;
						
						if(strlen($suodata_kylat) > 0){
						    // Kylien suodatus valittujen mukaan
						    $kylaArray = explode(',', $suodata_kylat);
						    $entities->withVillageIds($kylaArray);
						}
												
						$total_rows = Utils::getCount($entities);
						$entities = $entities->with('inventointiprojektit')->skip( $rivi )->take( $riveja )->get();						
						
						if ($total_rows > 0) {
							MipJson::initGeoJsonFeatureCollection( count( $entities ), $total_rows );
							MipJson::addMessage ( Lang::get( 'alue.search_success' ) );
							foreach( $entities as $entity ) {	
								/*
								 * Poistetaan "turhat" inventointiprojektit (eli ne joita ei tässä näkymässä tarvita,
								 * eli siis ne joiden ID on eri kuin tämän inventointiprojektin ID ja tämän
								 * jälkeen haetaan jokaiselle inventointiprojektille inventoijat (ainoastaan viimeinen tallennus per inventoija).
								 */
								for($i = sizeof($entity->inventointiprojektit) - 1; $i >= 0; $i--) {
									$ip = $entity->inventointiprojektit[$i];
									if($ip->id != $id) {
										unset($entity->inventointiprojektit[$i]);
									} else {
										$ip->inventoijat = Alue::inventoijat($entity->id, $ip->id);
									}
								}								
								
								$properties = clone($entity);
								unset($properties->sijainti, $properties->alue);
																
								if (!is_null( $entity->sijainti )) {
									MipJson::addGeoJsonFeatureCollectionFeaturePoint( json_decode( $entity->sijainti ), $properties );
								} else if (!is_null( $entity->alue )) {
									MipJson::addGeoJsonFeatureCollectionFeaturePoint( json_decode( $entity->alue ), $properties );
								} else {
									MipJson::addGeoJsonFeatureCollectionFeaturePoint( null, $properties );
								}
							}
						}
						if (count( $entities ) <= 0) {
							MipJson::setGeoJsonFeature ();
							MipJson::addMessage ( Lang::get ( 'alue.search_not_found' ) );
						}
					} else {
						MipJson::setGeoJsonFeature ();
						MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
						MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
					}
				}
			} catch ( QueryException $e ) {
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_failed' ) );
				MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
			}
		}
		return MipJson::getJson ();
	}
	
	/**
	 * List the valueareas liked to given inventoring project
	 *
	 * @param int $id        	
	 * @return Mixed <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @version 1.0
	 * @since 1.0
	 */
	public function listValueAreas(Request $request, $id) {
		
		/*
		 * Role check
		 */
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.katselu' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
		
		if (! is_numeric ( $id )) {
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			return MipJson::getJson ();
		}
		try {
			
			$validator = Validator::make ( $request->all (), [ 
					'rivi' => 'numeric',
					'rivit' => 'numeric',
					'jarjestys' => 'string',
					'jarjestys_suunta' => 'string',
					'inventoija_id' => 'numeric|exists:kayttaja,id' 
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
				
			$rivi = (isset ( $request->rivi ) && is_numeric ( $request->rivi )) ? $request->rivi : 0;
			$riveja = (isset ( $request->rivit ) && is_numeric ( $request->rivit )) ? $request->rivit : 100;
			$jarjestys_kentta = (isset ( $request->jarjestys )) ? $request->jarjestys : "arvoalue_id";
			$jarjestys_suunta = (isset ( $request->jarjestys_suunta )) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";
			
			$order_table = "inventointiprojekti_arvoalue";
			$order_field = $jarjestys_kentta;
			
			$project = Inventointiprojekti::find( $id );
			
			if (!$project) {
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
				MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
				return MipJson::getJson ();
			}
				
			$entities = Arvoalue::getAllForInv();
			$entities->withInventointiprojektiOrInventoija($id, $request->inventoija_id)->distinct();
			$entities->with(array(
					'_alue',
					'aluetyyppi',
					'kylat',
					'kylat.kunta'
			));
			$entities->withOrderBy(null, $jarjestys_kentta, $jarjestys_suunta);
			
			// Kuntien suodatuksen parametrit
			$suodata_kunnat = $request->suodata_kunnat;
			
			if(strlen($suodata_kunnat) > 0){
			    // Kuntien suodatus valittujen mukaan, muodostetaan pilkulla eroteltu array
			    $kuntaArray = explode(',', $suodata_kunnat);
			    $entities->withMunicipalityIds($kuntaArray);
			}
			
			// Kylien suodatuksen parametrit
			$suodata_kylat = $request->suodata_kylat;
			
			if(strlen($suodata_kylat) > 0){
			    // Kylien suodatus valittujen mukaan
			    $kylaArray = explode(',', $suodata_kylat);
			    $entities->withVillageIds($kylaArray);
			}
		
			$total_rows = Utils::getCount($entities);
			$entities = $entities->with('inventointiprojektit')->skip ( $rivi )->take ( $riveja )->distinct()->get();						
			
			if ($total_rows > 0) {
				MipJson::initGeoJsonFeatureCollection ( count ( $entities ), $total_rows );
				MipJson::addMessage ( Lang::get ( 'arvoalue.search_success' ) );
				foreach ( $entities as $entity ) {
					/*
					 * Poistetaan "turhat" inventointiprojektit (eli ne joita ei tässä näkymässä tarvita,
					 * eli siis ne joiden ID on eri kuin tämän inventointiprojektin ID ja tämän
					 * jälkeen haetaan jokaiselle inventointiprojektille inventoijat (ainoastaan viimeinen tallennus per inventoija).
					 */
					for($i = sizeof($entity->inventointiprojektit) - 1; $i >= 0; $i--) {
						$ip = $entity->inventointiprojektit[$i];
						if($ip->id != $id) {
							unset($entity->inventointiprojektit[$i]);
						} else {
							$ip->inventoijat = Arvoalue::inventoijat($entity->id, $ip->id);
						}
					}
					
					$properties = clone($entity);
					unset($properties->sijainti, $properties->aluerajaus);
					
					if (!is_null( $entity->sijainti )) {
						MipJson::addGeoJsonFeatureCollectionFeaturePoint( json_decode( $entity->sijainti ), $properties );
					} else if (! is_null ( $entity->alue )) {
						MipJson::addGeoJsonFeatureCollectionFeaturePoint( json_decode( $entity->alue ), $properties );
					} else {
						MipJson::addGeoJsonFeatureCollectionFeaturePoint( null, $properties );
					}
				}
			}
			if (count ( $entities ) <= 0) {
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'arvoalue.search_not_found' ) );
			}
			
		} catch ( QueryException $e ) {
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
		}
		
		return MipJson::getJson ();
	}
	
	/**
	 * list the villages of the given inventoringproject
	 *
	 * @param Request $request        	
	 * @param int $id        	
	 * @return Mixed <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @version 1.0
	 * @since 1.0
	 */
	public function listVillages(Request $request, $id) {
		
		/*
		 * Role check
		 */
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.katselu' )) {
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
				$validator = Validator::make ( $request->all (), [ 
						'rivi' => 'numeric',
						'rivit' => 'numeric',
						'jarjestys' => 'string',
						'jarjestys_suunta' => 'string',
						'inventoija_id' => 'numeric|exists:kayttaja,id' 
				] );
				
				if ($validator->fails ()) {
					MipJson::setGeoJsonFeature ();
					MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
					foreach ( $validator->errors ()->all () as $error )
						MipJson::addMessage ( $error );
					MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
				} else {
					
					/*
					 * Initialize the variables that needs to be "reformatted"
					 */
					$rivi = (isset ( $request->rivi ) && is_numeric ( $request->rivi )) ? $request->rivi : 0;
					$riveja = (isset ( $request->rivit ) && is_numeric ( $request->rivit )) ? $request->rivit : 100;
					$jarjestys_kentta = (isset ( $request->jarjestys )) ? $request->jarjestys : "id";
					$jarjestys_suunta = (isset ( $request->jarjestys_suunta )) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";
					
					// Kuntien suodatuksen parametrit
					$suodata_kunnat = $request->suodata_kunnat;
					// Kylien suodatuksen parametrit
					$suodata_kylat = $request->suodata_kylat;
					
					$order_table = "inventointiprojekti";
					$order_field = $jarjestys_kentta;
					
					// Kunta-järjestys valittu
					if ($jarjestys_kentta == "kunta") {
					    $jarjestys_kentta = "kunta_id";
					}
					
					$project = Inventointiprojekti::find ( $id );
					
					if ($project) {
					    
						/*
						 * Alla oleva on aikamoinen häkkyrä, mutta menkööt. Kunta joinitaan mukaan vielä myöhemmin. Tulisi varmaan siirtää controlleriin.
						 */
						if($request->inventoija_id) {
						    // Inventoija valittu
						    // Kylien määrä lasketaan ensin
						    $total_rows = DB::table('kyla')
						    ->selectRaw('count(*) as kyla_rows')
						    ->whereRaw('
									 kyla.id in (
									    select kyla.id as kid
									    from kyla
									    join arvoalue_kyla on arvoalue_kyla.kyla_id = kyla.id
									    left join arvoalue on arvoalue.id = arvoalue_kyla.arvoalue_id
									    left join inventointiprojekti_arvoalue on inventointiprojekti_arvoalue.arvoalue_id = arvoalue.id
									    where inventointiprojekti_arvoalue.inventointiprojekti_id = :inventointiprojekti_id
										and inventointiprojekti_arvoalue.inventoija_id = :inventoija_id
                                        and inventointiprojekti_arvoalue.poistettu is null
                                        and arvoalue.poistettu is null
									    group by kyla.id, inventointiprojekti_arvoalue.inventointiprojekti_id
									) or kyla.id in (
									    select kyla.id as kid
									    from kyla
									    join alue_kyla on alue_kyla.kyla_id = kyla.id
									    left join alue on alue.id = alue_kyla.alue_id
									    left join inventointiprojekti_alue on inventointiprojekti_alue.alue_id = alue.id
									    where inventointiprojekti_alue.inventointiprojekti_id = :inventointiprojekti_id
                                        and inventointiprojekti_alue.poistettu is null
                                        and alue.poistettu is null
										and inventointiprojekti_alue.inventoija_id = :inventoija_id
									    group by kyla.id, inventointiprojekti_alue.inventointiprojekti_id
									) or kyla.id in (
									    select kyla.id as kid
									    from kyla
									    join kiinteisto on kiinteisto.kyla_id = kyla.id
									    left join inventointiprojekti_kiinteisto on inventointiprojekti_kiinteisto.kiinteisto_id = kiinteisto.id
									    where inventointiprojekti_kiinteisto.inventointiprojekti_id = :inventointiprojekti_id
                                        and inventointiprojekti_kiinteisto.poistettu is null
                                        and kiinteisto.poistettu is null
										and inventointiprojekti_kiinteisto.inventoija_id = :inventoija_id
									    group by kyla.id, inventointiprojekti_kiinteisto.inventointiprojekti_id
									)', ['inventointiprojekti_id' => $id, 'inventoija_id' => $request->inventoija_id])
									->count();
						    
						    // Tietojen haku
							$villages = Kyla::on()->hydrate(DB::table('kyla')
									->whereRaw('
									 kyla.id in (
									    select kyla.id as kid
									    from kyla
									    join arvoalue_kyla on arvoalue_kyla.kyla_id = kyla.id
									    left join arvoalue on arvoalue.id = arvoalue_kyla.arvoalue_id
									    left join inventointiprojekti_arvoalue on inventointiprojekti_arvoalue.arvoalue_id = arvoalue.id
									    where inventointiprojekti_arvoalue.inventointiprojekti_id = :inventointiprojekti_id
										and inventointiprojekti_arvoalue.inventoija_id = :inventoija_id
                                        and inventointiprojekti_arvoalue.poistettu is null
                                        and arvoalue.poistettu is null
									    group by kyla.id, inventointiprojekti_arvoalue.inventointiprojekti_id
									) or kyla.id in (
									    select kyla.id as kid
									    from kyla
									    join alue_kyla on alue_kyla.kyla_id = kyla.id
									    left join alue on alue.id = alue_kyla.alue_id
									    left join inventointiprojekti_alue on inventointiprojekti_alue.alue_id = alue.id
									    where inventointiprojekti_alue.inventointiprojekti_id = :inventointiprojekti_id                                        
                                        and inventointiprojekti_alue.poistettu is null
                                        and alue.poistettu is null
										and inventointiprojekti_alue.inventoija_id = :inventoija_id
									    group by kyla.id, inventointiprojekti_alue.inventointiprojekti_id
									) or kyla.id in (
									    select kyla.id as kid
									    from kyla
									    join kiinteisto on kiinteisto.kyla_id = kyla.id
									    left join inventointiprojekti_kiinteisto on inventointiprojekti_kiinteisto.kiinteisto_id = kiinteisto.id
									    where inventointiprojekti_kiinteisto.inventointiprojekti_id = :inventointiprojekti_id                                        
                                        and inventointiprojekti_kiinteisto.poistettu is null
                                        and kiinteisto.poistettu is null
										and inventointiprojekti_kiinteisto.inventoija_id = :inventoija_id
									    group by kyla.id, inventointiprojekti_kiinteisto.inventointiprojekti_id
									)', ['inventointiprojekti_id' => $id, 'inventoija_id' => $request->inventoija_id])->skip($rivi)->take($riveja)->orderBy($jarjestys_kentta, $jarjestys_suunta)->get()->toArray());							
						} else {
						    // Kylien määrä lasketaan ensin
						    $total_rows = DB::table('kyla')
						    ->selectRaw('count(*) as kyla_rows')
						    ->whereRaw('
									 kyla.id in (
									    select kyla.id as kid
									    from kyla
									    join arvoalue_kyla on arvoalue_kyla.kyla_id = kyla.id
									    left join arvoalue on arvoalue.id = arvoalue_kyla.arvoalue_id
									    left join inventointiprojekti_arvoalue on inventointiprojekti_arvoalue.arvoalue_id = arvoalue.id
									    where inventointiprojekti_arvoalue.inventointiprojekti_id = :id
                                        and inventointiprojekti_arvoalue.poistettu is null
                                        and arvoalue.poistettu is null
									    group by kyla.id, inventointiprojekti_arvoalue.inventointiprojekti_id
									) or kyla.id in (
									    select kyla.id as kid
									    from kyla
									    join alue_kyla on alue_kyla.kyla_id = kyla.id
									    left join alue on alue.id = alue_kyla.alue_id
									    left join inventointiprojekti_alue on inventointiprojekti_alue.alue_id = alue.id
									    where inventointiprojekti_alue.inventointiprojekti_id = :id
                                        and inventointiprojekti_alue.poistettu is null
                                        and alue.poistettu is null
									    group by kyla.id, inventointiprojekti_alue.inventointiprojekti_id
									) or kyla.id in (
									    select kyla.id as kid
									    from kyla
									    join kiinteisto on kiinteisto.kyla_id = kyla.id
									    left join inventointiprojekti_kiinteisto on inventointiprojekti_kiinteisto.kiinteisto_id = kiinteisto.id
									    where inventointiprojekti_kiinteisto.inventointiprojekti_id = :id
                                        and inventointiprojekti_kiinteisto.poistettu is null
                                        and kiinteisto.poistettu is null
									    group by kyla.id, inventointiprojekti_kiinteisto.inventointiprojekti_id
									)', ['id' => $id])
									->count();
						    
						    // Tietojen haku
							$villages = Kyla::on()->hydrate(DB::table('kyla')
								->whereRaw('
									 kyla.id in (
									    select kyla.id as kid 
									    from kyla
									    join arvoalue_kyla on arvoalue_kyla.kyla_id = kyla.id
									    left join arvoalue on arvoalue.id = arvoalue_kyla.arvoalue_id
									    left join inventointiprojekti_arvoalue on inventointiprojekti_arvoalue.arvoalue_id = arvoalue.id
									    where inventointiprojekti_arvoalue.inventointiprojekti_id = :id                                        
                                        and inventointiprojekti_arvoalue.poistettu is null
                                        and arvoalue.poistettu is null
									    group by kyla.id, inventointiprojekti_arvoalue.inventointiprojekti_id
									) or kyla.id in (
									    select kyla.id as kid 
									    from kyla
									    join alue_kyla on alue_kyla.kyla_id = kyla.id
									    left join alue on alue.id = alue_kyla.alue_id
									    left join inventointiprojekti_alue on inventointiprojekti_alue.alue_id = alue.id
									    where inventointiprojekti_alue.inventointiprojekti_id = :id                                        
                                        and inventointiprojekti_alue.poistettu is null
                                        and alue.poistettu is null
									    group by kyla.id, inventointiprojekti_alue.inventointiprojekti_id
									) or kyla.id in (
									    select kyla.id as kid
									    from kyla 
									    join kiinteisto on kiinteisto.kyla_id = kyla.id
									    left join inventointiprojekti_kiinteisto on inventointiprojekti_kiinteisto.kiinteisto_id = kiinteisto.id
									    where inventointiprojekti_kiinteisto.inventointiprojekti_id = :id                                        
                                        and inventointiprojekti_kiinteisto.poistettu is null
                                        and kiinteisto.poistettu is null
									    group by kyla.id, inventointiprojekti_kiinteisto.inventointiprojekti_id
									)', ['id' => $id])->skip($rivi)->take($riveja)->orderBy($jarjestys_kentta, $jarjestys_suunta)->get()->toArray());
							
						}
						
						// Kuntien ja kylien suodatus
						if(strlen($suodata_kunnat) > 0 || strlen($suodata_kylat) > 0) {

						    // Kuntien suodatus valittujen mukaan
						    if(strlen($suodata_kunnat) > 0){
						        
						        // muodostetaan pilkulla eroteltu array ja int muunnos
						        $kuntaArray = array_map('intval', explode(',', $suodata_kunnat));
						        $villages = $villages->whereIn('kunta_id', $kuntaArray);
						    }
						    
						    // Kylien suodatus valittujen mukaan
						    if(strlen($suodata_kylat) > 0){

						        $kylaArray = explode(',', $suodata_kylat);
						        
						        $villages = Kyla::hydrate(DB::table('kyla')
						            ->whereIn('id', $kylaArray)->skip($rivi)->take($riveja)->get());
						    }
						    // Suodattettujen määrä
						    $total_rows = count ( $villages );
						}
						
						if (count ( $villages ) <= 0) {
							// MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
							MipJson::addMessage ( Lang::get ( 'kyla.search_not_found' ) );
						} else {
						    MipJson::initGeoJsonFeatureCollection ( count ( $villages ), $total_rows );
							MipJson::addMessage ( Lang::get ( 'kyla.found_count', [ 
									"count" => count ( $villages ) 
							] ) );
							
							foreach ( $villages as $village ) {			
								$village->kunta = DB::table('kunta')->where('kunta.id', '=', $village->kunta_id)->first();
								MipJson::addGeoJsonFeatureCollectionFeaturePoint ( null, $village );
							}
						}
					} else {
						MipJson::setGeoJsonFeature ();
						MipJson::addMessage ( Lang::get ( 'kunta.search_not_found' ) );
						MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
					}
				}
			} catch ( QueryException $e ) {
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'kyla.search_failed' ) );
				MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
			}
		}
		return MipJson::getJson ();
	}
	/**
	 * Palauttaa kaikki inventointiprojektin kylät
	 */
	public function listAllVillages($id) {

	    /*
	     * Role check
	     */
	    if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.katselu' )) {
	        MipJson::setGeoJsonFeature ();
	        MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
	        MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
	        return MipJson::getJson ();
	    }
	    
	    $villages = DB::table('kyla')
	    ->selectRaw('*')
	    ->whereRaw('
					 kyla.id in (
					    select kyla.id as kid
					    from kyla
					    join arvoalue_kyla on arvoalue_kyla.kyla_id = kyla.id
					    left join arvoalue on arvoalue.id = arvoalue_kyla.arvoalue_id
					    left join inventointiprojekti_arvoalue on inventointiprojekti_arvoalue.arvoalue_id = arvoalue.id
					    where inventointiprojekti_arvoalue.inventointiprojekti_id = :id
                        and inventointiprojekti_arvoalue.poistettu is null
                        and arvoalue.poistettu is null
					    group by kyla.id, inventointiprojekti_arvoalue.inventointiprojekti_id
					) or kyla.id in (
					    select kyla.id as kid
					    from kyla
					    join alue_kyla on alue_kyla.kyla_id = kyla.id
					    left join alue on alue.id = alue_kyla.alue_id
					    left join inventointiprojekti_alue on inventointiprojekti_alue.alue_id = alue.id
					    where inventointiprojekti_alue.inventointiprojekti_id = :id
                        and inventointiprojekti_alue.poistettu is null
                        and alue.poistettu is null
					    group by kyla.id, inventointiprojekti_alue.inventointiprojekti_id
					) or kyla.id in (
					    select kyla.id as kid
					    from kyla
					    join kiinteisto on kiinteisto.kyla_id = kyla.id
					    left join inventointiprojekti_kiinteisto on inventointiprojekti_kiinteisto.kiinteisto_id = kiinteisto.id
					    where inventointiprojekti_kiinteisto.inventointiprojekti_id = :id
                        and inventointiprojekti_kiinteisto.poistettu is null
                        and kiinteisto.poistettu is null
					    group by kyla.id, inventointiprojekti_kiinteisto.inventointiprojekti_id
					)', ['id' => $id])
					->orderBy('kyla.nimi', 'asc')
					->get();
	    
					return $villages;
	}
	
	/**
	 * Get the estates liked to given inventoring project
	 *
	 * @param int $id        	
	 * @return Mixed <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @version 1.0
	 * @since 1.0
	 */
	public function listEstates(Request $request, $id) {
		
		/*
		 * Role check
		 */
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.katselu' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
		
		if (! is_numeric ( $id )) {
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			return MipJson::getJson ();
		}
		
		try {
			
			$validator = Validator::make ( $request->all (), [ 
					'rivi' => 'numeric',
					'rivit' => 'numeric',
					'jarjestys' => 'string',
					'jarjestys_suunta' => 'string',
					'inventoija_id' => 'numeric|exists:kayttaja,id' 
			] );
			
			if ($validator->fails ()) {
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
				foreach ( $validator->errors ()->all () as $error ) {
					MipJson::addMessage ( $error );
				}
				MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			} else {
				
				$rivi = (isset ( $request->rivi ) && is_numeric ( $request->rivi )) ? $request->rivi : 0;
				$riveja = (isset ( $request->rivit ) && is_numeric ( $request->rivit )) ? $request->rivit : 100;
				$jarjestys_kentta = (isset ( $request->jarjestys )) ? $request->jarjestys : "kiinteistotunnus";
				$jarjestys_suunta = (isset ( $request->jarjestys_suunta )) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";
				

				$project = Inventointiprojekti::find ( $id );
				
				if (!$project) {
					MipJson::setGeoJsonFeature ();
					MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
					MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
				}
					
				$entities = Kiinteisto::getAll();
				$entities->withOrderBy(null, null, null);
				$entities->withInventointiprojektiOrInventoija($id, $request->inventoija_id);
				$entities->with('inventointiprojektit');
			/*
				$entities->with(array(
							'inventointitiedot' =>function($query) use ($id) {
								$query->where('inventointiprojekti_kiinteisto.inventointiprojekti_id', '=', $id)
								->groupBy('inventoija');
						}
				));
			*/
				// Kuntien suodatuksen parametrit
				$suodata_kunnat = $request->suodata_kunnat;
				
				if(strlen($suodata_kunnat) > 0){
				    // Kylien suodatus valittujen mukaan, muodostetaan pilkulla eroteltu array
				    $kuntaArray = explode(',', $suodata_kunnat);
				    $entities->whereIn('kunta_id', $kuntaArray);
				}
				
				// Kylien suodatuksen parametrit
				$suodata_kylat = $request->suodata_kylat;
				
				if(strlen($suodata_kylat) > 0){
				    // Kylien suodatus valittujen mukaan
				    $kylaArray = explode(',', $suodata_kylat);
				    $entities->whereIn('kyla_id', $kylaArray);
				}

				// Järjestys taulukon otsikosta 
				if(!empty($jarjestys_kentta)){
				    $entities->orderBy($jarjestys_kentta, $jarjestys_suunta);
				}
				
				
				$total_rows = Utils::getCount($entities);
				$entities = $entities->with('inventointiprojektit')->skip( $rivi )->take( $riveja )->get ();
				
				
				MipJson::initGeoJsonFeatureCollection ( count ( $entities ), $total_rows );
				MipJson::addMessage ( Lang::get ( 'kiinteisto.found_count', [ 
						"count" => count ( $entities ) 
				]));
				
				foreach ( $entities as $entity ) {
					/*
					 * Poistetaan "turhat" inventointiprojektit (eli ne joita ei tässä näkymässä tarvita, 
					 * eli siis ne joiden ID on eri kuin tämän inventointiprojektin ID ja tämän
					 * jälkeen haetaan jokaiselle inventointiprojektille inventoijat (ainoastaan viimeinen tallennus per inventoija). 
					 */
					for($i = sizeof($entity->inventointiprojektit) - 1; $i >= 0; $i--) {		
						$ip = $entity->inventointiprojektit[$i];
 						if($ip->id != $id) {
 							unset($entity->inventointiprojektit[$i]);
 						} else {
 							$ip->inventoijat = Kiinteisto::inventoijat($entity->id, $ip->id);
 						}
 					}
 					
					$properties = clone ($entity);
					unset ( $properties ['sijainti'] );
					
					MipJson::addGeoJsonFeatureCollectionFeaturePoint ( json_decode ( $entity->sijainti ), $properties );
				}
			}
		} catch ( QueryException $e ) {
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
		}
		
		return MipJson::getJson ();
	}
	
	/**
	 * Get the estates liked to given inventoring project
	 *
	 * @param int $id        	
	 * @return Mixed <multitype:, \Symfony\Component\HttpFoundation\Response, \Illuminate\Contracts\Routing\ResponseFactory>
	 * @version 1.0
	 * @since 1.0
	 */
	public function listBuildings(Request $request, $id) {
		
		/*
		 * Role check
		 */
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.katselu' )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
		
		if (! is_numeric ( $id )) {
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
		} else {
			try {
				
				$validator = Validator::make ( $request->all (), [ 
						'rivi' => 'numeric',
						'rivit' => 'numeric',
						'jarjestys' => 'string',
						'jarjestys_suunta' => 'string',
						'inventoija_id' => 'numeric|exists:kayttaja,id' 
				] );
				
				if ($validator->fails ()) {
					MipJson::setGeoJsonFeature ();
					MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
					foreach ( $validator->errors ()->all () as $error )
						MipJson::addMessage ( $error );
					MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
				} else {
					
					$rivi = (isset ( $request->rivi ) && is_numeric ( $request->rivi )) ? $request->rivi : 0;
					$riveja = (isset ( $request->rivit ) && is_numeric ( $request->rivit )) ? $request->rivit : 100;
					$jarjestys_kentta = (isset ( $request->jarjestys )) ? $request->jarjestys : "id";
					$jarjestys_suunta = (isset ( $request->jarjestys_suunta )) ? ($request->jarjestys_suunta == "laskeva" ? "desc" : "asc") : "asc";
					
					$project = Inventointiprojekti::find ( $id );
					
					if ($project) {

                        // Haetaan rakennukset taulun tiedot
						
						$rak_raktyypit_sql  = "( select rakennus_id, ";
						$rak_raktyypit_sql .= "   string_agg(rakennustyyppi.".self::getLocalizedfieldname('nimi').", ', ') as rakennustyypit ";
						$rak_raktyypit_sql .= "   from rakennus_rakennustyyppi, rakennustyyppi ";
						$rak_raktyypit_sql .= "   where rakennus_rakennustyyppi.rakennustyyppi_id = rakennustyyppi.id ";
						$rak_raktyypit_sql .= "   group by rakennus_id ";
						$rak_raktyypit_sql .= ") as rak_rakennustyypit ";
						
						$entities = DB::table('rakennus')
						->select(   
					              'rakennus.id',
                                  'kiinteisto.kiinteistotunnus as kiinteistotunnus',
                                  'kiinteisto.nimi as kiinteisto_nimi',
						          'kiinteisto.palstanumero as palstanumero',
						          'rakennustyypit',
						           DB::raw(MipGis::getGeometryFieldQueryString("rakennuksen_sijainti", "sijainti"))
                                    )
                                    ->join('inventointiprojekti_kiinteisto', 'inventointiprojekti_kiinteisto.kiinteisto_id', '=', 'rakennus.kiinteisto_id')
                                    ->join('kiinteisto', 'kiinteisto.id', '=', 'rakennus.kiinteisto_id')
                                    ->join('kyla', 'kyla.id', '=', 'kiinteisto.kyla_id')
                                    ->join('kunta', 'kunta.id', '=', 'kyla.kunta_id')
                                    ->leftJoin(DB::raw($rak_raktyypit_sql), 'rakennus.id', '=', 'rak_rakennustyypit.rakennus_id')
                                    ->whereNull('rakennus.poistettu')->whereNull('kiinteisto.poistettu')->whereNull('inventointiprojekti_kiinteisto.poistettu')
                                    ->where('inventointiprojekti_kiinteisto.inventointiprojekti_id', '=', $id)
                                    ->distinct();

						// Inventoijan mukainen suodatus
                         if ($request->inventoija_id){
                             $entities = $entities->where ( 'inventoija_id', '=', $request->inventoija_id );
                         }
							
						
						// Kuntien suodatuksen parametrit
						$suodata_kunnat = $request->suodata_kunnat;
						
						if(strlen($suodata_kunnat) > 0){
						    // Kylien suodatus valittujen mukaan, muodostetaan pilkulla eroteltu array
						    $kuntaArray = explode(',', $suodata_kunnat);
						    $entities->whereIn('kunta_id', $kuntaArray);
						}
						
						// Kylien suodatuksen parametrit
						$suodata_kylat = $request->suodata_kylat;
						
						if(strlen($suodata_kylat) > 0){
						    // Kylien suodatus valittujen mukaan, muodostetaan pilkulla eroteltu array
						    $kylaArray = explode(',', $suodata_kylat);
						    $entities->whereIn('kyla_id', $kylaArray);
						}
						
						// Järjestys taulukon otsikosta
						if(!empty($jarjestys_kentta)){
						    $entities->orderBy($jarjestys_kentta, $jarjestys_suunta);
						}
							
						$total_rows = Utils::getCount($entities);
						$entities = $entities->skip ( $rivi )->take ( $riveja )->get ();
						
						if ($total_rows > 0) {
							MipJson::initGeoJsonFeatureCollection ( count ( $entities ), $total_rows );
							MipJson::addMessage ( Lang::get ( 'rakennus.search_success' ) );
							foreach ( $entities as $entity ) {
								$properties = clone ($entity);
								unset ( $properties->rakennuksen_sijainti, $properties->sijainti );
								
								MipJson::addGeoJsonFeatureCollectionFeaturePoint ( json_decode ( $entity->sijainti ), $properties );
							}
						}
						if (count ( $entities ) <= 0) {
							MipJson::setGeoJsonFeature ();							
							MipJson::addMessage ( Lang::get ( 'rakennus.search_not_found' ) );
						}
					} else {
						MipJson::setGeoJsonFeature ();
						MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
						MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
					}
				}
			} catch ( QueryException $e ) {
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_failed' ) );
				MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
			}
		}
		return MipJson::getJson ();
	}
	
	/**
	 * Lokalisoitu kenttä
	 */
	public static function getLocalizedfieldname($field_name) {
	    
	    if (App::getLocale()=="fi") {
	        return $field_name."_fi";
	    }
	    if (App::getLocale()=="en") {
	        return $field_name."_en";
	    }
	    if (App::getLocale()=="se") {
	        return $field_name."_se";
	    }
	    return $field_name."_fi";
	}
	
	/*
	 * Get the latest value for the inventointipaiva. Note: We do not want the newest inventointipaiva value, we want the row that has been inserted recently and that inventointipaiva value is used.
	 */
	public function getInventointipaiva(Request $request, $inventointiprojekti_id) {
		if (! is_numeric ( $inventointiprojekti_id )) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			return MipJson::getJson ();
		}
				
		$validator = Validator::make ( $request->all (), [
				'entiteetti_tyyppi' => 'required|string',
				'entiteetti_id' => 'required|numeric', 
				'inventoija_id' => 'required|numeric'
		]);				
		
		if ($validator->fails ()) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			foreach ( $validator->errors ()->all () as $error ) {
				MipJson::addMessage ( $error );
			}
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			return MipJson::getJson ();
		}
	
		/*
		 * Permission check
		 */
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.katselu')) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
	
		try {
			if($request->entiteetti_tyyppi =='kiinteisto') {
				$entity = InventointiprojektiKiinteisto::getInventointipaiva($inventointiprojekti_id, $request->entiteetti_id, $request->inventoija_id);	
				
			} else if($request->entiteetti_tyyppi == 'alue') {
				$entity = InventointiprojektiAlue::getInventointipaiva($inventointiprojekti_id, $request->entiteetti_id, $request->inventoija_id);
				
			} else if($request->entiteetti_tyyppi == 'arvoalue') {
				$entity = InventointiprojektiArvoalue::getInventointipaiva($inventointiprojekti_id, $request->entiteetti_id, $request->inventoija_id);
				
			}
						
			$properties = $entity;
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_success' ) );
			MipJson::setGeoJsonFeature ( null, $properties );
			MipJson::setResponseStatus ( Response::HTTP_OK );
			
		} catch ( QueryException $e ) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.get_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
		}
	
		return MipJson::getJson ();
	}
	
	/*
	 * Get the latest value for the kenttapaiva. 
	 * Note: We do not want the newest kenttapaiva value, we want the row that has been inserted recently.
	 */
	public function getKenttapaiva(Request $request, $inventointiprojekti_id) {
		if (! is_numeric ( $inventointiprojekti_id )) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			return MipJson::getJson ();
		}
		
		$validator = Validator::make ( $request->all (), [
				'entiteetti_tyyppi' => 'required|string',
				'entiteetti_id' => 'required|numeric',
				'inventoija_id' => 'required|numeric'
		]);
		
		if ($validator->fails ()) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			foreach ( $validator->errors ()->all () as $error ) {
				MipJson::addMessage ( $error );
			}
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			return MipJson::getJson ();
		}
		
		/*
		 * Permission check
		 */
		if (! Kayttaja::hasPermission ( 'rakennusinventointi.inventointiprojekti.katselu')) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
		
		try {
			if($request->entiteetti_tyyppi =='kiinteisto') {
				$entity = InventointiprojektiKiinteisto::getKenttapaiva($inventointiprojekti_id, $request->entiteetti_id, $request->inventoija_id);
				
			} else if($request->entiteetti_tyyppi == 'alue') {
				$entity = InventointiprojektiAlue::getKenttapaiva($inventointiprojekti_id, $request->entiteetti_id, $request->inventoija_id);
				
			} else if($request->entiteetti_tyyppi == 'arvoalue') {
				$entity = InventointiprojektiArvoalue::getKenttapaiva($inventointiprojekti_id, $request->entiteetti_id, $request->inventoija_id);
				
			}
			
			$properties = $entity;
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_success' ) );
			MipJson::setGeoJsonFeature ( null, $properties );
			MipJson::setResponseStatus ( Response::HTTP_OK );
			
		} catch ( QueryException $e ) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.get_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
		}
		
		return MipJson::getJson ();
	}
	
	/**
	 * 
	 * Add inventointiprojekti-inventoija
	 *
	 * @param Request $request        	
	 * @param $inventointiprojekti_id        	
	 * @throws AuthorizationException
	 * @DEPRECATED
	 */
	public function addInventor(Request $request, $inventointiprojekti_id) {
		if (! is_numeric ( $inventointiprojekti_id )) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			return MipJson::getJson ();
		}
		
		$validator = Validator::make ( $request->all (), [ 
				'inventoija_id' => 'required|numeric|exists:kayttaja,id' 
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
		
		/*
		 * Permission check
		 */
		if (! Kayttaja::hasPermissionForEntity ( 'rakennusinventointi.inventointiprojekti.muokkaus', $inventointiprojekti_id )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
		
		try {
			$i = Inventointiprojekti::find ( $inventointiprojekti_id );
			
			if (! $i) {
				// error, entity not found
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
				MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
				return MipJson::getJson ();
			}
			
			$entity = $request->all ();
			
			$entity = new InventointiprojektiInventoija ();
			$entity->inventoija_id = $request->inventoija_id;
			$entity->inventointiprojekti_id = $inventointiprojekti_id;
			$entity->inventoija_arvo = $request->inventoija_arvo;
			$entity->inventoija_organisaatio = $request->inventoija_organisaatio;
			$entity->inventoija_nimi = $request->inventoija_nimi;
			
			$entity->save ();
			
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.save_success' ) );
			MipJson::setGeoJsonFeature ( null, array (
					"id" => $entity->id 
			) );
			MipJson::setResponseStatus ( Response::HTTP_OK );
		} catch ( QueryException $e ) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.get_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
		}
		
		return MipJson::getJson ();
	}
	
	/**
	 * Delete inventointiprojekti-inventoija
	 *
	 * @param Request $request        	
	 * @param $inventointiprojekti_id        	
	 * @throws AuthorizationException
	 * @DEPRECATED
	 */
	public function deleteInventor(Request $request, $inventointiprojekti_id, $inventoija_id) {
		if (! is_numeric ( $inventointiprojekti_id ) || ! is_numeric ( $inventoija_id )) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'validation.custom.user_input_validation_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_BAD_REQUEST );
			return MipJson::getJson ();
		}
		
		$validator = Validator::make ( $request->all (), [ 
				'inventoija_id' => 'required|numeric|exists:kayttaja,id',
				'inventointiprojekti_id' => 'required|numeric|exists:inventointiprojekti,id' 
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
		
		/*
		 * Permission check
		 */
		if (! Kayttaja::hasPermissionForEntity ( 'rakennusinventointi.inventointiprojekti.muokkaus', $inventointiprojekti_id )) {
			MipJson::setGeoJsonFeature ();
			MipJson::setResponseStatus ( Response::HTTP_FORBIDDEN );
			MipJson::addMessage ( Lang::get ( 'validation.custom.permission_denied' ) );
			return MipJson::getJson ();
		}
		
		try {
			$i = Inventointiprojekti::find ( $inventointiprojekti_id );
			
			if (! $i) {
				// error, entity not found
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'inventointiprojekti.search_not_found' ) );
				MipJson::setResponseStatus ( Response::HTTP_NOT_FOUND );
				return MipJson::getJson ();
			}
			
			$entity = InventointiprojektiInventoija::where ( 'inventointiprojekti_id', '=', $inventointiprojekti_id )->where ( 'inventoija_id', '=', $inventoija_id )->first ();
			$deleted_rows = $entity->delete ();
			
			if ($deleted_rows == 1) {
				MipJson::addMessage ( Lang::get ( 'inventointiprojektiInventoija.delete_success' ) );
				MipJson::setGeoJsonFeature ( null, array (
						"id" => $entity->id 
				) );
			} else {
				MipJson::setGeoJsonFeature ();
				MipJson::addMessage ( Lang::get ( 'inventointiprojektiInventoija.delete_failed' ) );
				MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
			}
		} catch ( QueryException $e ) {
			MipJson::setGeoJsonFeature ();
			MipJson::addMessage ( Lang::get ( 'inventointiprojekti.get_failed' ) );
			MipJson::setResponseStatus ( Response::HTTP_INTERNAL_SERVER_ERROR );
		}
		
		return MipJson::getJson ();
	}
	
	public function historia($id) {
		
		/*
		 * Role check
		 */
		if(!Kayttaja::hasPermission('rakennusinventointi.inventointiprojekti.katselu')) {
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
		
		return InventointiprojektiMuutoshistoria::getById($id);
		
	}
}
