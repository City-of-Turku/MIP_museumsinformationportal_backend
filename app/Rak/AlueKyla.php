<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AlueKyla extends Model
{
	protected $table = "alue_kyla";
	public $timestamps = false;
	protected $hidden = [];
	protected $fillable = [
			'alue_id',
			'kyla_id'
	];
	
	public static function update_alue_kylat($alue_id, $kylat) {
		
		if (!is_null($kylat)) {
			// remove all selections and add new ones
			DB::table('alue_kyla')->where('alue_id', $alue_id)->delete();
	
			foreach($kylat as $kyla) {
				DB::table('alue_kyla')->insert([
						'alue_id' => $alue_id,
						'kyla_id' => $kyla['id']
						//'luoja' => Auth::user()->id
				]);
			}
		}
	}
}
