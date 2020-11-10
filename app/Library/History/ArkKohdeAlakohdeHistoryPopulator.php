<?php
namespace App\Library\History;
use App\Ark\Tyyppi;
use App\Ark\Tyyppitarkenne;

/**
 * Kohteen alakohde tietojen muutoshistorian haku
 */
class ArkKohdeAlakohdeHistoryPopulator extends HistoryPopulatorBase{

    public function populate($hi_entry) {
        
        $row_data = $hi_entry->row_data;
        $changed_fields = $hi_entry->changed_fields;
        
        // Alakohteen tyyppi
        if (array_key_exists("ark_kohdetyyppi_id", $row_data)) {
            $row_data["alakohde_tyyppi"] = Tyyppi::getSingle($row_data["ark_kohdetyyppi_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ark_kohdetyyppi_id", $changed_fields)) {
            $changed_fields["alakohde_tyyppi"] = Tyyppi::getSingle($changed_fields["ark_kohdetyyppi_id"])->withTrashed()->first();
        }
        
        // Alakohteen tyypin tarkenne
        if (array_key_exists("ark_kohdetyyppitarkenne_id", $row_data)) {
            $row_data["alakohde_tyyppitarkenne"] = Tyyppitarkenne::getSingle($row_data["ark_kohdetyyppitarkenne_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ark_kohdetyyppitarkenne_id", $changed_fields)) {
            $changed_fields["alakohde_tyyppitarkenne"] = Tyyppitarkenne::getSingle($changed_fields["ark_kohdetyyppitarkenne_id"])->withTrashed()->first();
        }
        
        $hi_entry->row_data = $row_data;
        $hi_entry->changed_fields = $changed_fields;

        return $hi_entry;
    }

}

