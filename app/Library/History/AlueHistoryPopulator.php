<?php
namespace App\Library\History;

use App\Kyla;

class AlueHistoryPopulator extends HistoryPopulatorBase {
	
	public function populate($hi_entry) {
		
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;
		
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
				
				if ($chi["table_name"]=='alue_kyla') {
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