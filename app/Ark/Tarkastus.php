<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/*
 * Tarkastustutkimuksen kentät
 */
class Tarkastus extends Model
{
    use SoftDeletes;

    protected $table = "ark_tarkastus";

    protected $fillable = array(
        'tarkastaja', 'aiemmat_tiedot', 'aiemmat_loydot', 'tarkastusloydot', 'liitteet', 'tarkastusolosuhteet', 'muuta',
        'tarkastuksen_syy', 'ymparisto_maasto', 'kohteen_kuvaus', 'muut_tiedot', 'kohteen_kunto', 'hoitotarve', 'suoja_alueeksi',
        'maankayttohankkeet', 'sailymisen_asiat', 'kohteen_tiedon_maara'
    );

    /*
     * Aikaleimat päivitetään automaattisesti by Laravel
     */
    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    public function tarkastaja() {
        return $this->belongsTo('App\Kayttaja', 'tarkastaja');
    }
}
