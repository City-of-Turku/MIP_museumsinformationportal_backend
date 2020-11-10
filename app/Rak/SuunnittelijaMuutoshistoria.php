<?php

namespace App\Rak;

use App\Muutoshistoria;
use App\Library\History\HistoryPopulatorFactory;

class SuunnittelijaMuutoshistoria extends Muutoshistoria
{
	protected static $entiteetti_taulu = "suunnittelija";

	// returns the query
	private static function getEntriesById($id) {
		return parent::getHistoryById(self::$entiteetti_taulu, $id);
	}

	private static function getKuvaEntries($id) {
	    return parent::getHistoryByKeyAndId('kuva_suunnittelija', 'suunnittelija_id', $id);
	}

	private static function getTiedostoEntries($id) {
	    return parent::getHistoryByKeyAndId('tiedosto_suunnittelija', 'suunnittelija_id', $id);
	}

	private static function getDeletedKuvaEntries($id) {
	    return parent::getDeletionHistory('kuva_suunnittelija', 'suunnittelija_id', $id, 'poistaja');
	}

	private static function getDeletedTiedostoEntries($id) {
	    return parent::getDeletionHistory('tiedosto_suunnittelija', 'suunnittelija_id', $id, 'poistaja');
	}

	public static function getById($id) {

		// oma historia
		$historia = self::getEntriesById($id);

		// liitetyt kuvat historia
		$suunnittelija_hst = self::getKuvaEntries($id);
		foreach($suunnittelija_hst as $h) {
		    $historia[] = $h;
		}

		// liitetiedostot historia
		$t_hst = self::getTiedostoEntries($id);
		foreach($t_hst as $h) {
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

			// children is the other things done in same transaction
			// = inserts/updates/deletes to related tables such as kiinteisto_suojelutieto, kiinteisto_aluetyyppi etc

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
