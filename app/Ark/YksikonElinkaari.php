<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class YksikonElinkaari extends Model
{
    protected $table = "yksikon_elinkaari";
    
    /** fillable elements, otherwise we will get MassAssignemntException **/
	protected $fillable = array('nimi_fi', 'nimi_se', 'nimi_en', 'kuvaus_fi', 'kuvaus_se', 'kuvaus_en', 'aktiivinen');
	
	public $timestamps = false;
}
