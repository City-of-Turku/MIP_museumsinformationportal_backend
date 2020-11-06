<?php
namespace App\Library\History;

use App\Ark\Tyyppi;
use App\Ark\Tyyppitarkenne;

/**
 * Kohteen tyyppi tietojen muutoshistorian haku
 */
class ArkKohdeTyyppiHistoryPopulator{

    public function populate($hi_entry) {
        
        $row_data = $hi_entry->row_data;
        $changed_fields = $hi_entry->changed_fields;

        // Kohteen tyyppi
        if (array_key_exists("tyyppi_id", $row_data)) {
            $row_data["kohde_tyyppi"] = Tyyppi::getSingle($row_data["tyyppi_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("tyyppi_id", $changed_fields)) {
            $changed_fields["kohde_tyyppi"] = Tyyppi::getSingle($changed_fields["tyyppi_id"])->withTrashed()->first();
        }

        // Kohteen tyypin tarkenne
        if (array_key_exists("tyyppitarkenne_id", $row_data)) {
            $row_data["kohde_tyyppitarkenne"] = Tyyppitarkenne::getSingle($row_data["tyyppitarkenne_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("tyyppitarkenne_id", $changed_fields)) {
            $changed_fields["kohde_tyyppitarkenne"] = Tyyppitarkenne::getSingle($changed_fields["tyyppitarkenne_id"])->withTrashed()->first();
        }
        
        $hi_entry->row_data = $row_data;
        $hi_entry->changed_fields = $changed_fields;

        return $hi_entry;
    }

}

