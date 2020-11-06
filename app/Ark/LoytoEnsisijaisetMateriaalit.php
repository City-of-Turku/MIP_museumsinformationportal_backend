<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;

/**
 * Esisijaisten materiaalien välitaulu
 */
class LoytoEnsisijaisetMateriaalit extends Model
{
    protected $table = "ark_loyto_ensisijaiset_materiaalit";
    protected $fillable = array('ark_loyto_materiaalikoodi_id', 'ark_loyto_materiaali_id');
    public $timestamps = false;

    public function ensisijaiset() {
        return $this->hasMany('App\Ark\LoytoMateriaali', 'ark_loyto_materiaali_id');
    }
}
