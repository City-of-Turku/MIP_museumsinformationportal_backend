<?php

namespace App\Http\Controllers;

use App\Kayttaja;
use App\Ark\Tutkimus;
use App\Utils;
use App\Library\Gis\MipGis;
use App\Library\String\MipJson;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Reitti;

class ReittiController extends Controller {

    // Metodi jolla saadaan listattua kaikki järjestelmässä olevat reitit ja mihin entiteettiin ne liittyvät.
    public function index(Request $request) {
        // Täytyy olla pääkäyttäjä
        $kayttaja = Auth::user();
        $rakRooli = $kayttaja->rooli;
        $arkRooli = $kayttaja->ark_rooli;
        if($rakRooli != 'paakayttaja' && $arkRooli != 'paakayttaja') {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
            MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
            return MipJson::getJson();
        }

        try {
            $reitit = Reitti::getAll()->with(array('luoja', 'muokkaaja'))->get();

            $total_rows = Utils::getCount($reitit);
            MipJson::initGeoJsonFeatureCollection(count($reitit), $total_rows);

            foreach($reitit as $reitti) {
                $entiteetti = null; // Liittyvä entiteetti, kuten rakennus, kohde, arvoalue jne.
                switch ($reitti->entiteetti_tyyppi) {
                    case 'Kohde':
                        $entiteetti = DB::table('ark_kohde')->where('id', $reitti->entiteetti_id)->first();
                        $reitti->entiteettiTyyppi = 'Kohde';
                        break;
                    case 'Inventointitutkimus':
                        $entiteetti = DB::table('ark_tutkimus')->where('id', $reitti->entiteetti_id)->first();
                        $reitti->entiteettiTyyppi = 'Inventointitutkimus';
                        break;
                    case 'Tarkastustutkimus':
                        $entiteetti = DB::table('ark_tutkimus')->where('id', $reitti->entiteetti_id)->first();
                        $reitti->entiteettiTyyppi = 'Tarkastustutkimus';
                        break;
                    case 'Rakennus':
                        $entiteetti = DB::table('rakennus')->where('id', $reitti->entiteetti_id)->first();
                        $reitti->entiteettiTyyppi = 'Rakennus';
                        break;
                    case 'Arvoalue':
                        $entiteetti = DB::table('arvoalue')->where('id', $reitti->entiteetti_id)->first();
                        $reitti->entiteettiTyyppi = 'Arvoalue';
                        break;
                }

                $reitti->entiteetti = $entiteetti;

                $geom = $reitti->getGeometryAttribute();
                MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($geom), $reitti);
            }

            MipJson::addMessage(Lang::get('reitti.search_success'));
        }  catch(QueryException $e) {
            MipJson::setGeoJsonFeature();
            MipJson::addMessage(Lang::get('reitti.search_failed'));
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return MipJson::getJson();

    }

