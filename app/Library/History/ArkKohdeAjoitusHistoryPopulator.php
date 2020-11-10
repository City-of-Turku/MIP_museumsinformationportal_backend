<?php
namespace App\Library\History;

use App\Ark\Ajoitus;
use App\Ark\Ajoitustarkenne;

/**
 * Kohteen ajoitus tietojen muutoshistorian haku
 */
class ArkKohdeAjoitusHistoryPopulator{

    public function populate($hi_entry) {
        
        $row_data = $hi_entry->row_data;
        $changed_fields = $hi_entry->changed_fields;

        // Kohteen ajoitus
        if (array_key_exists("ajoitus_id", $row_data)) {
            $row_data["kohde_ajoitus"] = Ajoitus::getSingle($row_data["ajoitus_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ajoitus_id", $changed_fields)) {
            $changed_fields["kohde_ajoitus"] = Ajoitus::getSingle($changed_fields["ajoitus_id"])->withTrashed()->first();
        }

        // Kohteen ajoituksen tarkenne
        if (array_key_exists("ajoitustarkenne_id", $row_data)) {
            $row_data["kohde_ajoitustarkenne"] = Ajoitustarkenne::getSingle($row_data["ajoitustarkenne_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ajoitustarkenne_id", $changed_fields)) {
            $changed_fields["kohde_ajoitustarkenne"] = Ajoitustarkenne::getSingle($changed_fields["ajoitustarkenne_id"])->withTrashed()->first();
        }
        
        $hi_entry->row_data = $row_data;
        $hi_entry->changed_fields = $changed_fields;

        return $hi_entry;
    }

}

