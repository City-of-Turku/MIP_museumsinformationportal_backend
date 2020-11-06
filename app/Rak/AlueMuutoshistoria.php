<?php

namespace App\Rak;

use App\Muutoshistoria;
use App\Library\History\HistoryPopulatorFactory;

class AlueMuutoshistoria extends Muutoshistoria
{
	protected static $entiteetti_taulu = "alue";

	// returns the query
	private static function getEntriesById($id) {
		return parent::getHistoryById(self::$entiteetti_taulu, $id);
	}

	private static function getKuvaEntries($id) {
		return parent::getHistoryByKeyAndId('kuva_alue', 'alue_id', $id);
	}

	private static function getTiedostoEntries($id) {
		return parent::getHistoryByKeyAndId('tiedosto_alue', 'alue_id', $id);
	}

	private static function getArvoalueEntries($id) {
		return parent::getHistoryByKeyAndIdAndAction('arvoalue', 'alue_id', $id, 'I');
	}

	private static function getDeletedKuvaEntries($id) {
		return parent::getDeletionHistory('kuva_alue', 'alue_id', $id, 'poistaja');
	}

	private static function getDeletedTiedostoEntries($id) {
		return parent::getDeletionHistory('tiedosto_alue', 'alue_id', $id, 'poistaja');
	}

	private static function getDeletedArvoalueEntries($id) {
		return parent::getDeletionHistory('arvoalue', 'alue_id', $id, 'poistaja');
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

		// lisätyt arvoalueet historia
		$r_hst = self::getArvoalueEntries($id);
		foreach($r_hst as $h) {
			$historia[] = $h;
		}

		// poistetut liittyvät tiedot

		$d_r_hst = self::getDeletedArvoalueEntries($id);
		foreach($d_r_hst as $h) {
			$historia[] = $h;
		}

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
