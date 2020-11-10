<?php
namespace App\Library\History;

use App\Rak\Porrashuonetyyppi;

class PorrashuoneHistoryPopulator {
	
	public function populate($hi_entry) {
		
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;
		
		// lisää kylä id:n perusteella
		if (array_key_exists("porrashuonetyyppi_id", $row_data)) {
			$row_data["porrashuonetyyppi"] = Porrashuonetyyppi::getSingle($row_data["porrashuonetyyppi_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("porrashuonetyyppi_id", $changed_fields)) {
			$changed_fields["porrashuonetyyppi"] = Porrashuonetyyppi::getSingle($changed_fields["porrashuonetyyppi_id"])->withTrashed()->first();
		}
				
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;
		
		return $hi_entry;
	}
}