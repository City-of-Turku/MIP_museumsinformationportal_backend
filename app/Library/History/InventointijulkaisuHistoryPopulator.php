<?php
namespace App\Library\History;

use App\Rak\InventointiprojektiTyyppi;

class InventointijulkaisuHistoryPopulator {
	
	public function populate($hi_entry) {
		
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;
		
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;
		
		return $hi_entry;
	}
}