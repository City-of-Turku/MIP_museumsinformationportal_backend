<?php
namespace App\Library\History;

use App\Rak\InventointiprojektiTyyppi;

class InventointiprojektiHistoryPopulator {
	
	public function populate($hi_entry) {
		
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;
		
		// lisää tyyppi id:n perusteella
		if (array_key_exists("tyyppi_id", $row_data)) {
			$row_data["tyyppi"] = InventointiprojektiTyyppi::getSingle($row_data["tyyppi_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("tyyppi_id", $changed_fields)) {
			$changed_fields["tyyppi"] = InventointiprojektiTyyppi::getSingle($changed_fields["tyyppi_id"])->withTrashed()->first();
		}
				
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;
		
		return $hi_entry;
	}
}