	// Hae tiettyyn entiteettiin (tutkimus, kohde, rakennus...) linkitetyt reitit
	public function show($entiteettiTyyppi, $entiteettiId) {

		/*
		 * Role check - Not needed: If the user currently has access to the entity, the route can also be requested
		 */
		/*if(!Kayttaja::hasPermission('rakennusinventointi.kiinteisto.katselu')) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}*/
		try {
		    $reitit = Reitti::getAllByEntiteettiTyyppiAndId($entiteettiTyyppi, $entiteettiId)->with(array('luoja', 'muokkaaja'));

		    // calculate the total rows of the search results
		    $total_rows = Utils::getCount($reitit);

		    // Haetaan ainoastaan 50 riviä
		    $reitit->withLimit(0, 50);

		    // Execute the query
		    $reitit = $reitit->get();

		    MipJson::initGeoJsonFeatureCollection(count($reitit), $total_rows);

		    foreach ($reitit as $entity) {
		        $geom = $entity->getGeometryAttribute();
		        MipJson::addGeoJsonFeatureCollectionFeaturePoint(json_decode($geom), $entity);
		    }
		    MipJson::addMessage(Lang::get('reitti.search_success'));
		} catch(QueryException $e) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('reitti.search_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		return MipJson::getJson();
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {

	    $kayttaja = Auth::user();
	    $rakRooli = $kayttaja->rooli;
	    $arkRooli = $kayttaja->ark_rooli;

	    // Jos liitetään kohteeseen, rakennukseen tai arvoalueeseen, tulee niihin olla oikeus.
	    // Jos liitetään tutkimukseen, tulee sen alla oleviin tietoihin olla muokkausoikeudet
	    $hasPermission = false;
	    $eType = $request->input('entiteetti_tyyppi');

	    if($eType == 'Rakennus') {
	        $hasPermission = Kayttaja::hasPermission('rakennusinventointi.rakennus.muokkaus');
	    } else if($eType == 'Arvoalue') {
	        $hasPermission = Kayttaja::hasPermission('rakennusinventointi.arvoalue.muokkaus');
	    } else if($eType == 'Kohde') {
	        $hasPermission = Kayttaja::hasPermission('arkeologia.ark_kohde.muokkaus');
	    } else if($eType == 'Inventointitutkimus' || $eType == 'Tarkastustutkimus') {
	        // HUOM: Riittänee, että on oikeus luoda esimerkiksi löytö, silloin on oikeus muokata tutkimukseen liittyviä tietoja
	        $hasPermission = Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_loyto.luonti', $request->input('entiteetti_id'));
	    }

	    if(!$hasPermission) {
	        MipJson::setGeoJsonFeature();
	        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
	        MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
	        return MipJson::getJson();
	    }

		try {
			DB::beginTransaction();
			Utils::setDBUser();

			// Varmistetaan, että linkattava entiteetti on olemassa
			$relatedEntity = null;
			if($request->input('entiteetti_tyyppi') =='Kohde') {
			    $relatedEntity = DB::table('ark_kohde')->where('id', $request->input('entiteetti_id'))->first();
			} else if($request->input('entiteetti_tyyppi') =='Inventointitutkimus' || $request->input('entiteetti_tyyppi') =='Tarkastustutkimus') {
			    $relatedEntity = DB::table('ark_tutkimus')->where('id', $request->input('entiteetti_id'))->first();
			} else if($request->input('entiteetti_tyyppi') =='Rakennus') {
			    $relatedEntity = DB::table('rakennus')->where('id', $request->input('entiteetti_id'))->first();
			} else if($request->input('entiteetti_tyyppi') =='Arvoalue') {
			    $relatedEntity = DB::table('arvoalue')->where('id', $request->input('entiteetti_id'))->first();
			}
			if($relatedEntity == null) {
			    MipJson::setGeoJsonFeature();
			    MipJson::addMessage(Lang::get('reitti.entity_search_failed'));
			    MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			    return MipJson::getJson();
			}

			$reitti = new Reitti($request->all());

			// Tehdään LINESTRING tyyppinen geometria
			$lineString = 'LINESTRING(';
			$pisteet = $request->input('pisteet');
			foreach($pisteet as $piste) {
			    $lineString .= $piste['lon'].' '.$piste['lat'].',';
			}
			$lineString = substr($lineString, 0, -1); // Viimeinen , pois
			$lineString .= ')';
            $geom = MipGis::getGeometryFromText($lineString);
			$reitti->reitti = $geom;

			$reitti->luoja = Auth::user()->id;

			$reitti->save();

			DB::commit();
		} catch (Exception $e) {
			DB::rollback();
			throw $e;
		}

		MipJson::addMessage(Lang::get('reitti.save_success'));
		MipJson::setGeoJsonFeature(null, array("id" => $reitti->id));


		return MipJson::getJson();
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id) {

	    $entity = Reitti::find($id);


		/*
		 * Role check - Pitää olla pääkäyttäjä, tutkija tai reitin lisääjä.
		 * PHP ei jostain syystä halua kaikkia iffin ehtoja samaan, joten tehty näin.
		 */
	    $kayttaja = Auth::user();
	    $rakRooli = $kayttaja->rooli;
	    $arkRooli = $kayttaja->ark_rooli;
			$hasPermission = false;
			if($rakRooli == 'pääkäyttäjä') {
	        $hasPermission = true;
			}
			if($rakRooli == 'tutkija') {
	        $hasPermission = true;
	    }
			if($arkRooli == 'pääkäyttäjä') {
	        $hasPermission = true;
			}
			if($arkRooli == 'tutkija') {
	        $hasPermission = true;
			}
			if($entity->luoja == $kayttaja->id) {
	        $hasPermission = true;
	    }

	    if(!$hasPermission) {
	        MipJson::setGeoJsonFeature();
	        MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
	        MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
	        return MipJson::getJson();
	    }

		if(!$entity) {
			// return: not found
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('reitti.search_not_found'));
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
			return MipJson::getJson();
		}

		try {
			DB::beginTransaction();
			Utils::setDBUser();

			$author_field = Reitti::DELETED_BY;
			$when_field = Reitti::DELETED_AT;
			$entity->$author_field = Auth::user()->id;
			$entity->$when_field = \Carbon\Carbon::now();
			$entity->save();

			DB::commit();

			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			MipJson::addMessage(Lang::get('reitti.delete_success'));
		} catch (Exception $e) {
			DB::rollback();

			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('reitti.delete_failed'));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		return MipJson::getJson();
	}
}
