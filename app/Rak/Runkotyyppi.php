<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Runkotyyppi extends Model
{
	use SoftDeletes;
	
    protected $table = "runkotyyppi";
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
	 * Update runkotyypit of a rakennus
	 *
	 * @param $rakennus_id
	 * @param $runkotyypit
	 */
	public static function update_rakennus_runkotyypit($rakennus_id, $runkotyypit) {
		if (!is_null($runkotyypit)) {
			// remove all selections and add new ones
			DB::table('rakennus_runkotyyppi')->where('rakennus_id', $rakennus_id)->delete();
	
			foreach($runkotyypit as $runkotyyppi) {
				$rt = new runkotyyppi($runkotyyppi);
					
				DB::table('rakennus_runkotyyppi')->insert([
						'rakennus_id' => $rakennus_id,
						'runkotyyppi_id' => $rt->id,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
