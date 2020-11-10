<?php
namespace App\Library\History;
use App\Rak\Kuva;

class KuvaHistoryPopulator {

	public function populate($hi_entry) {

		// Make a copy of arrays, otherwise we get exception when modifying them
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;

		if (array_key_exists("kuva_id", $row_data)) {
			$row_data["kuva"] = Kuva::getSingle($row_data["kuva_id"])->withTrashed()->first();
		}

		if (!is_null($changed_fields) && array_key_exists("kuva_id", $changed_fields)) {
			$changed_fields["kuva"] = Kuva::getSingle($changed_fields["kuva_id"])->withTrashed()->first();
		}

		// now write back those arrays
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;

		return $hi_entry;
	}

}