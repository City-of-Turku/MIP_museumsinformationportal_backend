<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;

class MatkaraporttiSyy extends Model
{
	protected $table = "matkaraportti_syy";
	
	/**
	 * fillable elements
	 */
	protected $fillable = array (
			"matkaraportti_id",
			"matkaraportinsyy_id"
	);
	
	public $timestamps = false;
	
	public function syy() {
		return $this->hasOne('App\Rak\MatkaraportinSyy', 'id', 'matkaraportinsyy_id');
	}
}
