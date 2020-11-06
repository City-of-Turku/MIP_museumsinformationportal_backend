<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Kayttotarkoitus extends Model
{
	use SoftDeletes;
	
    protected $table = "kayttotarkoitus";
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
	
	public function luoja() {
		return $this->belongsTo('App\Kayttaja', 'luoja');
	}
	
	public function muokkaaja() {
		return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}
	
	/**
	 * Update alkuperainen_kaytto of a rakennus
	 *
	 * @param $rakennus_id
	 * @param $alkuperaisetkaytot
	 */
	public static function update_rakennus_alkuperaisetkaytot($rakennus_id, $alkuperaisetkaytot) {
		if (!is_null($alkuperaisetkaytot)) {
			// remove all selections and add new ones
			DB::table('rakennus_alkuperainenkaytto')->where('rakennus_id', $rakennus_id)->delete();
	
			foreach($alkuperaisetkaytot as $kayttotarkoitus) {
				$kt = new Kayttotarkoitus($kayttotarkoitus);
					
				DB::table('rakennus_alkuperainenkaytto')->insert([
						'rakennus_id' => $rakennus_id,
						'kayttotarkoitus_id' => $kt->id,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
	
	/**
	 * Update nykykaytot of a rakennus
	 *
	 * @param $rakennus_id
	 * @param $nykykaytot
	 */
	public static function update_rakennus_nykykaytot($rakennus_id, $nykykaytot) {
		if (!is_null($nykykaytot)) {
			// remove all selections and add new ones
			DB::table('rakennus_nykykaytto')->where('rakennus_id', $rakennus_id)->delete();
	
			foreach($nykykaytot as $kayttotarkoitus) {
				$kt = new Kayttotarkoitus($kayttotarkoitus);
					
				DB::table('rakennus_nykykaytto')->insert([
						'rakennus_id' => $rakennus_id,
						'kayttotarkoitus_id' => $kt->id,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
