<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;

class RakennusKattotyyppi extends Model
{
    protected $table = "rakennus_kattotyyppi";
	
	/**
	 * fillable elements
	 */
	protected $fillable = array (
			"rakennus_id",
			"kattotyyppi_id"
	);
	
	public $timestamps = false;
}
