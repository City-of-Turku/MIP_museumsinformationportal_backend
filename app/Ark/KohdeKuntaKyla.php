<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KohdeKuntaKyla extends Model {
	
	protected $table = "ark_kohde_kuntakyla";
	protected $fillable = array('ark_kohde_id', 'kunta_id', 'kyla_id');
	public $timestamps = false;
		
	
	public static function paivita_kohde_kunnatkylat($kohde_id, $kunnatkylat) {
		if (!is_null($kunnatkylat)) {
			// remove all selections and add new ones
			DB::table('ark_kohde_kuntakyla')->where('ark_kohde_id', $kohde_id)->delete();
			
			foreach($kunnatkylat as $kk) {
				$kohdeKuntaKyla = new KohdeKuntaKyla();
				$kohdeKuntaKyla->ark_kohde_id = $kohde_id;
				$kohdeKuntaKyla->kunta_id = $kk['kunta']['id'];
				if(array_key_exists('kyla', $kk) && isset($kk['kyla']['id'])) {
				    $kohdeKuntaKyla->kyla_id = $kk['kyla']['id'];
				} else {
				    $kohdeKuntaKyla->kyla_id = null;
				}
				$kohdeKuntaKyla->luoja = Auth::user()->id;
				$kohdeKuntaKyla->save();				
			}
		}
	}	
	
	
	public function kunta() {
		return $this->hasOne('App\Kunta', 'id', 'kunta_id');
	}
	
	public function kyla() {
		return $this->hasOne('App\Kyla', 'id', 'kyla_id');
	}
}
