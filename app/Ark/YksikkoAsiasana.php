<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

class YksikkoAsiasana extends Model
{
    protected $table = "yksikko_asiasana";
    protected $fillable = array('yksikko_id', 'asiasana');
    public $timestamps = false;
      
    public function yksikko() {
    	return $this->hasOne('App\Ark\Yksikko', 'id', 'yksikko_id');
    }
    
}
