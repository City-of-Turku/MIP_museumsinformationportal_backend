<?php
namespace App\Library\History;
use App\Kunta;

class InventointiprojektiKuntaHistoryPopulator {
	
	public function populate($hi_entry) {
		
		// Make a copy of arrays, otherwise we get exception when modifying them
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;
		
		if (array_key_exists("kunta_id", $row_data)) {
			$row_data["kunta"] = Kunta::getSingle($row_data["kunta_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("kunta_id", $changed_fields)) {
			$changed_fields["kunta"] = Kunta::getSingle($changed_fields["kunta_id"])->withTrashed()->first();
		}
		
		// now write back those arrays
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;
		
		return $hi_entry;
	}
	
}