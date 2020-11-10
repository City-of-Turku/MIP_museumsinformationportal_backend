<?php

namespace App\Http\Controllers;

use App\Kayttaja;
use App\Library\String\MipJson;
use Illuminate\Support\Facades\Lang;

class RooliController extends Controller{
    
	/**
	 * Method to get LOGGED IN users permissions on given section/entity
	 * 
	 * @param String $section (rakennusinventointi, arkeologia)
	 * @param String $entity (kiinteisto, rakennus, alue ...)
	 * @return MipJson
	 * @author 
	 * @version 1.0
	 * @since 1.0
	 */
	function show($section, $entity) {
		$data = Kayttaja::getPermissions($section, $entity);
				
		MipJson::setGeoJsonFeature();
		MipJson::setData($data, 1, 1);
		MipJson::addMessage(Lang::get('rooli.search_success'));
		
		return MipJson::getJson();
	}
	
	/**
	 * Method to get permissions for a given entity identified by id in given section for currently logged in user.
	 * 
	 * @param String $section (arkeologia ONLY)
	 * @param String $entity (projekti, yksikko, kartta ...)
	 * @param $id
	 * @return MipJson
	 * @author ATR Soft Oy
	 * @version 1.0
	 * @since 1.0
	 */
	function showPermissionsForEntity($section, $entity, $id) {
		//ark_tutkimus_sub entiteetillä haetaan oikeudet jotka käyttäjällä on 
		//pyydettyyn arkeologiseen tutkimukseen liittyviin datoihin.
		if($entity == 'ark_tutkimus_sub') {
			$data = Kayttaja::getArkTutkimusSubPermissions($id);
		} else {
			$data = Kayttaja::getPermissionsByEntity($section, $entity, $id);
		}
		
		MipJson::setGeoJsonFeature();
		MipJson::setData($data, 1, 1);
		MipJson::addMessage(Lang::get('rooli.search_success'));
		
		return MipJson::getJson();
	}	
}
