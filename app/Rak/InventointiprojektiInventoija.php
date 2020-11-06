<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;

class InventointiprojektiInventoija extends Model {
	
    protected $table = "inventointiprojekti_inventoija";
    public $timestamps = false;
    protected $hidden = ['id'];       
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
    		'inventointiprojekti_id',
    		'inventoija_nimi',
    		'inventoija_arvo',
    		'inventoija_id',
    		'inventoija_organisaatio',
    ];
    
    public function kayttaja() {
    	return $this->hasOne('App\Kayttaja', 'id', 'inventoija_id');
    }
    
    public function inventointiprojekti() {
    	return $this->hasOne('App\Rak\Inventointiprojekti', 'id', 'inventointiprojekti_id');
    }
    
}
