<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class Nayttely extends Model {
    protected $table = "nayttely";
	
	/**
	 * fillable elements, otherwise we will get MassAssignemntException *
	 */
	protected $fillable = array (
			"nimi_fi",
			"nimi_se",
			"nimi_en",
			"kuvaus_fi",
			"kuvaus_se",
			"kuvaus_en",
			"paikka_fi",
			"paikka_se",
			"paikka_en",
			"alkaen",
			"paattyen"
	);
	public $timestamps = false;
}
