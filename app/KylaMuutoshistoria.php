<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Library\History\HistoryPopulatorFactory;

class KylaMuutoshistoria extends Muutoshistoria
{
	protected static $entiteetti_taulu = "kyla";
	
	// returns the query
	private static function getEntriesById($id) {
		return parent::getHistoryById(self::$entiteetti_taulu, $id);
	}
	
	private static function getKuvaEntries($id) {
		return parent::getHistoryByKeyAndId('kuva_kyla', 'kyla_id', $id);
	}

	public static function getById($id) {
		
		// oma historia
		$historia = self::getEntriesById($id);
		
		// liitetyt kuvat historia
		$kuva_hst = self::getKuvaEntries($id);
		foreach($kuva_hst as $h) {
			
			$populator = HistoryPopulatorFactory::getPopulator($h->table_name);
			$new_h = $populator->populate($h);
			$historia[] = $h;
		}
						
		foreach ($historia as $hi) {
		
			$populator = HistoryPopulatorFactory::getPopulator($hi->table_name);
			$new_hi = $populator->populate($hi);
				
			// children is the other things done in same transaction
			// = inserts/updates/deletes to related tables such as kiinteisto_suojelutieto, kiinteisto_aluetyyppi etc

		    foreach ($hi->children as $child_hi) {
				
		    	$populator = HistoryPopulatorFactory::getPopulator($child_hi->table_name);
		    	$new_child_hi = $populator->populate($child_hi);
		    	
		    }
		
		}
		
		return $historia;
	}

}
