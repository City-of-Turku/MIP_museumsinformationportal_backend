<?php
namespace App\Ark;

use App\Library\History\HistoryPopulatorFactory;
use App\Muutoshistoria;

class TutkimusMuutoshistoria extends Muutoshistoria
{
    protected static $entiteetti_taulu = "ark_tutkimus";

    // Tutkimus
    private static function getEntriesById($id) {
        return parent::getHistoryById(self::$entiteetti_taulu, $id);
    }

    // Tutkimuksen kohde
    private static function getKohdeTutkimusEntries($id) {
        return parent::getHistoryByKeyAndIdAndAction('ark_kohde_tutkimus', 'ark_tutkimus_id', $id, 'I');
    }

    // Tarkastustutkimuksen tiedot
    private static function getTarkastusTiedot($id) {
        return parent::getHistoryByKeyAndId('ark_tarkastus', 'ark_tutkimus_id', $id);
    }


    public static function getById($id) {

        // oma historia
        $historia = self::getEntriesById($id);

        // lisätyt kohteet historia
        $r_hst = self::getKohdeTutkimusEntries($id);
        foreach($r_hst as $h) {
            $historia[] = $h;
        }

        // Tarkastustiedot
        $r_hst = self::getTarkastusTiedot($id);
        foreach($r_hst as $h) {
            $historia[] = $h;
        }

        foreach ($historia as $hi) {

            $populator = HistoryPopulatorFactory::getPopulator($hi->table_name);
            $new_hi = $populator->populate($hi);

            foreach ($hi->children as $child_hi) {

                $populator = HistoryPopulatorFactory::getPopulator($child_hi->table_name);
                $new_child_hi = $populator->populate($child_hi);

            }

        }

        return $historia;
    }

}

