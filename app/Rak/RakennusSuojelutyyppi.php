<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RakennusSuojelutyyppi extends Model
{
    protected $table = "rakennus_suojelutyyppi";
    public $timestamps = false; 
    protected $fillable = array(
    		'suojelutyyppi_id',
    		'rakennus_id',
    		'merkinta', 
    		'selite',
    		'id'
    		
    );
    
    public function suojelutyyppi() {
    	return $this->belongsTo('App\Rak\Suojelutyyppi');
    }
    

    /**
     * Update suojelutyypit of a rakennus
     *
     * @param $rakennus_id
     * @param $rakennus_suojelutyypit
     */
    public static function update_rakennus_suojelutyypit($rakennus_id, $rakennus_suojelutyypit) {
    	
    	if (!is_null($rakennus_suojelutyypit)) {
	    	// remove all selections and add new ones
	    	DB::table('rakennus_suojelutyyppi')->where('rakennus_id', $rakennus_id)->delete();
	    	
	    	foreach($rakennus_suojelutyypit as $rakennus_suojelutyyppi) {
	    		
	    		$kst = new RakennusSuojelutyyppi($rakennus_suojelutyyppi);
	    		$st = new Suojelutyyppi($rakennus_suojelutyyppi['suojelutyyppi']);
	    		
	    		DB::table('rakennus_suojelutyyppi')->insert([
	    				'rakennus_id' => $rakennus_id,
	    				'suojelutyyppi_id' => $st->id, 
	    				'merkinta' => $kst->merkinta,
	    				'selite' => $kst->selite,
	    				'luoja' => Auth::user()->id
	    		]);
	    	}
    	}
    }
}
