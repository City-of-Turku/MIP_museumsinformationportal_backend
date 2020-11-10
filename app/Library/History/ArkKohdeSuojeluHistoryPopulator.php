<?php
namespace App\Library\History;
use App\Rak\Suojelutyyppi;

/**
 * Kohteen suojelu tietojen muutoshistorian haku
 */
class ArkKohdeSuojeluHistoryPopulator extends HistoryPopulatorBase{

    public function populate($hi_entry) {
        
        $row_data = $hi_entry->row_data;
        $changed_fields = $hi_entry->changed_fields;
        
        // Suojelutieto
        if (array_key_exists("suojelutyyppi_id", $row_data)) {
            $row_data["kohde_suojelutyyppi"] = Suojelutyyppi::getSingle($row_data["suojelutyyppi_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("suojelutyyppi_id", $changed_fields)) {
            $changed_fields["kohde_suojelutyyppi"] = Suojelutyyppi::getSingle($changed_fields["suojelutyyppi_id"])->withTrashed()->first();
        }
        
        
        $hi_entry->row_data = $row_data;
        $hi_entry->changed_fields = $changed_fields;

        return $hi_entry;
    }

}

