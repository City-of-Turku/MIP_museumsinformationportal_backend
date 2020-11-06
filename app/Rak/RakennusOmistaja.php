<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
 
class RakennusOmistaja extends Model
{
    protected $table = "rakennus_omistaja";
	
	/**
	 * fillable elements
	 */
	protected $fillable = array (
			"id",
			"rakennus_id",
			"etunimi",
			"sukunimi"
	);
	
	public $timestamps = false;
	
	/**
	 * Update omistajat of a rakennus
	 *
	 * @param $rakennus_id
	 * @param $rakennus_omistajat
	 */
	public static function update_rakennus_omistajat($rakennus_id, $rakennus_omistajat) {
		
		if (!is_null($rakennus_omistajat)) {
			// remove all and add new ones
			DB::table('rakennus_omistaja')->where('rakennus_id', $rakennus_id)->delete();
			 
			foreach($rakennus_omistajat as $rakennus_omistaja) {
		
				$ro = new RakennusOmistaja($rakennus_omistaja);
				if(strlen($ro->etunimi) == 0) {
				    $ro->etunimi = "";
				}
				if(strlen($ro->sukunimi) == 0) {
				    $ro->sukunimi = "";
				}
				
				DB::table('rakennus_omistaja')->insert([
						'rakennus_id' => $rakennus_id,
						'etunimi' => $ro->etunimi,
						'sukunimi' => $ro->sukunimi,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
