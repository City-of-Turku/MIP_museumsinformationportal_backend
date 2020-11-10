<?php
namespace App\Library\History;
use App\Entiteettityyppi;

class InventointijulkaisuTasoHistoryPopulator {
	
	public function populate($hi_entry) {
		
		// Make a copy of arrays, otherwise we get exception when modifying them
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;
		
		if (array_key_exists("entiteetti_tyyppi_id", $row_data)) {
			$row_data["taso"] = Entiteettityyppi::getSingle($row_data["entiteetti_tyyppi_id"])->first(); //ei withTrashed() koska ei ole softDeleteä
		}
		
		if (!is_null($changed_fields) && array_key_exists("entiteetti_tyyppi_id", $changed_fields)) {
			$changed_fields["taso"] = Entiteettityyppi::getSingle($changed_fields["inventointiprojekti_id"])->first(); //ei withTrashed() koska ei ole softDeleteä
		}
		
		// now write back those arrays
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;
		
		return $hi_entry;
	}
	
}