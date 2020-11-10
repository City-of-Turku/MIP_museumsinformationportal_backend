<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TutkimusKuntaKyla extends Model {

	protected $table = "ark_tutkimus_kuntakyla";
	protected $fillable = array('ark_tutkimus_id', 'kunta_id', 'kyla_id');
	public $timestamps = false;


	public static function paivita_tutkimus_kunnatkylat($tutkimus_id, $kunnatkylat) {
		if (!is_null($kunnatkylat)) {
			// remove all selections and add new ones
			DB::table('ark_tutkimus_kuntakyla')->where('ark_tutkimus_id', $tutkimus_id)->delete();

			foreach($kunnatkylat as $kk) {
				$tKuntaKyla = new TutkimusKuntaKyla();
				$tKuntaKyla->ark_tutkimus_id = $tutkimus_id;
				$tKuntaKyla->kunta_id = $kk['kunta']['id'];
				array_key_exists('kyla', $kk) ? $tKuntaKyla->kyla_id = $kk['kyla']['id'] : null;
				$tKuntaKyla->luoja = Auth::user()->id;
				$tKuntaKyla->save();
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
