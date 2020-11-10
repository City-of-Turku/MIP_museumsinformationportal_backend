<?php
namespace App\Library\History;
use App\Tiedosto;

class TiedostoHistoryPopulator {

	public function populate($hi_entry) {

		// Make a copy of arrays, otherwise we get exception when modifying them
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;

		if (array_key_exists("tiedosto_id", $row_data)) {
			$row_data["tiedosto"] = Tiedosto::getSingle($row_data["tiedosto_id"])->withTrashed()->first();
		}

		if (!is_null($changed_fields) && array_key_exists("tiedosto_id", $changed_fields)) {
			$changed_fields["tiedosto"] = Tiedosto::getSingle($changed_fields["tiedosto_id"])->withTrashed()->first();
		}

		// now write back those arrays
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;

		return $hi_entry;
	}

}