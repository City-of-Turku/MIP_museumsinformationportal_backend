<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use App\Library\History\HistoryPopulatorFactory;
use App\Muutoshistoria;

class RakennusMuutoshistoria extends Muutoshistoria
{
	protected static $entiteetti_taulu = "rakennus";
	
	// returns the query
	private static function getEntriesById($id) {
		return parent::getHistoryById(self::$entiteetti_taulu, $id);
	}
	
	private static function getKuvaEntries($id) {
		return parent::getHistoryByKeyAndId('kuva_rakennus', 'rakennus_id', $id);
	}

	private static function getTiedostoEntries($id) {
		return parent::getHistoryByKeyAndId('tiedosto_rakennus', 'rakennus_id', $id);
	}
	
	private static function getPorrashuoneEntries($id) {
		return parent::getHistoryByKeyAndIdAndAction('porrashuone', 'rakennus_id', $id, 'I');
	}
	
	private static function getDeletedKuvaEntries($id) {
		return parent::getDeletionHistory('kuva_rakennus', 'rakennus_id', $id, 'poistaja');
	}
	
	private static function getDeletedTiedostoEntries($id) {
		return parent::getDeletionHistory('tiedosto_rakennus', 'rakennus_id', $id, 'poistaja');
	}
	
	private static function getDeletedPorrashuoneEntries($id) {
		return parent::getDeletionHistory('porrashuone', 'rakennus_id', $id, 'poistaja');
	}
	
	private static function getSuunnittelijaEntries($id) {
		return parent::getHistoryByKeyAndIdAndAction('suunnittelija_rakennus', 'rakennus_id', $id, 'I');
	}
	
	private static function getDeletedSuunnittelijaEntries($id) {			
		return parent::getDeletionHistory('suunnittelija_rakennus', 'rakennus_id', $id, 'poistaja');
	}
	
	// TODO... the rest of stuff
	
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
		
		// lisätyt porrashuoneet historia
		$r_hst = self::getPorrashuoneEntries($id);
		foreach($r_hst as $h) {
			$historia[] = $h;
		}
		
		// lisätyt suunnittelijat historia
		$r_hst = self::getSuunnittelijaEntries($id);
		foreach($r_hst as $h) {
			$historia[] = $h;			
		}
		
		// poistot
		
		$d_hst = self::getDeletedKuvaEntries($id);
		foreach($d_hst as $h) {
			$historia[] = $h;
		}
		$d_hst = self::getDeletedTiedostoEntries($id);
		foreach($d_hst as $h) {
			$historia[] = $h;
		}
		$d_hst = self::getDeletedPorrashuoneEntries($id);
		foreach($d_hst as $h) {
			$historia[] = $h;
		}
		$d_hst = self::getDeletedSuunnittelijaEntries($id);
		foreach($d_hst as $h) {
			$historia[] = $h;
		}
		
		// populate each and children
		foreach ($historia as $hi) {
		
			$populator = HistoryPopulatorFactory::getPopulator($hi->table_name);
			$new_hi = $populator->populate($hi);
		
			// children is the other things done in same transaction
			// = inserts/updates/deletes to related tables
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
