<?php

namespace App\Http\Controllers\Ark;

use App\Ark\ArkKuva;
use App\Ark\Tutkimus;
use App\Ark\Tutkimusraportti;
use App\Ark\TutkimusraporttiKuva;
use App\Http\Controllers\Controller;
use App\Kayttaja;
use App\Library\String\MipJson;
use App\Utils;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TutkimusraporttiController extends Controller
{
	public function store(Request $request)
	{
		/*
		 * Käyttöoikeus - Jos on oikeudet tutkimukseen liittyvien entiteettien muokkaukseen, on oikeudet raportin muokkaukseen
		 */
		if (!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_loyto.muokkaus', $request->all()['properties']['ark_tutkimus_id'])) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		try {
			DB::beginTransaction();
			Utils::setDBUser();
			$entity = new Tutkimusraportti($request->all()['properties']);
			$entity->luoja = Auth::user()->id;
			$entity->save();
			// Päivitetään myös tutkimuksen tiivistelmä vastaamaan tutkimusraportin tiivistelmää
			$tutkimus = Tutkimus::getSingle($entity->ark_tutkimus_id)->first();
			$tutkimus->tiivistelma = $request->all()['properties']['tiivistelma'];
			$tutkimus->update();

			// Kuvalinkkaukset
			TutkimusraporttiKuva::paivita_kuvat($entity->id, $request->input('properties.kuvat_kansilehti'), 'kansilehti');
			TutkimusraporttiKuva::paivita_kuvat($entity->id, $request->input('properties.kuvat_johdanto'), 'johdanto');
			TutkimusraporttiKuva::paivita_kuvat($entity->id, $request->input('properties.kuvat_havainnot'), 'havainnot');
			TutkimusraporttiKuva::paivita_kuvat($entity->id, $request->input('properties.kuvat_yhteenveto'), 'yhteenveto');
		} catch (Exception $e) {
			Log::debug("Tutkimusraportti store failed: " . $e);
			DB::rollback();
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('tutkimusraportti.save_failed'));
			return MipJson::getJson();
		}
		// Onnistunut case
		DB::commit();

		MipJson::addMessage(Lang::get('tutkimusraportti.save_success'));
		MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
		MipJson::setResponseStatus(Response::HTTP_OK);

		return MipJson::getJson();
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id)
	{
		if (!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_loyto.muokkaus', $request->all()['properties']['ark_tutkimus_id'])) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$entity = Tutkimusraportti::find($id);

		if (!$entity) {
			//error, entity not found
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('tutkimusraportti.search_not_found'));
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
			return MipJson::getJson();
		}

		try {
			DB::beginTransaction();
			Utils::setDBUser();

			$entity->fill($request->all()['properties']);

			$author_field = Tutkimusraportti::UPDATED_BY;
			$entity->$author_field = Auth::user()->id;
			$entity->update();
			// Päivitetään myös tutkimuksen tiivistelmä vastaamaan tutkimusraportin tiivistelmää
			$tutkimus = Tutkimus::getSingle($entity->ark_tutkimus_id)->first();
			$tutkimus->tiivistelma = $request->all()['properties']['tiivistelma'];
			$tutkimus->update();

			// Kuvalinkkaukset
			TutkimusraporttiKuva::paivita_kuvat($entity->id, $request->input('properties.kuvat_kansilehti'), 'kansilehti');
			TutkimusraporttiKuva::paivita_kuvat($entity->id, $request->input('properties.kuvat_johdanto'), 'johdanto');
			TutkimusraporttiKuva::paivita_kuvat($entity->id, $request->input('properties.kuvat_havainnot'), 'havainnot');
			TutkimusraporttiKuva::paivita_kuvat($entity->id, $request->input('properties.kuvat_yhteenveto'), 'yhteenveto');

			DB::commit();

			MipJson::addMessage(Lang::get('tutkimusraportti.save_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			MipJson::setResponseStatus(Response::HTTP_OK);
		} catch (Exception $e) {
			Log::debug($e);
			DB::rollback();
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('tutkimusraportti.save_failed'));
		}

		return MipJson::getJson();
	}


	public function destroy($id)
	{

		$tr = Tutkimusraportti::find($id);

		if (!$tr) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('tutkimusraportti.search_not_found'));
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
			return MipJson::getJson();
		}

		if (!Kayttaja::getArkTutkimusSubPermissions('arkeologia.ark_loyto.poisto', $tr->ark_tutkimus_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		try {
			DB::beginTransaction();
			Utils::setDBUser();

			$author_field = Tutkimusraportti::DELETED_BY;
			$when_field = Tutkimusraportti::DELETED_AT;
			$tr->$author_field = Auth::user()->id;
			$tr->$when_field = \Carbon\Carbon::now();

			$tr->save();

			DB::commit();

			MipJson::addMessage(Lang::get('tutkimusraportti.delete_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $tr->id));
		} catch (Exception $e) {
			DB::rollback();
			MipJson::setGeoJsonFeature();
			MipJson::setMessages(array(Lang::get('tutkimusraportti.delete_failed'), $e->getMessage()));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		return MipJson::getJson();
	}

	public function getSingle($id)
	{

		try {

			$entity = Tutkimusraportti::where('id', $id)->with('tutkimusraporttikuvat.ark_kuva', 'luoja', 'muokkaaja')->first();

			if (!$entity) {
				MipJson::setGeoJsonFeature();
				MipJson::addMessage(Lang::get('tutkimusraportti.search_not_found'));
				MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
				return MipJson::getJson();
			}

			/*
		   * Käyttöoikeus - Jos on oikeudet tutkimukseen liittyvien entiteettien muokkaukseen, on oikeudet raportin muokkaukseen
		   */
			if (!Kayttaja::hasArkTutkimusSubPermission('arkeologia.ark_loyto.muokkaus', $entity->tutkimus->ark_tutkimus_id)) {
				MipJson::setGeoJsonFeature();
				MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
				MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
				return MipJson::getJson();
			}

			$tutkimusraporttikuvat = $entity->tutkimusraporttikuvat()->get();
			$kuvat_kansilehti = [];
			$kuvat_johdanto = [];
			$kuvat_havainnot = [];
			$kuvat_tutkimus_ja_dokumentointimenetelmat = [];
			$kuvat_yhteenveto= [];

			foreach ($tutkimusraporttikuvat as $tutkimusraporttikuva) {
				// SoftDelete aiheuttaa ongelmia relaatioiden hakutilanteessa (hakee myös poistetut, jolloin ark_kuva jääkin nulliksi).
				// Siksi haetaan tässä myös poistetut ja käsitellään ainoastan jos kuvaa ei ole poistettu.
				$ark_kuva = $tutkimusraporttikuva->ark_kuva()->withTrashed()->with(['asiasanat', 'luoja', 'muokkaaja'])->first();
				if($ark_kuva->poistettu == null) {
					$ark_kuva = $ark_kuva->makeVisible(['polku']);

					$images = ArkKuva::getImageUrls($ark_kuva->polku . $ark_kuva->tiedostonimi);
					$ark_kuva->url = $images->original;
					$ark_kuva->url_tiny = $images->tiny;
					$ark_kuva->url_small = $images->small;
					$ark_kuva->url_medium = $images->medium;
					$ark_kuva->url_large = $images->large;

					if($tutkimusraporttikuva->kappale == 'kansilehti') {
						array_push($kuvat_kansilehti, $ark_kuva);
					}
					if($tutkimusraporttikuva->kappale == 'johdanto') {
						array_push($kuvat_johdanto, $ark_kuva);
					}

					if($tutkimusraporttikuva->kappale == 'havainnot') {
						array_push($kuvat_havainnot, $ark_kuva);
					}
					if($tutkimusraporttikuva->kappale == 'tutkimus_ja_dokumentointimenetelmat') {
						array_push($kuvat_tutkimus_ja_dokumentointimenetelmat, $ark_kuva);
					}

					if($tutkimusraporttikuva->kappale == 'yhteenveto') {
						array_push($kuvat_yhteenveto, $ark_kuva);
					}
				}
			}
			$entity->kuvat_kansilehti = $kuvat_kansilehti;
			$entity->kuvat_johdanto = $kuvat_johdanto;
			$entity->kuvat_havainnot = $kuvat_havainnot;
			$entity->kuvat_tutkimus_ja_dokumentointimenetelmat = $kuvat_tutkimus_ja_dokumentointimenetelmat;
			$entity->kuvat_yhteenveto = $kuvat_yhteenveto;
			//TODO LOPuT
			unset($entity->tutkimusraporttikuvat);

			// Muodostetaan propparit
			$properties = clone ($entity);

			MipJson::setGeoJsonFeature(null, $properties);
			MipJson::addMessage(Lang::get('tutkimusraportti.search_ok'));
			MipJson::setResponseStatus(Response::HTTP_OK);
		} catch (Exception $e) {
			Log::debug($e);
			MipJson::setGeoJsonFeature();
			MipJson::setMessages(array(Lang::get('tutkimusraportti.search_failed'), $e->getMessage()));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		return MipJson::getJson();
	}
}
