<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KiinteistoSuojelutyyppi extends Model
{
	use SoftDeletes;
	
    protected $table = "kiinteisto_suojelutyyppi";
    public $timestamps = true;
    
    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";
    
    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';
    
    protected $fillable = array(
    		'suojelutyyppi_id',
    		'kiinteisto_id',
    		'merkinta', 
    		'selite',
    		'id'
    		
    );
    
    public function suojelutyyppi() {
    	return $this->belongsTo('App\Rak\Suojelutyyppi');
    }
    

    /**
     * Update suojelutyypit of a kiinteisto
     *
     * @param $kiinteisto_id
     * @param $kiint_suojelutyypit
     */
    public static function update_kiinteisto_suojelutyypit($kiinteisto_id, $kiint_suojelutyypit) {
    	
    	if (!is_null($kiint_suojelutyypit)) {
	    	// remove all selections and add new ones
	    	DB::table('kiinteisto_suojelutyyppi')->where('kiinteisto_id', $kiinteisto_id)->delete();
	    	
	    	foreach($kiint_suojelutyypit as $kiint_suojelutyyppi) {
	    		
	    		$kst = new KiinteistoSuojelutyyppi($kiint_suojelutyyppi);
	    		$st = new Suojelutyyppi($kiint_suojelutyyppi['suojelutyyppi']);
	    		
	    		DB::table('kiinteisto_suojelutyyppi')->insert([
	    				'kiinteisto_id' => $kiinteisto_id,
	    				'suojelutyyppi_id' => $st->id, 
	    				'merkinta' => $kst->merkinta,
	    				'selite' => $kst->selite,
	    				'luoja' => Auth::user()->id
	    		]);
	    	}
    	}
    }
}
