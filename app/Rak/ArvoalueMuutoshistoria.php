<?php

namespace App\Rak;

use App\Rak\Arvoalue;
use App\Library\History\HistoryPopulatorFactory;
use App\Muutoshistoria;

class ArvoalueMuutoshistoria extends Muutoshistoria
{
	protected static $entiteetti_taulu = "arvoalue";
	
	// returns the query
	private static function getEntriesById($id) {
		return parent::getHistoryById(self::$entiteetti_taulu, $id);
	}
	
	private static function getKuvaEntries($id) {
		return parent::getHistoryByKeyAndId('kuva_arvoalue', 'arvoalue_id', $id);
	}

	private static function getTiedostoEntries($id) {
		return parent::getHistoryByKeyAndId('tiedosto_arvoalue', 'arvoalue_id', $id);
	}
			
	private static function getDeletedKuvaEntries($id) {
		return parent::getDeletionHistory('kuva_arvoalue', 'arvoalue_id', $id, 'poistaja');
	}
	
	private static function getDeletedTiedostoEntries($id) {
		return parent::getDeletionHistory('tiedosto_arvoalue', 'arvoalue_id', $id, 'poistaja');
	}
	
	private static function getDeletedKylaEntries($id) {
		return parent::getDeletionHistory('arvoalue_kyla', 'arvoalue_id', $id, 'poistaja');
	}
	
	public static function getById($id) {
		
		// oma historia
		$historia = self::getEntriesById($id);
		
		// liitetyt kuvat historia
		$kuva_hst = self::getKuvaEntries($id);
		foreach($kuva_hst as $h) {
			$historia[] = $h;
		}
		
		// liitetiedostot historia
		$t_hst = self::getTiedostoEntries($id);
		foreach($t_hst as $h) {
			$historia[] = $h;
		}
		
		// poistetut liittyvät tiedot
				
		$d_r_hst = self::getDeletedTiedostoEntries($id);
		foreach($d_r_hst as $h) {
			$historia[] = $h;
		}
		
		$d_r_hst = self::getDeletedKuvaEntries($id);
		foreach($d_r_hst as $h) {
			$historia[] = $h;
		}
		
		foreach ($historia as $hi) {
		
			$populator = HistoryPopulatorFactory::getPopulator($hi->table_name);
			$new_hi = $populator->populate($hi);

			if ($hi->children) {
			    foreach ($hi->children as $child_hi) {
					
			    	$populator = HistoryPopulatorFactory::getPopulator($child_hi->table_name);
			    	$new_child_hi = $populator->populate($child_hi);
			    	
			    }
			}
		}
		
		return $historia;
	}

}
