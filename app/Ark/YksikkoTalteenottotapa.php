<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class YksikkoTalteenottotapa extends Model
{
	protected $table = "yksikko_talteenottotapa";
	protected $fillable = array('yksikko_id', 'talteenottotapa_id');
	public $timestamps = false;	
	
	public function talteenottotapa() {
		return $this->hasOne('App\Ark\Talteenottotapa', 'id', 'talteenottotapa_id');
	}
	
	public function yksikko() {
		return $this->hasOne('App\Ark\Yksikko', 'id', 'yksikko_id');
	}
}
