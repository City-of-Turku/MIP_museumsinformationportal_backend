<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


/**
 * Kohteen vanhat kunnat.
 */
class KohdeVanhaKunta extends Model
{
    protected $table = "ark_kohde_vanhakunta";
    protected $fillable = array('ark_kohde_id', 'kuntanimi');
    public $timestamps = false;
    
    
    public static function paivita_kohde_vanhatkunnat($kohde_id, $vanhat_kunnat) {
    	
    	// remove all existing selections ...
    	DB::table('ark_kohde_vanhakunta')->where('ark_kohde_id', $kohde_id)->delete();
    	
    	// ... and add new ones
    	if (!is_null($vanhat_kunnat)) {    		
    		foreach($vanhat_kunnat as $vk) {
    			$v = new KohdeVanhaKunta();
    			
    			$v->ark_kohde_id = $kohde_id;
    			
    			$v->kuntanimi = $vk['kuntanimi'];
    			$v->luoja = Auth::user()->id;
    			
    			$v->save();
    		}
    	}
    }
    /*
     * Tämä tarvitaan muutoshistorian tallennukseen. Jos modelilla ei ole ainoatakaan relaatiota määritelty, 
     * niin audit trigger ei tallenna tietoja saman transaction id:n alle.
     */ 
    public function kohde() {
        return $this->belongsTo('App\Ark\Kohde', 'id', 'kohde_id');
    }
    
}
