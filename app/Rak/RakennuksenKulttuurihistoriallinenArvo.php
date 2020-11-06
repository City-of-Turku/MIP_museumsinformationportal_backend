<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RakennuksenKulttuurihistoriallinenArvo extends KulttuurihistoriallinenArvo
{
	use SoftDeletes;
	
    protected $table = "rakennuskulttuurihistoriallinenarvo";
    public $timestamps = true;
    
    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";
    
    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';
    
    public static function getSingle($id) {
    	return self::select('*')->where('id', '=', $id);
    }
    
    public function luoja() {
    	return $this->belongsTo('App\Kayttaja', 'luoja');
    }
    
    public function muokkaaja() {
    	return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }
    
    /**
     * Update kulttuurihistoriallisetarvot of a rakennus
     *
     * @param $rakennus_id
     * @param $kulttuurihistoriallisetarvot
     */
    public static function update_rakennus_kulttuurihistoriallisetarvot($rakennus_id, $kulttuurihistoriallisetarvot) {
    	
    	if (!is_null($kulttuurihistoriallisetarvot)) {
    		// remove all selections and add new ones
	    	DB::table('rakennus_rakennuskulttuurihistoriallinenarvo')->where('rakennus_id', $rakennus_id)->delete();
	    
	    	foreach($kulttuurihistoriallisetarvot as $kulttuurihistoriallinenarvo) {
	    		$kha = new RakennuksenKulttuurihistoriallinenArvo($kulttuurihistoriallinenarvo);
	    
	    		DB::table('rakennus_rakennuskulttuurihistoriallinenarvo')->insert([
	    				'rakennus_id' => $rakennus_id,
	    				'kulttuurihistoriallinenarvo_id' => $kha->id,
	    				'luoja' => Auth::user()->id
	    		]);
	    	}
    	}
    }
}
