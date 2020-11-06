<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Perustustyyppi extends Model
{
	use SoftDeletes;
	
    protected $table = "perustustyyppi";
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
	 * Update perustustyypit of a rakennus
	 *
	 * @param $rakennus_id
	 * @param $perustustyypit
	 */
	public static function update_rakennus_perustustyypit($rakennus_id, $perustustyypit) {
		if (!is_null($perustustyypit)) {
			// remove all selections and add new ones
			DB::table('rakennus_perustustyyppi')->where('rakennus_id', $rakennus_id)->delete();
	
			foreach($perustustyypit as $perustustyyppi) {
				$pt = new Perustustyyppi($perustustyyppi);
					
				DB::table('rakennus_perustustyyppi')->insert([
						'rakennus_id' => $rakennus_id,
						'perustustyyppi_id' => $pt->id,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
