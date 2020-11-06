<?php
namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use App\Library\History\HistoryPopulatorFactory;
use App\Muutoshistoria;

class KohdeMuutoshistoria extends Muutoshistoria
{
    protected static $entiteetti_taulu = "ark_kohde";
    
    // Kohteen tiedot ja referenssit mukana
    private static function getEntriesById($id) {
        return parent::getHistoryById(self::$entiteetti_taulu, $id);
    }
    
    public static function getById($id) {
        
        // oma historia
        $historia = self::getEntriesById($id);
        
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

