<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Tilatyyppi extends Model
{
	use SoftDeletes;
	
	protected $table = "tilatyyppi";
	public $timestamps = true;
	
	const CREATED_AT 		= 'luotu';
	const UPDATED_AT 		= 'muokattu';
	const DELETED_AT 		= "poistettu";
	
	const CREATED_BY		= 'luoja';
	const UPDATED_BY		= 'muokkaaja';
	const DELETED_BY		= 'poistaja';
	
	/**
	 * fillable elements
	 */
	protected $fillable = array (
			"id",
			"nimi_fi",
			"nimi_se",
			"nimi_en"
	);
	
	public static function getSingle($id) {
		return self::select('*')->where('id', '=', $id);
	}
	
	/**
	 * Update historiallinen tilatyyppi of a kiinteisto
	 *
	 * @param $kiinteisto_id
	 * @param $tilatyypit
	 */
	public static function update_kiinteisto_historiallinentilatyypit($kiinteisto_id, $tilatyypit) {
		if (!is_null($tilatyypit)) {
			// remove all selections and add new ones
			DB::table('kiinteisto_historiallinen_tilatyyppi')->where('kiinteisto_id', $kiinteisto_id)->delete();
	
			foreach($tilatyypit as $tilatyyppi) {
				$tt = new Tilatyyppi($tilatyyppi);
					
				DB::table('kiinteisto_historiallinen_tilatyyppi')->insert([
						'kiinteisto_id' => $kiinteisto_id,
						'tilatyyppi_id' => $tt->id,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
