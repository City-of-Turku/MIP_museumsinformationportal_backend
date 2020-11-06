<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class TutkimusalueYksikko extends Model
{
    use SoftDeletes;

    protected $table = "ark_tutkimusalue_yksikko";

    protected $fillable = array(
        'ark_tutkimusalue_id', 'yksikko_tyyppi_id', 'yksikkotunnus', 'tyonimi', 'tyo_sijainti',
        'tyo_kaivajat', 'yksikko_kaivaustapa_id', 'yksikko_seulontatapa_id', 'kuvaus', 'kuvaus_note', 'yksikon_perusteet', 'stratigrafiset_suhteet',
        'rajapinnat', 'yksikon_perusteet_note', 'stratigrafiset_suhteet_note', 'rajapinnat_note', 'tulkinta', 'tulkinta_note',
        'ajoitus', 'ajoitus_note', 'ajoituksen_perusteet', 'ajoituksen_perusteet_note', 'lisatiedot', 'lisatiedot_note',
        'kaivaus_valmis', 'yksikon_numero', 'yksikko_paamaalaji_id', 'muokattu', 'muokkaaja', 'kaivaustapa_lisatieto', 'kaivaustapa_lisatieto_note'
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

    /**
     * Haku id:n mukaan.
     */
    public static function getSingle($id) {
        return self::select('ark_tutkimusalue_yksikko.*')->where('id', '=', $id);
    }

    /**
     * Kaikkien haku
     */
    public static function getAll() {
        return self::select('ark_tutkimusalue_yksikko.*')->orderby('yksikon_numero', 'asc');
    }

    /**
     * Hakee suurimman yksikkönumeron tutkimukselle
     */
    public static function haeSuurinYksikkoNumero($tutkimus_id){
        return DB::table('ark_tutkimusalue_yksikko')
        ->select('ark_tutkimusalue_yksikko.yksikon_numero')
        ->join('ark_tutkimusalue', 'ark_tutkimusalue.id', '=', 'ark_tutkimusalue_yksikko.ark_tutkimusalue_id')
        ->where('ark_tutkimusalue.ark_tutkimus_id', '=', $tutkimus_id)
        ->whereNull('ark_tutkimusalue_yksikko.poistettu')
        ->max('ark_tutkimusalue_yksikko.yksikon_numero');
    }

    /**
     * Relaatiot
     */
    public function tutkimusalue() {
        return $this->belongsTo('App\Ark\Tutkimusalue', 'ark_tutkimusalue_id');
    }

    public function tyovaiheet() {
        return $this->hasMany('App\Ark\YksikkoTyovaihe', 'ark_tutkimusalue_yksikko_id', 'id')->orderby('paivamaara', 'asc');
    }
    public function loydot() {
        return $this->hasMany('App\Ark\Loyto', 'ark_tutkimusalue_yksikko_id', 'id');
    }
    public function naytteet() {
        return $this->hasMany('App\Ark\Nayte', 'ark_tutkimusalue_yksikko_id', 'id');
    }
    public function yksikkoTyyppi() {
        return $this->belongsTo('App\Ark\YksikkoTyyppi', 'yksikko_tyyppi_id');
    }
    public function yksikkoKaivaustapa() {
        return $this->belongsTo('App\Ark\YksikkoKaivaustapa', 'yksikko_kaivaustapa_id');
    }
    public function yksikkoSeulontatapa() {
        return $this->belongsTo('App\Ark\YksikkoSeulontatapa', 'yksikko_seulontatapa_id');
    }

    public function paamaalaji() {
        return $this->belongsTo('App\Ark\YksikkoMaalaji', 'yksikko_paamaalaji_id');
    }

    /*
     * Hakee välitaulun 'yksikko_paasekoitteet' mukaiset yksikön pääsekoite-maalajit
     */
    public function paasekoitteet() {
        return $this->belongsToMany('App\Ark\YksikkoMaalaji' ,'yksikko_paasekoitteet' ,'ark_tutkimusalue_yksikko_id' ,'yksikko_paasekoite_id');
    }

    /*
     * Hakee välitaulun 'yksikko_muut_maalajit' mukaiset yksikön muut maalajit eli "Kerroksessa esiintyy lisäksi"
     */
    public function muutMaalajit() {
        return $this->belongsToMany('App\Ark\YksikkoMaalaji' ,'yksikko_muut_maalajit' ,'ark_tutkimusalue_yksikko_id' ,'yksikko_muu_maalaji_id');
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

    public function files() {
        return $this->belongsToMany('App\Ark\ArkTiedosto', 'ark_tiedosto_yksikko', 'ark_yksikko_id');
    }

    /*
     * Suodatukset
     */
    public function scopeWithTutkimusalue($query, $id) {
        return $query->where('ark_tutkimusalue_id', '=', $id);
    }

    // Tutkimuksen yksikön haku
    public function scopeWithTutkimuksenYksikko($query, $tutkimusId, $yksikkotunnus) {
        $query->select('ark_tutkimusalue_yksikko.*')
        ->from('ark_tutkimusalue_yksikko')
        ->join('ark_tutkimusalue', 'ark_tutkimusalue.id', '=', 'ark_tutkimusalue_yksikko.ark_tutkimusalue_id')
        ->where('ark_tutkimusalue.ark_tutkimus_id', '=', $tutkimusId)
        ->where('yksikkotunnus', 'ILIKE', $yksikkotunnus);
        return $query;
    }

    // Tutkimuksen sisällä yksikön numero oltava uniikki. Yksikön tutkimusalueelta löytyy tutkimuksen id.
    public function scopeWithTutkimus($query, $id, $yksikon_numero) {
        $query->select('ark_tutkimusalue_yksikko.*')
              ->from('ark_tutkimusalue_yksikko')
              ->join('ark_tutkimusalue', 'ark_tutkimusalue.id', '=', 'ark_tutkimusalue_yksikko.ark_tutkimusalue_id')
              ->where('ark_tutkimusalue.ark_tutkimus_id', '=', $id)
              ->where('yksikon_numero', '=', $yksikon_numero);
        return $query;
    }

    public function scopeWithTutkimusId($query, $id) {
        $query->select('ark_tutkimusalue_yksikko.*')
            ->from('ark_tutkimusalue_yksikko')
            ->join('ark_tutkimusalue', 'ark_tutkimusalue.id', '=', 'ark_tutkimusalue_yksikko.ark_tutkimusalue_id')
            ->where('ark_tutkimusalue.ark_tutkimus_id', '=', $id);

        return $query;
    }

    public function scopeWithYksikkotunnus($query, $keyword) {
        return $query->where('yksikkotunnus', 'ILIKE', "%".$keyword."%");
    }

    public function scopeWithLimit($query, $start_row, $row_count) {
        return $query->skip($start_row)->take($row_count);
    }

    public function scopeWithKaivausValmis($query, $keyword) {
        return $query->where('kaivaus_valmis', '=', $keyword);
    }
}
