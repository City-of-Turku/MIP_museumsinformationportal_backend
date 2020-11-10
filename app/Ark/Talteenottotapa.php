<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class Talteenottotapa extends Model
{
    protected $table = "talteenottotapa";
    
    /** fillable elements, otherwise we will get MassAssignemntException **/
	protected $fillable = array('nimi_fi', 'nimi_se', 'nimi_en', 'kuvaus_fi', 'kuvaus_se', 'kuvaus_en', 'aktiivinen');
	
	public $timestamps = false;
	
	//Is this needed or correct???
	public function yksikkoTalteenottotapa() {
		return $this->belongsToMany('App\Ark\Yksikko', 'yksikko_talteenottotapa');
	}
}
