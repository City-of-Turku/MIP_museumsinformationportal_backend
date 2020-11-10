<?php
namespace App\Library\History;

use App\Rak\SuunnittelijaAmmattiarvo;
use App\Rak\SuunnittelijaLaji;

class SuunnittelijaHistoryPopulator {
	
	public function populate($hi_entry) {
		
		$row_data = $hi_entry->row_data;
		$changed_fields = $hi_entry->changed_fields;
		
		// lisää laji id:n perusteella
		if (array_key_exists("suunnittelija_laji_id", $row_data)) {
			$row_data["suunnittelija_laji"] = SuunnittelijaLaji::getSingle($row_data["suunnittelija_laji_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("suunnittelija_laji_id", $changed_fields)) {
			$changed_fields["suunnittelija_laji"] = SuunnittelijaLaji::getSingle($changed_fields["suunnittelija_laji_id"])->withTrashed()->first();
		}
		
		// lisää ammattiarvo id:n perusteella
		if (array_key_exists("suunnittelija_ammattiarvo_id", $row_data)) {
			$row_data["suunnittelija_ammattiarvo"] = SuunnittelijaAmmattiarvo::getSingle($row_data["suunnittelija_ammattiarvo_id"])->withTrashed()->first();
		}
		
		if (!is_null($changed_fields) && array_key_exists("suunnittelija_ammattiarvo_id", $changed_fields)) {
			$changed_fields["suunnittelija_ammattiarvo"] = SuunnittelijaAmmattiarvo::getSingle($changed_fields["suunnittelija_ammattiarvo_id"])->withTrashed()->first();
		}
				
		$hi_entry->row_data = $row_data;
		$hi_entry->changed_fields = $changed_fields;
		
		return $hi_entry;
	}
}