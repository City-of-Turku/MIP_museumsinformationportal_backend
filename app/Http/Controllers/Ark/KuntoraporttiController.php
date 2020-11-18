<?php

namespace App\Http\Controllers\Ark;

use App\Ark\ArkKuntoraportti;
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

class KuntoraporttiController extends Controller
{
	public function store(Request $request)
	{
		/*
		 * Käyttöoikeus - konservointinäkymässä olevat tiedot
		 */
		if (!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.muokkaus', $request->all()['properties']['ark_loyto_id'])) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		try {
			DB::beginTransaction();
			Utils::setDBUser();
			$entity = new ArkKuntoraportti($request->all()['properties']);
			$entity->luoja = Auth::user()->id;
			$entity->save();
		} catch (Exception $e) {
			Log::debug("Kuntoraportti store failed: " . $e);
			DB::rollback();
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('kuntoraportti.save_failed'));
			return MipJson::getJson();
		}
		// Onnistunut case
		DB::commit();

		MipJson::addMessage(Lang::get('kuntoraportti.save_success'));
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
		if (!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.muokkaus', $request->all()['properties']['ark_loyto_id'])) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		$entity = ArkKuntoraportti::find($id);

		if (!$entity) {
			//error, entity not found
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('kuntoraportti.search_not_found'));
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
			return MipJson::getJson();
		}

		try {
			DB::beginTransaction();
			Utils::setDBUser();

			$entity->fill($request->all()['properties']);

			$author_field = ArkKuntoraportti::UPDATED_BY;
			$entity->$author_field = Auth::user()->id;
			$entity->update();

			DB::commit();

			MipJson::addMessage(Lang::get('kuntoraportti.save_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $entity->id));
			MipJson::setResponseStatus(Response::HTTP_OK);
		} catch (Exception $e) {
			DB::rollback();
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
			MipJson::addMessage(Lang::get('kuntoraportti.save_failed'));
		}

		return MipJson::getJson();
	}


	public function destroy($id)
	{

		$kr = ArkKuntoraportti::find($id);

		if (!$kr) {
			MipJson::setGeoJsonFeature();
			MipJson::addMessage(Lang::get('kuntoraportti.search_not_found'));
			MipJson::setResponseStatus(Response::HTTP_NOT_FOUND);
			return MipJson::getJson();
		}

		if (!Kayttaja::hasPermissionForEntity('arkeologia.ark_loyto.poisto', $kr->ark_loyto_id)) {
			MipJson::setGeoJsonFeature();
			MipJson::setResponseStatus(Response::HTTP_FORBIDDEN);
			MipJson::addMessage(Lang::get('validation.custom.permission_denied'));
			return MipJson::getJson();
		}

		try {
			DB::beginTransaction();
			Utils::setDBUser();

			$author_field = ArkKuntoraportti::DELETED_BY;
			$when_field = ArkKuntoraportti::DELETED_AT;
			$kr->$author_field = Auth::user()->id;
			$kr->$when_field = \Carbon\Carbon::now();

			$kr->save();

			DB::commit();

			MipJson::addMessage(Lang::get('kuntoraportti.delete_success'));
			MipJson::setGeoJsonFeature(null, array("id" => $kr->id));
		} catch (Exception $e) {
			DB::rollback();
			MipJson::setGeoJsonFeature();
			MipJson::setMessages(array(Lang::get('kuntoraportti.delete_failed'), $e->getMessage()));
			MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		return MipJson::getJson();
	}
}
