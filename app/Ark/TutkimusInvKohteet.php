<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Inventointi-tutkimuksen kohteiden välitaulu
 * @author vilheino
 *
 */
class TutkimusInvKohteet extends Model
{
    protected $table = "ark_tutkimus_inv_kohteet";
    protected $fillable = array('ark_tutkimus_id', 'ark_kohde_id', 'inventoija_id', 'inventointipaiva');
    public $timestamps = false;

    public static function byTutkimusId($id) {
        return static::select('ark_tutkimus_inv_kohteet.*')
        ->where('ark_tutkimus_id', '=', "$id");
    }

    public function inventoija() {
        return $this->belongsTo('App\Kayttaja', 'inventoija_id');
    }

    /**
     * Välitaulusta poistetaan kohteet ja lisätään muokatut
     */
    public static function paivita_kohteet($id, $invKohteet) {
        if (!is_null($invKohteet)) {
            // poista vanhat
            DB::table('ark_tutkimus_inv_kohteet')->where('ark_tutkimus_id', $id)->delete();

            foreach($invKohteet as $kohde) {
                $invKohde = new TutkimusInvKohteet();
                $invKohde->ark_tutkimus_id = $id;
                $invKohde->ark_kohde_id = $kohde['kohde_id'];
                $invKohde->luoja = Auth::user()->id;
                $invKohde->save();
            }
        }
    }

    public static function paivita_kohde($invKohde, $kohde_id) {
        // Jos rivi on jo aikaisemmin olemassa, päivitetään sen inventoija- ja inventointipaiva -tiedot (Inventoija päivittää tutkimusalueella olevaa kohdetta)
        // Muutoin lisätään uusi rivi (Inventoija lisää kokonaan uuden kohteen)
        if (!is_null($invKohde)) {
            $existing = TutkimusInvKohteet::where('ark_tutkimus_id', $invKohde['inventointitutkimus_id'])
            ->where('ark_kohde_id', $kohde_id)
            ->first();

            if(!$existing) {
                $k = new TutkimusInvKohteet();
                $k->ark_tutkimus_id = $invKohde['inventointitutkimus_id'];
                $k->ark_kohde_id = $kohde_id;
                $k->luoja = Auth::user()->id;
                $k->inventoija_id = $invKohde['inventoija_id'];
                $k->inventointipaiva = $invKohde['inventointipaiva'];
                $k->save();
            } else {
                $existing->inventoija_id = $invKohde['inventoija_id'];
                $existing->inventointipaiva = $invKohde['inventointipaiva'];
                $existing->update();
            }
        }
    }
}
