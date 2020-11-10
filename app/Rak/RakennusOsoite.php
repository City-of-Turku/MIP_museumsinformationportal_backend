<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
 
class RakennusOsoite extends Model
{
    protected $table = "rakennus_osoite";
	
	/**
	 * fillable elements
	 */
	protected $fillable = array (
			"id",
			"rakennus_id",
			"katunimi",
			"katunumero",
			"kieli",
			"jarjestysnumero"
	);
	
	public $timestamps = false;
	
	public static function getSingle($id) {
		return self::select('*')->where('id', '=', $id);
	}
	
	/**
	 * Update osoitteet of a rakennus
	 *
	 * @param $rakennus_id
	 * @param $rakennus_osoitteet
	 */
	public static function update_rakennus_osoitteet($rakennus_id, $rakennus_osoitteet) {
		
		if (!is_null($rakennus_osoitteet)) {
			// remove all and add new ones
			DB::table('rakennus_osoite')->where('rakennus_id', $rakennus_id)->delete();
			 
			foreach($rakennus_osoitteet as $rakennus_osoite) {
		
				$ro = new RakennusOsoite($rakennus_osoite);
				
				DB::table('rakennus_osoite')->insert([
						'rakennus_id' => $rakennus_id,
						'katunimi' => $ro->katunimi,
						'katunumero' => $ro->katunumero,
						'kieli' => $ro->kieli,
						'jarjestysnumero' => $ro->jarjestysnumero,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
