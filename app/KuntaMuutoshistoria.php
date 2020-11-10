<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Library\History\HistoryPopulatorFactory;

class KuntaMuutoshistoria extends Muutoshistoria
{
	protected static $entiteetti_taulu = "kunta";
	
	// returns the query
	private static function getEntriesById($id) {
		return parent::getHistoryById(self::$entiteetti_taulu, $id);
	}
	
	private static function getTiedostoEntries($id) {
		return parent::getHistoryByKeyAndId('tiedosto_kunta', 'kunta_id', $id);
	}

	public static function getById($id) {
		
		// oma historia
		$historia = self::getEntriesById($id);
		
		// liitetyt tiedostot historia
		$tiedosto_hst = self::getTiedostoEntries($id);
		foreach($tiedosto_hst as $h) {
			
			$populator = HistoryPopulatorFactory::getPopulator($h->table_name);
			$new_h = $populator->populate($h);
			$historia[] = $h;
		}
						
		foreach ($historia as $hi) {
		
			$populator = HistoryPopulatorFactory::getPopulator($hi->table_name);
			$new_hi = $populator->populate($hi);
				
		    foreach ($hi->children as $child_hi) {
				
		    	$populator = HistoryPopulatorFactory::getPopulator($child_hi->table_name);
		    	$new_child_hi = $populator->populate($child_hi);
		    	
		    }
		
		}
		
		return $historia;
	}

}
