<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Yksikön työvaihe
 */
class YksikkoTyovaihe extends Model
{
    use SoftDeletes;

    protected $table = "ark_tutkimusalue_yksikko_tyovaihe";

    protected $fillable = array('ark_tutkimusalue_yksikko_id', 'paivamaara', 'kuvaus', 'muokattu', 'muokkaaja');

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

    /**
     * Haku id:n mukaan.
     */
    public static function getSingle($id) {
        return self::select('ark_tutkimusalue_yksikko_tyovaihe.*')->where('id', '=', $id);
    }

    /**
     * Kaikkien haku
     */
    public static function getAll() {
        return self::select('ark_tutkimusalue_yksikko_tyovaihe.*');
    }

    /**
     * Yksikön työvaiheiden päivitys
     */
    public static function paivita_yksikko_tyovaiheet($yksikko_id, $tyovaiheet) {
        if (!is_null($tyovaiheet)) {
            // pois vanhat
            DB::table('ark_tutkimusalue_yksikko_tyovaihe')->where('ark_tutkimusalue_yksikko_id', $yksikko_id)->delete();

            foreach($tyovaiheet as $t_vaihe) {
                $tyovaihe = new YksikkoTyovaihe();
                $tyovaihe->ark_tutkimusalue_yksikko_id = $yksikko_id;
                $tyovaihe->paivamaara = $t_vaihe['paivamaara'];
                $tyovaihe->kuvaus = $t_vaihe['kuvaus'];

                $tyovaihe->luoja = Auth::user()->id;
                $tyovaihe->save();
            }
        }
    }

    /**
     * Relaatiot
     */
    public function tutkimusalueYksikko() {
        return $this->belongsTo('App\Ark\TutkimusalueYksikko');
    }

    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }

    public function muokkaaja() {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }

    public function poistaja() {
        return $this->belongsTo('App\Kayttaja', 'poistaja');
    }
}
