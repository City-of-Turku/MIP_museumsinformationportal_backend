<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArvoalueSuojelutyyppi extends Model
{
    protected $table = "arvoalue_suojelutyyppi";
    public $timestamps = false; 
    protected $fillable = array(
    		'suojelutyyppi_id',
    		'arvoalue_id',
    		'merkinta', 
    		'selite',
    		'id'
    		
    );
    
    public function suojelutyyppi() {
    	return $this->belongsTo('App\Rak\Suojelutyyppi');
    }
    

    /**
     * Update suojelutyypit of a arvoalue
     *
     * @param $arvoalue_id
     * @param $arvoalue_suojelutyypit
     */
    public static function update_arvoalue_suojelutyypit($arvoalue_id, $arvoalue_suojelutyypit) {
    	
    	if (!is_null($arvoalue_suojelutyypit)) {
	    	// remove all selections and add new ones
	    	DB::table('arvoalue_suojelutyyppi')->where('arvoalue_id', $arvoalue_id)->delete();
	    	
	    	foreach($arvoalue_suojelutyypit as $arvoalue_suojelutyyppi) {
	    		
	    		$kst = new ArvoalueSuojelutyyppi($arvoalue_suojelutyyppi);
	    		$st = new Suojelutyyppi($arvoalue_suojelutyyppi['suojelutyyppi']);
	    		
	    		DB::table('arvoalue_suojelutyyppi')->insert([
	    				'arvoalue_id' => $arvoalue_id,
	    				'suojelutyyppi_id' => $st->id, 
	    				'merkinta' => $kst->merkinta,
	    				'selite' => $kst->selite,
	    				'luoja' => Auth::user()->id
	    		]);
	    	}
    	}
    }
}
