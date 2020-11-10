<?php

namespace App\Rak;

use App\Library\History\HistoryPopulatorFactory;
use App\Muutoshistoria;

class PorrashuoneMuutoshistoria extends Muutoshistoria
{
	protected static $entiteetti_taulu = "porrashuone";
	
	// returns the query
	private static function getEntriesById($id) {
		return parent::getHistoryById(self::$entiteetti_taulu, $id);
	}
	
	private static function getKuvaEntries($id) {
		return parent::getHistoryByKeyAndId('kuva_porrashuone', 'porrashuone_id', $id);
	}

	private static function getTiedostoEntries($id) {
		return parent::getHistoryByKeyAndId('tiedosto_porrashuone', 'porrashuone_id', $id);
	}
	
	// TODO... the rest of stuff
	
	public static function getById($id) {
		
		// oma historia
		$historia = self::getEntriesById($id);
		
		// TODO:
		// suunnittelijat
	
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
		
		// populate each and children
		foreach ($historia as $hi) {
		
			$populator = HistoryPopulatorFactory::getPopulator($hi->table_name);
			$new_hi = $populator->populate($hi);
		
			// children is the other things done in same transaction
			// = inserts/updates/deletes to related tables
		
			foreach ($hi->children as $child_hi) {
		
				$populator = HistoryPopulatorFactory::getPopulator($child_hi->table_name);
				$new_child_hi = $populator->populate($child_hi);
				 
			}
		
		}
		
		return $historia;
	}

}
