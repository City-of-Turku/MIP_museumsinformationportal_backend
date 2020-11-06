<?php


namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
class KohdeMjrTutkimus extends Model {
	
	protected $table = "ark_kohde_mjrtutkimus";
	protected $fillable = array('ark_kohde_id', 'ark_tutkimus_id', 'tutkija', 'vuosi', 'tutkimuslaji_id', 'huomio');
	public $timestamps = false;
	
	
	public function mjrtutkimuslaji() {
		return $this->hasOne('App\Ark\Tutkimuslaji', 'id', 'ark_tutkimuslaji_id');
	}
	
}
