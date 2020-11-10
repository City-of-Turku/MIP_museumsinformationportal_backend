<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class LoytoTyyppi extends Model {
    protected $table = "loyto_tyyppi";
	
	/**
	 * Vanha toteutus. kts ArkLoytotyyppi
	 * fillable elements, otherwise we will get MassAssignemntException *
	 */
	protected $fillable = array (
			"nimi_fi",
			"nimi_se",
			"nimi_en",
			"kuvaus_fi",
			"kuvaus_se",
			"kuvaus_en"
	);
	public $timestamps = false;
}
