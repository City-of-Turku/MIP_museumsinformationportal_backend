<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;

class InventointiprojektiAjanjakso extends Model {
	
    protected $table = "inventointiprojekti_ajanjakso";
    public $timestamps = false; 
    protected $fillable = array('alkupvm', 'loppupvm');
    
    public function luoja() {
    	return $this->belongsTo('App\Kayttaja', 'luoja');
    }
    
    public function muokkaaja() {
    	return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }
    
    public function inventointiprojekti() {
    	return $this->belongsTo('App\Rak\Inventointiprojekti', 'id', 'inventointiprojekti_id');
    }
    
}
