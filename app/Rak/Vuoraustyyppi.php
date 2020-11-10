<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Vuoraustyyppi extends Model
{
	use SoftDeletes;
	
    protected $table = "vuoraustyyppi";
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
	 * Update vuoraustyypit of a rakennus
	 *
	 * @param $rakennus_id
	 * @param $vuoraustyypit
	 */
	public static function update_rakennus_vuoraustyypit($rakennus_id, $vuoraustyypit) {
		if (!is_null($vuoraustyypit)) {
			// remove all selections and add new ones
			DB::table('rakennus_vuoraustyyppi')->where('rakennus_id', $rakennus_id)->delete();
	
			foreach($vuoraustyypit as $vuoraustyyppi) {
				$rt = new vuoraustyyppi($vuoraustyyppi);
					
				DB::table('rakennus_vuoraustyyppi')->insert([
						'rakennus_id' => $rakennus_id,
						'vuoraustyyppi_id' => $rt->id,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
