<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SuunnittelijaRakennus extends Model
{
	use SoftDeletes;
	protected $table = "suunnittelija_rakennus";
	
	
	//protected $casts = ['rakennus_id' => 'integer', 'suunnittelija_id' => 'integer', 'suunnittelija_tyyppi_id' => 'integer'];
	
	/**
	 * fillable elements
	 */
	protected $fillable = array (
			"id",
			"rakennus_id",
			"suunnittelija_id",
			"suunnitteluvuosi_alku",
			"suunnitteluvuosi_loppu",
			"suunnittelija_tyyppi_id",
			"lisatieto"
	);
	
	public $timestamps = false;
	
	const DELETED_AT 		= "poistettu";
	/*
	 * Remove all existing suunnittelijat of the building and add new ones.
	 * TODO: Soft deletes?
	 */
	public static function updateSuunnittelijat($rakennus_id, $suunnittelijat) {
		// remove all and add new ones
		DB::table('suunnittelija_rakennus')->where('rakennus_id', $rakennus_id)->delete();
		
		foreach($suunnittelijat as $s) {
			//value "" is not accepted, change them to null
			if(isset($s['suunnitteluvuosi_alku'])) {
				if($s['suunnitteluvuosi_alku'] == "") {
					$s['suunnitteluvuosi_alku'] = null;
				}
			} else {
				$s['suunnitteluvuosi_alku'] = null;
			}

			if(isset($s['suunnitteluvuosi_loppu'])) {
				if($s['suunnitteluvuosi_loppu'] == "") {
					$s['suunnitteluvuosi_loppu'] = null;
				}
			} else {
				$s['suunnitteluvuosi_loppu'] = null;
			}
			
			if(isset($s['lisatieto'])) {
				if($s['lisatieto'] == "") {
					$s['lisatieto'] = null;
				}
			} else {
				$s['lisatieto'] = null;
			}

			DB::table('suunnittelija_rakennus')->insert([
					'rakennus_id' => $rakennus_id,
					'suunnittelija_id' => $s['suunnittelija']['properties']['id'],
					'suunnitteluvuosi_alku' => $s['suunnitteluvuosi_alku'],
					'suunnitteluvuosi_loppu' => $s['suunnitteluvuosi_loppu'],
					'suunnittelija_tyyppi_id' => $s['suunnittelijatyyppi']['id'],
					'lisatieto' => $s['lisatieto'],
					'luoja' => Auth::user()->id
			]);			
		}
	}
	
	public function suunnittelijatyyppi() {
		return $this->hasOne('App\Rak\SuunnittelijaTyyppi', 'id', 'suunnittelija_tyyppi_id');
	}
	
	public function suunnittelija() {
		return $this->belongsTo('App\Rak\Suunnittelija');
	}
}