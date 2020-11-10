<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class Asiasanasto extends Model
{
	protected $table = "asiasanasto";
	
	/** fillable elements, otherwise we will get MassAssignemntException **/
	protected $fillable = array('tunnus_fi', 'tunnus_se', 'tunnus_en');
	
	public $timestamps = false;
	
	public function luoja() {
		return $this->belongsTo('App\Kayttaja', 'luoja');
	}
	
	public function muokkaaja() {
		return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}
}
