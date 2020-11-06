<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;

class InventointiprojektiKunta extends Model
{
	protected $table = "inventointiprojekti_kunta";
	protected $primaryKey = null;
	public $incrementing = false;
	
	protected $hidden = [];
	protected $fillable = array('inventointiprojekti_id', 'kunta_id');
	public $timestamps = false;
		
	public function inventointiprojekti() {
		return $this->belongsTo('App\Rak\Inventointiprojekti', 'id', 'inventointiprojekti_id');
	}
}
