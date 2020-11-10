<?php

namespace App\Ark;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class YksikkoSijainti extends Model
{
	protected $table = "yksikko_sijainti";
	protected $fillable = array('id', 'yksikko_id', 'sijainti');
	public $timestamps = false;
	
	public static function byYksikkoId($yksikko_id) {
		return static::select('yksikko_sijainti.id as id',
				DB::raw(MipGis::getGeometryFieldQueryString("yksikko_sijainti.sijainti", "sijainti")),
				'yksikko.tunnus as yksikko_tunnus',
				'yksikko.id as yksikko_id')
				->leftJoin('yksikko', 'yksikko_id', '=', 'yksikko.id')
				->where('yksikko_id', '=', "$yksikko_id");
	}
}
