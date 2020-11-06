<?php
namespace App\Library\History;

use App\Ark\Kohde;

/**
 * Tutkimuksen kohde tietojen muutoshistorian haku
 */
class ArkTutkimusKohdeHistoryPopulator{

    public function populate($hi_entry) {
        
        $row_data = $hi_entry->row_data;
        $changed_fields = $hi_entry->changed_fields;
        
        // Tutkimuksen kohde
        if (array_key_exists("ark_kohde_id", $row_data)) {
            $row_data["tutkimuksen_kohde"] = Kohde::getSingle($row_data["ark_kohde_id"])
            ->with( array(
                'kunnatkylat.kunta',
                'kunnatkylat.kyla'
            ))->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ark_kohde_id", $changed_fields)) {
            $changed_fields["tutkimuksen_kohde"] = Kohde::getSingle($changed_fields["ark_kohde_id"])->withTrashed()->first();
        }
        
        $hi_entry->row_data = $row_data;
        $hi_entry->changed_fields = $changed_fields;

        return $hi_entry;
    }

}

