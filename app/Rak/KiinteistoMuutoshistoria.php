<?php

namespace App\Rak;

use App\Library\History\HistoryPopulatorFactory;
use App\Muutoshistoria;

class KiinteistoMuutoshistoria extends Muutoshistoria
{
	protected static $entiteetti_taulu = "kiinteisto";

	// returns the query
	private static function getEntriesById($id) {
		return parent::getHistoryById(self::$entiteetti_taulu, $id);
	}

	private static function getKuvaEntries($id) {
		return parent::getHistoryByKeyAndId('kuva_kiinteisto', 'kiinteisto_id', $id);
	}

	private static function getTiedostoEntries($id) {
		return parent::getHistoryByKeyAndId('tiedosto_kiinteisto', 'kiinteisto_id', $id);
	}

	private static function getRakennusEntries($id) {
		return parent::getHistoryByKeyAndIdAndAction('rakennus', 'kiinteisto_id', $id, 'I');
	}

	private static function getDeletedKuvaEntries($id) {
		return parent::getDeletionHistory('kuva_kiinteisto', 'kiinteisto_id', $id, 'poistaja');
	}

	private static function getDeletedTiedostoEntries($id) {
		return parent::getDeletionHistory('tiedosto_kiinteisto', 'kiinteisto_id', $id, 'poistaja');
	}

	private static function getDeletedRakennusEntries($id) {
		return parent::getDeletionHistory('rakennus', 'kiinteisto_id', $id, 'poistaja');
	}


	private static function getMovedFromRakennusEntries($id) {
		return parent::getMovedRakennusEntries('kiinteisto_id', $id, 'from');
	}

	private static function getMovedToRakennusEntries($id) {
		return parent::getMovedRakennusEntries('kiinteisto_id', $id, 'to');
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

		// lisätyt rakennukset historia
		$r_hst = self::getRakennusEntries($id);
		foreach($r_hst as $h) {
			$historia[] = $h;
		}

		// poistetut rakennukset, kuvat, tiedostot

		$d_r_hst = self::getDeletedRakennusEntries($id);
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

		$m_r_hst = self::getMovedFromRakennusEntries($id);
		foreach($m_r_hst as $h) {
			$historia[] = $h;
		}

		$m_r_hst = self::getMovedToRakennusEntries($id);
		foreach($m_r_hst as $h) {
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
