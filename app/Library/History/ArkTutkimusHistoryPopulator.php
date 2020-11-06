<?php
namespace App\Library\History;

use App\Ark\Kokoelmalaji;
use App\Ark\Tutkimuslaji;

/**
 * Tutkimuksen valintalistatietojen muutoshistorian haku
 */
class ArkTutkimusHistoryPopulator{

    public function populate($hi_entry) {
        
        $row_data = $hi_entry->row_data;
        $changed_fields = $hi_entry->changed_fields;
        
        // Tyyppi eli tutkimuslaji
        if (array_key_exists("ark_tutkimuslaji_id", $row_data)) {
            $row_data["tyyppi"] = Tutkimuslaji::getSingle($row_data["ark_tutkimuslaji_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ark_tutkimuslaji_id", $changed_fields)) {
            $changed_fields["tyyppi"] = Tutkimuslaji::getSingle($changed_fields["ark_tutkimuslaji_id"])->withTrashed()->first();
        }
        
        // Kokoelma- ja arkistotiedot
        if (array_key_exists("ark_loyto_kokoelmalaji_id", $row_data)) {
            $row_data["loyto_kokoelma"] = Tutkimuslaji::getSingle($row_data["ark_loyto_kokoelmalaji_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ark_loyto_kokoelmalaji_id", $changed_fields)) {
            $changed_fields["loyto_kokoelma"] = Kokoelmalaji::getSingle($changed_fields["ark_loyto_kokoelmalaji_id"])->withTrashed()->first();
        }
        
        if (array_key_exists("ark_raportti_kokoelmalaji_id", $row_data)) {
            $row_data["raportti_kokoelma"] = Kokoelmalaji::getSingle($row_data["ark_raportti_kokoelmalaji_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ark_raportti_kokoelmalaji_id", $changed_fields)) {
            $changed_fields["raportti_kokoelma"] = Kokoelmalaji::getSingle($changed_fields["ark_raportti_kokoelmalaji_id"])->withTrashed()->first();
        }
        
        if (array_key_exists("ark_kartta_kokoelmalaji_id", $row_data)) {
            $row_data["kartta_kokoelma"] = Kokoelmalaji::getSingle($row_data["ark_kartta_kokoelmalaji_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ark_kartta_kokoelmalaji_id", $changed_fields)) {
            $changed_fields["kartta_kokoelma"] = Kokoelmalaji::getSingle($changed_fields["ark_kartta_kokoelmalaji_id"])->withTrashed()->first();
        }
        
        if (array_key_exists("ark_valokuva_kokoelmalaji_id", $row_data)) {
            $row_data["valokuva_kokoelma"] = Kokoelmalaji::getSingle($row_data["ark_valokuva_kokoelmalaji_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ark_valokuva_kokoelmalaji_id", $changed_fields)) {
            $changed_fields["valokuva_kokoelma"] = Kokoelmalaji::getSingle($changed_fields["ark_valokuva_kokoelmalaji_id"])->withTrashed()->first();
        }
        
        if (array_key_exists("ark_nayte_kokoelmalaji_id", $row_data)) {
            $row_data["nayte_kokoelma"] = Kokoelmalaji::getSingle($row_data["ark_nayte_kokoelmalaji_id"])->withTrashed()->first();
        }
        
        if (!is_null($changed_fields) && array_key_exists("ark_nayte_kokoelmalaji_id", $changed_fields)) {
            $changed_fields["nayte_kokoelma"] = Kokoelmalaji::getSingle($changed_fields["ark_nayte_kokoelmalaji_id"])->withTrashed()->first();
        }
        
        $hi_entry->row_data = $row_data;
        $hi_entry->changed_fields = $changed_fields;

        return $hi_entry;
    }

}

