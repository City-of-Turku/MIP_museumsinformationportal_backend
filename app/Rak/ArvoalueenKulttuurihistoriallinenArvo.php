<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArvoalueenKulttuurihistoriallinenArvo extends KulttuurihistoriallinenArvo
{
	use SoftDeletes;
	
    protected $table = "arvoaluekulttuurihistoriallinenarvo";
    
    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";
    
    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';
    
    public function luoja() {
    	return $this->belongsTo('App\Kayttaja', 'luoja');
    }
    
    public function muokkaaja() {
    	return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }
    
    public function poistaja() {
    	return $this->belongsTo('App\Kayttaja', 'poistaja');
    }
    
    public static function getSingle($id) {
    	return self::select('*')->where('id', '=', $id);
    }
        
    /**
     * Update kulttuurihistoriallisetarvot of a arvoalue
     *
     * @param $arvoalue_id
     * @param $kulttuurihistoriallisetarvot
     */
    public static function update_arvoalue_kulttuurihistoriallisetarvot($arvoalue_id, $kulttuurihistoriallisetarvot) {
    	
    	if (!is_null($kulttuurihistoriallisetarvot)) {
	    	// remove all selections and add new ones
	    	DB::table('arvoalue_arvoaluekulttuurihistoriallinenarvo')->where('arvoalue_id', $arvoalue_id)->delete();
	    
	    	foreach($kulttuurihistoriallisetarvot as $kulttuurihistoriallinenarvo) {
	    		$kha = new ArvoalueenKulttuurihistoriallinenArvo($kulttuurihistoriallinenarvo);
	    
	    		DB::table('arvoalue_arvoaluekulttuurihistoriallinenarvo')->insert([
	    				'arvoalue_id' => $arvoalue_id,
	    				'kulttuurihistoriallinenarvo_id' => $kha->id,
	    				'luoja' => Auth::user()->id
	    		]);
	    	}
    	}
    }
}
