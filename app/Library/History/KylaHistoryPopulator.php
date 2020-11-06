<?php
namespace App\Library\History;

use App\Kunta;

class KylaHistoryPopulator {
	
	public function populate($hi_entry) {
		
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;
		
		// lisää kunta id:n perusteella
		if (array_key_exists("kunta_id", $row_data)) {
			$row_data["kunta"] = Kunta::getSingle($row_data["kunta_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("kunta_id", $changed_fields)) {
			$changed_fields["kunta"] = Kunta::getSingle($changed_fields["kunta_id"])->withTrashed()->first();
		}
				
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;
		
		return $hi_entry;
	}
}