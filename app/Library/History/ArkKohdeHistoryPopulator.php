<?php
namespace App\Library\History;

use App\Ark\Kohdelaji;
use App\Ark\Rauhoitusluokka;
use App\Ark\Alkuperaisyys;
use App\Ark\Kunto;
use App\Ark\Hoitotarve;
use App\Ark\Rajaustarkkuus;
use App\Ark\Maastomerkinta;

/**
 * Kohteen valintalistatietojen muutoshistorian haku
 */
class ArkKohdeHistoryPopulator{

    public function populate($hi_entry) {
        
        $row_data = $hi_entry->row_data;
        $changed_fields = $hi_entry->changed_fields;
        
        // Kohdelaji
        if (array_key_exists("ark_kohdelaji_id", $row_data)) {
            $row_data["kohdelaji"] = Kohdelaji::getSingle($row_data["ark_kohdelaji_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ark_kohdelaji_id", $changed_fields)) {
            $changed_fields["kohdelaji"] = Kohdelaji::getSingle($changed_fields["ark_kohdelaji_id"])->withTrashed()->first();
        }
        
        // Rauhoitusluokka
        if (array_key_exists("rauhoitusluokka_id", $row_data)) {
            $row_data["rauhoitusluokka"] = Rauhoitusluokka::getSingle($row_data["rauhoitusluokka_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("rauhoitusluokka_id", $changed_fields)) {
            $changed_fields["rauhoitusluokka"] = Rauhoitusluokka::getSingle($changed_fields["rauhoitusluokka_id"])->withTrashed()->first();
        }
        
        // Alkuperäisyys
        if (array_key_exists("alkuperaisyys_id", $row_data)) {
            $row_data["alkuperaisyys"] = Alkuperaisyys::getSingle($row_data["alkuperaisyys_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("alkuperaisyys_id", $changed_fields)) {
            $changed_fields["alkuperaisyys"] = Alkuperaisyys::getSingle($changed_fields["alkuperaisyys_id"])->withTrashed()->first();
        }
        
        // Kunto
        if (array_key_exists("kunto_id", $row_data)) {
            $row_data["kunto"] = Kunto::getSingle($row_data["kunto_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("kunto_id", $changed_fields)) {
            $changed_fields["kunto"] = Kunto::getSingle($changed_fields["kunto_id"])->withTrashed()->first();
        }
        
        // Hoitotarve
        if (array_key_exists("kunto_id", $row_data)) {
            $row_data["hoitotarve"] = Hoitotarve::getSingle($row_data["hoitotarve_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("kunto_id", $changed_fields)) {
            $changed_fields["hoitotarve"] = Hoitotarve::getSingle($changed_fields["hoitotarve_id"])->withTrashed()->first();
        }
        
        // Rajaustarkkuus
        if (array_key_exists("rajaustarkkuus_id", $row_data)) {
            $row_data["rajaustarkkuus"] = Rajaustarkkuus::getSingle($row_data["rajaustarkkuus_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("rajaustarkkuus_id", $changed_fields)) {
            $changed_fields["rajaustarkkuus"] = Rajaustarkkuus::getSingle($changed_fields["rajaustarkkuus_id"])->withTrashed()->first();
        }
        
        // Maastomerkintä
        if (array_key_exists("maastomerkinta_id", $row_data)) {
            $row_data["maastomerkinta"] = Maastomerkinta::getSingle($row_data["maastomerkinta_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("maastomerkinta_id", $changed_fields)) {
            $changed_fields["maastomerkinta"] = Maastomerkinta::getSingle($changed_fields["maastomerkinta_id"])->withTrashed()->first();
        }
        
        // Tuhoutumissyy
        if (array_key_exists("tuhoutumissyy_id", $row_data)) {
            $row_data["tuhoutumissyy"] = Maastomerkinta::getSingle($row_data["tuhoutumissyy_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("tuhoutumissyy_id", $changed_fields)) {
            $changed_fields["tuhoutumissyy"] = Maastomerkinta::getSingle($changed_fields["tuhoutumissyy_id"])->withTrashed()->first();
        }
        
        $hi_entry->row_data = $row_data;
        $hi_entry->changed_fields = $changed_fields;
        
        return $hi_entry;
    }

}

