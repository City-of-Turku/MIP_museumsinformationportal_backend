<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
 
class RakennusMuutosvuosi extends Model
{
    protected $table = "rakennus_muutosvuosi";
	
	/**
	 * fillable elements
	 */
	protected $fillable = array (
			"id",
			"rakennus_id",
			"alkuvuosi",
			"loppuvuosi",
			"selite"
	);
	
	public $timestamps = false;
	
	/**
	 * Update muutosvuodet of a rakennus
	 *
	 * @param $rakennus_id
	 * @param $rakennus_muutosvuodet
	 */
	public static function update_rakennus_muutosvuodet($rakennus_id, $rakennus_muutosvuodet) {
		
		if (!is_null($rakennus_muutosvuodet)) {
			// remove all and add new ones
			DB::table('rakennus_muutosvuosi')->where('rakennus_id', $rakennus_id)->delete();
			 
			foreach($rakennus_muutosvuodet as $rakennus_muutosvuosi) {
		
				$rmv = new RakennusMuutosvuosi($rakennus_muutosvuosi);
	
				DB::table('rakennus_muutosvuosi')->insert([
						'rakennus_id' => $rakennus_id,
						'alkuvuosi' => $rmv->alkuvuosi,
						'loppuvuosi' => $rmv->loppuvuosi,
						'selite' => $rmv->selite,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
