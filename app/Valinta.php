<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Valinta extends Model {
	
	protected $table = "valinnat";
	public $timestamps = false;
	protected $hidden = [];
	protected $fillable = [];
	
	
	public function scopeWithCategory($query, $category) {
		return $query->where('kategoria', '=', $category);
	}
	
}
