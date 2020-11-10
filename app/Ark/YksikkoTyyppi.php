<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class YksikkoTyyppi extends Model
{
    protected $table = "yksikko_tyyppi";
    
    /** fillable elements, otherwise we will get MassAssignemntException **/
	protected $fillable = array('nimi_fi', 'nimi_se', 'nimi_en', 'aktiivinen');
	
	public $timestamps = false;
}
