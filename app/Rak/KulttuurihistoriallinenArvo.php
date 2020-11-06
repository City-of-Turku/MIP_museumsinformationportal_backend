<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;

class KulttuurihistoriallinenArvo extends Model {
    
	//protected $table = "kulttuurihistoriallinenarvo"; // no such table
	
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	*/
	protected $fillable = [
			"id",
			"nimi_fi",
			"nimi_se",
			"nimi_en"
	];
	
	/**
	 * By default, Eloquent will maintain the created_at and updated_at columns on your database table automatically.
	 * Simply add these timestamp columns to your table and Eloquent will take care of the rest.
	*/
	public $timestamps = false;

	public static function getSingle($id) {
		return self::select('*')->where('id', '=', $id);
	}
	
	/**
	 * Method to limit results to only given type of culture historical values
	 * 
	 * @param $query
	 * @param String $type
	 * @author 
	 * @version 1.0
	 * @since 1.0
	 */
	public function scopeWithType($query, $type) {
		return $query->where('tyyppi', '=', $type);
	}
	
}
