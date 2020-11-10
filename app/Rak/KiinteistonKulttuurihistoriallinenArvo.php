<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KiinteistonKulttuurihistoriallinenArvo extends KulttuurihistoriallinenArvo
{
	use SoftDeletes;
	
    protected $table = "kiinteistokulttuurihistoriallinenarvo";
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
    
    /**
     * Update kulttuurihistoriallisetarvot of a kiinteisto
     *
     * @param $kiinteisto_id
     * @param $kulttuurihistoriallisetarvot
     */
    public static function update_kiinteisto_kulttuurihistoriallisetarvot($kiinteisto_id, $kulttuurihistoriallisetarvot) {
    	if(!is_null($kulttuurihistoriallisetarvot)) {
	    	// remove all selections and add new ones
	    	DB::table('kiinteisto_kiinteistokulttuurihistoriallinenarvo')->where('kiinteisto_id', $kiinteisto_id)->delete();
	    
	    	foreach($kulttuurihistoriallisetarvot as $kulttuurihistoriallinenarvo) {
	    		$kha = new KiinteistonKulttuurihistoriallinenArvo($kulttuurihistoriallinenarvo);
	    				
	    		DB::table('kiinteisto_kiinteistokulttuurihistoriallinenarvo')->insert([
	    				'kiinteisto_id' => $kiinteisto_id,
	    				'kulttuurihistoriallinenarvo_id' => $kha->id,
	    				'luoja' => Auth::user()->id
	    		]);
	    	}
    	}
    }

}
