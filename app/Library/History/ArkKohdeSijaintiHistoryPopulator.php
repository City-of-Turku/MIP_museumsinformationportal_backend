<?php
namespace App\Library\History;


/**
 * Kohteen sijainti tietojen muutoshistorian haku
 */
class ArkKohdeSijaintiHistoryPopulator extends HistoryPopulatorBase{

    public function populate($hi_entry) {
        
        $row_data = $hi_entry->row_data;
        $changed_fields = $hi_entry->changed_fields;
        
        // Sijainti
        if (array_key_exists("sijainti", $row_data)) {
            $row_data["kohde_sijainti"] = parent::getGeometryAsText($row_data["sijainti"]);
        }
        if (!is_null($changed_fields) && array_key_exists("sijainti", $changed_fields)) {
            $changed_fields["kohde_sijainti"] = parent::getGeometryAsText($changed_fields["sijainti"]);
        }
        
        $hi_entry->row_data = $row_data;
        $hi_entry->changed_fields = $changed_fields;

        return $hi_entry;
    }

}

