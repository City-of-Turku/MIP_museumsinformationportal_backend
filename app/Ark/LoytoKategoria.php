<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class LoytoKategoria extends Model {
    protected $table = "loyto_kategoria";
	
	/**
	 * fillable elements, otherwise we will get MassAssignemntException *
	 */
	protected $fillable = array (
			"nimi_fi",
			"nimi_se",
			"nimi_en",
			"lyhenne_fi",
			"lyhenne_se",
			"lyhenne_en"
	);
	public $timestamps = false;
}
