<?php

namespace App\Library\History;
use App\Rak\Kayttotarkoitus;

class RakennusKayttotarkoitusHistoryPopulator {

	public function populate($hi_entry) {

		// Make a copy of arrays, otherwise we get exception when modifying them
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;

		if (array_key_exists("kayttotarkoitus_id", $row_data)) {
			$row_data["kayttotarkoitus"] = Kayttotarkoitus::getSingle($row_data["kayttotarkoitus_id"])->withTrashed()->first();
		}

		if (!is_null($changed_fields) && array_key_exists("kayttotarkoitus_id", $changed_fields)) {
			$changed_fields["kayttotarkoitus"] = Kayttotarkoitus::getSingle($changed_fields["kayttotarkoitus_id"])->withTrashed()->first();
		}

		// now write back those arrays
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;

		return $hi_entry;
	}

}