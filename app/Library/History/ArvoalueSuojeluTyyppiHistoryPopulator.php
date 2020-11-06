<?php
namespace App\Library\History;
use App\Rak\Suojelutyyppi;

class ArvoalueSuojeluTyyppiHistoryPopulator {

	public function populate($hi_entry) {

		// Make a copy of arrays, otherwise we get exception when modifying them
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;

		if (array_key_exists("suojelutyyppi_id", $row_data)) {
			$row_data["suojelutyyppi"] = Suojelutyyppi::getSingle($row_data["suojelutyyppi_id"])->with(array("suojelutyyppiryhma"=>function($query){$query->withTrashed();}))->withTrashed()->first();
		}

		if (!is_null($changed_fields) && array_key_exists("suojelutyyppi_id", $changed_fields)) {
			$changed_fields["suojelutyyppi"] = Suojelutyyppi::getSingle($changed_fields["suojelutyyppi_id"])->with(array("suojelutyyppiryhma"=>function($query){$query->withTrashed();}))->withTrashed()->first();
		}

		// now write back those arrays
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;

		return $hi_entry;
	}

}
