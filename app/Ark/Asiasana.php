<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class Asiasana extends Model {
	protected $table = "asiasana";
	
	/**
	 * fillable elements, otherwise we will get MassAssignemntException *
	 */
	protected $fillable = array (
			"asiasana_fi",
			"asiasana_se",
			"asiasana_en" 
	);
	public $timestamps = false;
	
	public function luoja() {
		return $this->belongsTo('App\Kayttaja', 'luoja');
	}
	
	public function muokkaaja() {
		return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}
	
	/**
	 * Define relationships
	 */
	public function asiasanasto() {
		return $this->belongsTo ( 'App\Ark\Asiasanasto' );
	}
	
	/*
	 * public function yksikkoAsiasana() {
	 * return $this->belongsToMany('App\Yksikko', 'yksikko_asiasana');
	 * }
	 */
}
