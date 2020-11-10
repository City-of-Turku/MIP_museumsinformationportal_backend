<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class OCMLuokka extends Model {
    protected $table = "ocm_luokka";
	
	/**
	 * fillable elements, otherwise we will get MassAssignemntException *
	 */
	protected $fillable = array (
			"nimi_fi",
			"nimi_se",
			"nimi_en"
	);
	public $timestamps = false;
}
