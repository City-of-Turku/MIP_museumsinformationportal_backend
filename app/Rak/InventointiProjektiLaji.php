<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventointiProjektiLaji extends Model
{
	use SoftDeletes;
	
    protected $table = "inventointiprojekti_laji";
    public $timestamps = true;
    
    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";
    
    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';
    
    /**
     * fillable elements
     */
    protected $fillable = array (
    		"id",
    		"nimi_fi",
    		"nimi_se",
    		"nimi_en",
    		"tekninen_projekti"
    );
    
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
}
