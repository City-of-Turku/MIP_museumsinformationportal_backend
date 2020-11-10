<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Aluetyyppi extends Model
{
	use SoftDeletes;
	
    protected $table = "aluetyyppi";
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

	public function luoja() {
		return $this->belongsTo('App\Kayttaja', 'luoja');
	}
	
	public function muokkaaja() {
		return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}
	
	public function poistaja() {
		return $this->belongsTo('App\Kayttaja', 'poistaja');
	}
	
	public static function getSingle($id) {
		return self::select('*')->where('id', '=', $id);
	}
	
	/**
	 * Update historiallinen aluetyyppi of a kiinteisto
	 *
	 * @param $kiinteisto_id
	 * @param $aluetyypit
	 */
	public static function update_kiinteisto_aluetyypit($kiinteisto_id, $aluetyypit) {
		if (!is_null($aluetyypit)) {
			// remove all selections and add new ones
			DB::table('kiinteisto_aluetyyppi')->where('kiinteisto_id', $kiinteisto_id)->delete();
	
			foreach($aluetyypit as $aluetyyppi) {
				$tt = new aluetyyppi($aluetyyppi);
					
				DB::table('kiinteisto_aluetyyppi')->insert([
						'kiinteisto_id' => $kiinteisto_id,
						'aluetyyppi_id' => $tt->id,
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
