<?php
namespace App\Library\History;
use App\Rak\Perustustyyppi;

class RakennusPerustustyyppiHistoryPopulator {

	public function populate($hi_entry) {

		// Make a copy of arrays, otherwise we get exception when modifying them
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;

		if (array_key_exists("perustustyyppi_id", $row_data)) {
			$row_data["perustustyyppi"] = Perustustyyppi::getSingle($row_data["perustustyyppi_id"])->withTrashed()->first();
		}

		if (!is_null($changed_fields) && array_key_exists("perustustyyppi_id", $changed_fields)) {
			$changed_fields["perustustyyppi"] = Perustustyyppi::getSingle($changed_fields["perustustyyppi_id"])->withTrashed()->first();
		}

		// now write back those arrays
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;

		return $hi_entry;
	}

}