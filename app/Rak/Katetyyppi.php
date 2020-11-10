<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Katetyyppi extends Model
{
	use SoftDeletes;
	
    protected $table = "katetyyppi";
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
	 * Update katetyypit of a rakennus
	 *
	 * @param $rakennus_id 
	 * @param $katetyypit
	 */
	public static function update_rakennus_katetyypit($rakennus_id, $katetyypit) {
		if (!is_null($katetyypit)) {
			// remove all selections and add new ones
			DB::table('rakennus_katetyyppi')->where('rakennus_id', $rakennus_id)->delete();
	
			foreach($katetyypit as $katetyyppi) {
				$rt = new katetyyppi($katetyyppi);
					
				DB::table('rakennus_katetyyppi')->insert([
						'rakennus_id' => $rakennus_id,
						'katetyyppi_id' => $rt->id,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
