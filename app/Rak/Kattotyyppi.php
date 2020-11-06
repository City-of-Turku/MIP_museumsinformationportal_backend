<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Kattotyyppi extends Model
{
	use SoftDeletes;
	
    protected $table = "kattotyyppi";
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
	 * Update kattotyypit of a rakennus
	 *
	 * @param $rakennus_id
	 * @param $kattotyypit
	 */
	public static function update_rakennus_kattotyypit($rakennus_id, $kattotyypit) {		
		if (!is_null($kattotyypit)) {
			// remove all kattotyyppi selections and add new ones
			DB::table('rakennus_kattotyyppi')->where('rakennus_id', $rakennus_id)->delete();
	
			foreach($kattotyypit as $kattotyyppi) {
				$kt = new Kattotyyppi($kattotyyppi);
					
				DB::table('rakennus_kattotyyppi')->insert([
						'rakennus_id' => $rakennus_id,
						'kattotyyppi_id' => $kt->id,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
