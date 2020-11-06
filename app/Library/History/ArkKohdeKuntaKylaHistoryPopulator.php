<?php
namespace App\Library\History;

use App\Kunta;
use App\Kyla;

/**
 * Kohteen kunta/kylä tietojen muutoshistorian haku
 */
class ArkKohdeKuntaKylaHistoryPopulator{

    public function populate($hi_entry) {
        
        $row_data = $hi_entry->row_data;
        $changed_fields = $hi_entry->changed_fields;
        
        // Kunta
        if (array_key_exists("kunta_id", $row_data)) {
            $row_data["kohde_kunta"] = Kunta::getSingle($row_data["kunta_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("kunta_id", $changed_fields)) {
            $changed_fields["kohde_kunta"] = Kunta::getSingle($changed_fields["kunta_id"])->withTrashed()->first();
        }

        // Kylä
        if (array_key_exists("kyla_id", $row_data)) {
            $row_data["kohde_kyla"] = Kyla::getSingle($row_data["kyla_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("kyla_id", $changed_fields)) {
            $changed_fields["kohde_kyla"] = Kyla::getSingle($changed_fields["kyla_id"])->withTrashed()->first();
        }
        
        $hi_entry->row_data = $row_data;
        $hi_entry->changed_fields = $changed_fields;

        return $hi_entry;
    }

}

