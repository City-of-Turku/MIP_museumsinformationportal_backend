<?php
namespace App\Library\History;

use App\Kyla;
use App\Rak\Aluetyyppi;
use App\Rak\Arvotustyyppi;
use App\Rak\Alue;

class ArvoalueHistoryPopulator extends HistoryPopulatorBase {
	
	public function populate($hi_entry) {
		
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;
		
		// lisää alue id:n perusteella
		if (array_key_exists("alue_id", $row_data)) {
			$row_data["alue"] = Alue::getSingle($row_data["alue_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("alue_id", $changed_fields)) {
			$changed_fields["alue"] = Aluetyyppi::getSingle($changed_fields["alue_id"])->withTrashed()->first();
		}
		
		// lisää aluetyyppi id:n perusteella
		if (array_key_exists("aluetyyppi_id", $row_data)) {
			$row_data["aluetyyppi"] = Aluetyyppi::getSingle($row_data["aluetyyppi_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("aluetyyppi_id", $changed_fields)) {
			$changed_fields["aluetyyppi"] = Aluetyyppi::getSingle($changed_fields["aluetyyppi_id"])->withTrashed()->first();
		}
		
		// arvoluokka ( = arvotustyyppi )
		if (array_key_exists("arvotustyyppi_id", $row_data)) {
			$row_data["arvotustyyppi"] = Arvotustyyppi::getSingle($row_data["arvotustyyppi_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("arvotustyyppi_id", $changed_fields)) {
			$changed_fields["arvotustyyppi"] = Arvotustyyppi::getSingle($changed_fields["arvotustyyppi_id"])->withTrashed()->first();
		}

		// sijainti - aluerajaus
		if (array_key_exists("aluerajaus", $row_data)) {
			$row_data["aluerajaus"] = parent::getGeometryAsText($row_data["aluerajaus"]);
		}
		if (!is_null($changed_fields) && array_key_exists("aluerajaus", $changed_fields)) {
			$changed_fields["aluerajaus"] = parent::getGeometryAsText($changed_fields["aluerajaus"]);
		}
		// sijainti - keskipiste
		if (array_key_exists("keskipiste", $row_data)) {
			$row_data["keskipiste"] = parent::getGeometryAsText($row_data["keskipiste"]);
		}
		if (!is_null($changed_fields) && array_key_exists("keskipiste", $changed_fields)) {
			$changed_fields["keskipiste"] = parent::getGeometryAsText($changed_fields["keskipiste"]);
		}
		
		if ($hi_entry->children) {
			
			foreach ($hi_entry->children as $chi) {
				
				
				
				if ($chi["table_name"]=='arvoalue_kyla') {
					// lisää kylä id:n perusteella
					$chi_row_data = $chi->row_data;
					$chi_changed_fields = $chi->changed_fields;
					
					if (array_key_exists("kyla_id", $chi_row_data)) {
						$chi_row_data["kyla"] = Kyla::getSingle($chi_row_data["kyla_id"])->with(array("kunta"=>function($query){$query->withTrashed();}))->withTrashed()->first();
					}
					
					if (!is_null($chi_changed_fields) && array_key_exists("kyla_id", $chi_changed_fields)) {
						$chi_changed_fields["kyla"] = Kyla::getSingle($chi_changed_fields["kyla_id"])->with(array("kunta"=>function($query){$query->withTrashed();}))->withTrashed()->first();
					}
					
					$chi->row_data = $chi_row_data;
					$chi->changed_fields = $chi_changed_fields;
				}
			}
		}
		
		
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;
		
		return $hi_entry;
	}
}