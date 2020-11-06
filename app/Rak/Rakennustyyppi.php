<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Rakennustyyppi extends Model
{
	use SoftDeletes;
	
    protected $table = "rakennustyyppi";
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
	 * Update rakennustyypit of a rakennus
	 *
	 * @param $rakennus_id 
	 * @param $rakennustyypit
	 */
	public static function update_rakennus_rakennustyypit($rakennus_id, $rakennustyypit) {
		if (!is_null($rakennustyypit)) {
			// remove all rakennustyyppi selections and add new ones
			DB::table('rakennus_rakennustyyppi')->where('rakennus_id', $rakennus_id)->delete();
	
			foreach($rakennustyypit as $rakennustyyppi) {
				$kt = new Rakennustyyppi($rakennustyyppi);
					
				DB::table('rakennus_rakennustyyppi')->insert([
						'rakennus_id' => $rakennus_id,
						'rakennustyyppi_id' => $kt->id,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
