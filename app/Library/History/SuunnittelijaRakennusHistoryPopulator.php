<?php
namespace App\Library\History;
use App\Rak\Suunnittelija;
use App\Rak\SuunnittelijaTyyppi;

class SuunnittelijaRakennusHistoryPopulator {

	public function populate($hi_entry) {

		// Make a copy of arrays, otherwise we get exception when modifying them
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;

		if (array_key_exists("suunnittelija_id", $row_data)) {
			$row_data["suunnittelija"] = Suunnittelija::getSingle($row_data["suunnittelija_id"])->withTrashed()->first();
			$row_data["suunnittelijatyyppi"] = SuunnittelijaTyyppi::getSingle($row_data["suunnittelija_tyyppi_id"])->first();
		}

		if (!is_null($changed_fields) && array_key_exists("suunnittelija_id", $changed_fields)) {
			$changed_fields["suunnittelija"] = Suunnittelija::getSingle($changed_fields["suunnittelija_id"])->withTrashed()->first();
			$row_data["suunnittelijatyyppi"] = SuunnittelijaTyyppi::getSingle($row_data["suunnittelija_tyyppi_id"])->first();
		}

		// now write back those arrays
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;

		return $hi_entry;
	}

}