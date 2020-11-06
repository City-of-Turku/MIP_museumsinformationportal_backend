<?php
namespace App\Library\History;
use App\Rak\Arvotustyyppi;
use App\Rak\Kuntotyyppi;
use App\Rak\Tyylisuunta;
use App\Rak\Suunnittelija;
use App\Rak\Kiinteisto;

class RakennusHistoryPopulator extends HistoryPopulatorBase {

	public function populate($hi_entry) {

		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;

		// kunto
		if (array_key_exists("kuntotyyppi_id", $row_data)) {
			$row_data["kuntotyyppi"] = Kuntotyyppi::getSingle($row_data["kuntotyyppi_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("kuntotyyppi_id", $changed_fields)) {
			$changed_fields["kuntotyyppi"] = Kuntotyyppi::getSingle($changed_fields["kuntotyyppi_id"])->withTrashed()->first();
		}
		
		// nykytyyli
		if (array_key_exists("nykyinen_tyyli_id", $row_data)) {
			$row_data["nykyinentyyli"] = Tyylisuunta::getSingle($row_data["nykyinen_tyyli_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("nykyinen_tyyli_id", $changed_fields)) {
			$changed_fields["nykyinentyyli"] = Tyylisuunta::getSingle($changed_fields["nykyinen_tyyli_id"])->withTrashed()->first();
		}
		
		// arvoluokka ( = arvotustyyppi )
		if (array_key_exists("arvotustyyppi_id", $row_data)) {
			$row_data["arvotustyyppi"] = Arvotustyyppi::getSingle($row_data["arvotustyyppi_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("arvotustyyppi_id", $changed_fields)) {
			$changed_fields["arvotustyyppi"] = Arvotustyyppi::getSingle($changed_fields["arvotustyyppi_id"])->withTrashed()->first();
		}
		
		// Kiinteisto 
		if (array_key_exists("kiinteisto_id", $row_data)) {
			$row_data["kiinteisto"] = Kiinteisto::getSingle($row_data["kiinteisto_id"])->withTrashed()->first();
		}
		
		if(!is_null($changed_fields) && array_key_exists("kiinteisto_id", $changed_fields)) {
			$changed_fields["kiinteisto"] = Kiinteisto::getSingle($changed_fields["kiinteisto_id"])->withTrashed()->first();
		}
		
		// sijainti
		if (array_key_exists("rakennuksen_sijainti", $row_data)) {
			$row_data["rakennuksen_sijainti"] = parent::getGeometryAsText($row_data["rakennuksen_sijainti"]);
		}
		if (!is_null($changed_fields) && array_key_exists("rakennuksen_sijainti", $changed_fields)) {
			$changed_fields["rakennuksen_sijainti"] = parent::getGeometryAsText($changed_fields["rakennuksen_sijainti"]);
		}
			
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;

		return $hi_entry;
	}
}