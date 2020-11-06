<?php
namespace App\Library\History;
use App\Rak\MatkaraportinSyy;

class MatkaraporttiSyyHistoryPopulator {

	public function populate($hi_entry) {

		// Make a copy of arrays, otherwise we get exception when modifying them
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;

		if (array_key_exists("matkaraportinsyy_id", $row_data)) {
			$row_data["syy"] = MatkaraportinSyy::getSingle($row_data["matkaraportinsyy_id"])->withTrashed()->first();
		}

		if (!is_null($changed_fields) && array_key_exists("matkaraportinsyy_id", $changed_fields)) {
			$changed_fields["syy"] = MatkaraportinSyy::getSingle($changed_fields["matkaraportinsyy_id"])->withTrashed()->first();
		}

		// now write back those arrays
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;

		return $hi_entry;
	}

}