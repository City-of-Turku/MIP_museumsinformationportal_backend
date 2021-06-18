<?php

namespace App\Ark;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Kayttaja;

/**
 * Tutkimusraportti.
 *
 */
class TutkimuslajiKokoelmalaji extends Model
{
    use SoftDeletes;

    protected $table = "ark_tutkimuslaji_kokoelmalajit";

    protected $fillable = array(
        'ark_tutkimuslaji_id', 'ark_kokoelmalaji_id'
    );

    /*
     * Aikaleimat päivitetään automaattisesti by Laravel
     */
    public $timestamps = true;

    const CREATED_AT         = 'luotu';
    const UPDATED_AT         = 'muokattu';
    const DELETED_AT         = "poistettu";

    const CREATED_BY        = 'luoja';
    const UPDATED_BY        = 'muokkaaja';
    const DELETED_BY        = 'poistaja';

    /**
     * Relaatiot
     */
    public function kokoelmalaji()
    {
        return $this->hasOne('App\Ark\Kokoelmalaji', 'id', 'ark_kokoelmalaji_id');
    }

    public function tutkimuslaji() {
        return $this->hasOne('App\Ark\Tutkimuslaji', 'id', 'ark_tutkimuslaji_id');
    }
}
