<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class ArkFinnaLog extends Model
{

		protected $table = "finna_log";

		public $timestamps = false;

	/**
	 * fillable elements
	 */
	protected $fillable = array (
			"id",
			"ark_loyto_id",
			"siirto_pvm"
	);

}