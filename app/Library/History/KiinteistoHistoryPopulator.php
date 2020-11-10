<?php
namespace App\Library\History;

use App\Kyla;
use App\Rak\Arvotustyyppi;

class KiinteistoHistoryPopulator extends HistoryPopulatorBase {
	
	public function populate($hi_entry) {
		
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;
		
		// lisää kylä id:n perusteella
		if (array_key_exists("kyla_id", $row_data)) {
			$row_data["kyla"] = Kyla::getSingle($row_data["kyla_id"])->with(array("kunta"=>function($query){$query->withTrashed();}))->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("kyla_id", $changed_fields)) {
			$changed_fields["kyla"] = Kyla::getSingle($changed_fields["kyla_id"])->with(array("kunta"=>function($query){$query->withTrashed();}))->withTrashed()->first();
		}
		
		// arvoluokka ( = arvotustyyppi )
		if (array_key_exists("arvotustyyppi_id", $row_data)) {
			$row_data["arvotustyyppi"] = Arvotustyyppi::getSingle($row_data["arvotustyyppi_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("arvotustyyppi_id", $changed_fields)) {
			$changed_fields["arvotustyyppi"] = Arvotustyyppi::getSingle($changed_fields["arvotustyyppi_id"])->withTrashed()->first();
		}
		
		// sijainti
		if (array_key_exists("kiinteiston_sijainti", $row_data)) {
			$row_data["kiinteiston_sijainti"] = parent::getGeometryAsText($row_data["kiinteiston_sijainti"]);
		}		
		if (!is_null($changed_fields) && array_key_exists("kiinteiston_sijainti", $changed_fields)) {
			$changed_fields["kiinteiston_sijainti"] = parent::getGeometryAsText($changed_fields["kiinteiston_sijainti"]);
		}
		
		
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;
		
		return $hi_entry;
	}
}