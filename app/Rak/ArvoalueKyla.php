<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArvoalueKyla extends Model
{
	protected $table = "arvoalue_kyla";
	public $timestamps = false;
	protected $hidden = [];
	protected $fillable = [
			'arvoalue_id',
			'kyla_id'
	];
	
	public static function update_arvoalue_kylat($arvoalue_id, $kylat) {
		
		if (!is_null($kylat)) {
			// remove all selections and add new ones
			DB::table('arvoalue_kyla')->where('arvoalue_id', $arvoalue_id)->delete();
	
			foreach($kylat as $kyla) {
				DB::table('arvoalue_kyla')->insert([
						'arvoalue_id' => $arvoalue_id,
						'kyla_id' => $kyla['id'],
						'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
