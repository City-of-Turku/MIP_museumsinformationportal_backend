<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class Kokoelma extends Model {
    protected $table = "kokoelma";
	
	/**
	 * fillable elements, otherwise we will get MassAssignemntException *
	 */
	protected $fillable = array (
			"museo_id",
			"paakokoelma_id",
			"nimi_fi",
			"nimi_se",
			"nimi_en",
			"kuvaus_fi",
			"kuvaus_se",
			"kuvaus_en"
	);
	public $timestamps = false;
}
