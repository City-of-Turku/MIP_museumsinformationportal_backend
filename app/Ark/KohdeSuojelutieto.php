<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KohdeSuojelutieto extends Model {
	
	protected $table = "ark_kohde_suojelutiedot";
	protected $fillable = array('ark_kohde_id', 'suojelutyyppi_id', 'merkinta', 'selite');
	public $timestamps = false;
			
	public static function paivita_kohde_suojelutiedot($kohde_id, $suojelutiedot) {
		if (!is_null($suojelutiedot)) {
			// remove all selections and add new ones
			DB::table('ark_kohde_suojelutiedot')->where('ark_kohde_id', $kohde_id)->delete();
			
			foreach($suojelutiedot as $st) {
				$kst = new KohdeSuojelutieto();
				$suojelutyyppiId = $st['suojelutyyppi']['id'];
				
				$kst->ark_kohde_id = $kohde_id;
				$kst->suojelutyyppi_id = $suojelutyyppiId;
				array_key_exists('merkinta', $st) ? $kst->merkinta = $st['merkinta'] : null;
				array_key_exists('selite', $st) ? $kst->selite = $st['selite'] : null;
				$kst->luoja = Auth::user()->id;
				
				$kst->save();				
			}
		}
	}
	
	public function suojelutyyppi() {
		return $this->belongsTo('App\Rak\Suojelutyyppi');
	}
}
