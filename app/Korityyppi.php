<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Korityypit. esim. Löytö, Näyte jne
 */
class Korityyppi extends Model
{
    protected $table = "korityyppi";
    
    protected $fillable = array('nimi_fi', 'nimi_se', 'nimi_en', 'taulu', 'aktiivinen');
    
    public $timestamps = false;
}
