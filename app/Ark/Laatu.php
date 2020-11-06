<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class Laatu extends Model {
    protected $table = "laatu";
	
	/**
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
