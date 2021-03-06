<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use App\Library\History\HistoryPopulatorFactory;
use App\Muutoshistoria;

class InventointijulkaisuMuutoshistoria extends Muutoshistoria
{
	protected static $entiteetti_taulu = "inventointijulkaisu";
	
	// returns the query
	private static function getEntriesById($id) {
		return parent::getHistoryById(self::$entiteetti_taulu, $id);
	}
	
	public static function getById($id) {
		
		// oma historia
		$historia = self::getEntriesById($id);
			
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
