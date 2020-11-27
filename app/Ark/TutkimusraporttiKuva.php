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
class TutkimusraporttiKuva extends Model
{
    use SoftDeletes;

    protected $table = "ark_tutkimusraportti_kuva";

    protected $fillable = array(
        'ark_tutkimus_id', 'ark_kuva_id', 'kappale', 'jarjestys'
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
    public function tutkimusraportti()
    {
        return $this->belongsTo('App\Ark\ArkTutkimusraportti', 'ark_tutkimusraportti_id');
    }
    public function ark_kuva()
    {
        return $this->belongsTo('App\Ark\ArkKuva', 'ark_kuva_id');
    }
    public function luoja()
    {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }

    public function muokkaaja()
    {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }

    public function poistaja()
    {
        return $this->belongsTo('App\Kayttaja', 'poistaja');
    }
    public function tutkimus()
    {
        return $this->belongsTo('App\Ark\Tutkimus', 'ark_tutkimus_id');
    }


    /**
     * Välitaulusta poistetaan löydön materiaalit ja lisätään muokatut
     */
    public static function paivita_kuvat($tutkimusraporttiId, $kuvat, $kappale)
    {
        // poista vanhat
        DB::table('ark_tutkimusraportti_kuva')->where([['ark_tutkimusraportti_id', '=', $tutkimusraporttiId], ['kappale', '=', $kappale]])->delete();
        $i = 0;
        Log::debug($kuvat);
        if ($kuvat != null) {
            foreach ($kuvat as $key => $val) {
                $k = new TutkimusraporttiKuva();
                $k->ark_tutkimusraportti_id = $tutkimusraporttiId;
                $k->ark_kuva_id = $val['id'];
                $k->kappale = $kappale;
                $k->jarjestys = $i;

                $k->luoja = Auth::user()->id;
                $k->save();
                $i++;
            }
        }
    }
}
