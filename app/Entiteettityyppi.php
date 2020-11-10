<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Entiteettityyppi extends Model {
	
	protected $table = "entiteetti_tyyppi";
		
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
	//		'nimi'	
	];
	
	/**
	 * By default, Eloquent will maintain the created_at and updated_at columns on your database table automatically.
	 * Simply add these timestamp columns to your table and Eloquent will take care of the rest.
	 */
	public $timestamps = true;
	
	
	public static function getSingle($id) {
		return self::select('*')->where('id', '=', $id);
	}
	
	
}
