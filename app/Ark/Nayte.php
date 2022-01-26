<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Näytteen model
 */
class Nayte extends Model
{
    use SoftDeletes;

    protected $table = "ark_nayte";

    protected $fillable = array(
        'ark_tutkimusalue_yksikko_id', 'ark_tutkimusalue_kerros_id', 'ark_naytekoodi_id', 'ark_naytetyyppi_id', 'ark_talteenottotapa_id', 'ark_nayte_tila_id',
        'luettelointinumero', 'alanumero', 'kuvaus', 'koordinaatti_n', 'koordinaatti_e', 'koordinaatti_z', 'koordinaatti_n_min', 'koordinaatti_n_max', 'koordinaatti_e_min',
        'koordinaatti_e_max', 'koordinaatti_z_min', 'koordinaatti_z_max', 'laboratorion_arvio', 'luokka', 'luunayte_maara', 'luunayte_maara_yksikko',
        'maanayte_maara', 'rf_naytteen_koko', 'lisatiedot', 'naytetta_jaljella', 'ark_tutkimusalue_id', 'vakituinen_sailytystila_id', 'vakituinen_hyllypaikka', 'tilapainen_sijainti',
        'paino_ennen', 'paino_ennen_yksikko', 'paino_jalkeen', 'paino_jalkeen_yksikko', 'kunto', 'kunto_paivamaara', 'sailytysolosuhteet', 'konservointi_lisatiedot',
        'alkup_luetnro'
    );

    /**
     * Laravel ei ymmärrä kaikkia SQL-tyyppejä. Castataan kentät, jotta erityisesti floatit menevät oikein.
     */
    protected $casts = ['luunayte_maara' => 'float', 'maanayte_maara' => 'float', 'koordinaatti_z' => 'float', 'maanayte_maara' => 'float', 'koordinaatti_z_min' => 'float',
        'koordinaatti_z_max' => 'float', 'luunayte_maara' => 'float', 'paino_ennen' => 'float', 'paino_jalkeen' => 'float'];

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
        return self::select('ark_nayte.*')->where('id', '=', $id);
    }

    /**
     * Kaikkien haku tai katselijan oikeuksilla haku
     *
     * Split part osuudella jaotellaan luettelointinumero eri kenttiin, jotta järjestys luettelointinumeron saadaan tehtyä oikein.
     */
    public static function getAll() {

        // Katselija näkee vain niiden tutkimusten löydöt, joihin henkilö on liitetty
        if(Auth::user()->ark_rooli == 'katselija') {
            // Tutkimusten id:t
            $ids = Tutkimus::getAllIdsForKatselija(Auth::user()->id)->get();

            $q = self::select('ark_nayte.*')
                ->selectRaw("split_part(ark_nayte.luettelointinumero, ':', 1) as luettelointinumero1,
                            split_part(ark_nayte.luettelointinumero, ':', 2) as luettelointinumero2,
                            case when split_part(luettelointinumero, ':', 3) ~ '^[0-9\.]+$' -- sisältää pelkästään numeroita -> säilytetään originaali
                                then split_part(luettelointinumero, ':', 3)
                                when split_part(luettelointinumero, ':', 3) ~ '[0-9a-z]' -- sisältää numeroita tai kirjaimia -> korvataan kirjaimet ascii koodilla
                                then regexp_replace(split_part(luettelointinumero, ':', 3), '[^0-9]', ascii(substring(split_part(luettelointinumero, ':', 3), '[^0-9]'))::text)
                                else '0' -- jos ei kumpikaan (eli tyhjä) -> korvataan null 0:lla
                            end::text as luettelointinumero3");

            $q->whereIn('ark_nayte.id', function($q) use ($ids) {
                $q = self::joinTutkimus($q);
                $q->whereIn('ark_tutkimus.id', $ids);
            });
                return $q;

        } else {
            // notice comments inside SQL
            return self::select("ark_nayte.*")
                ->selectRaw("split_part(ark_nayte.luettelointinumero, ':', 1) as luettelointinumero1,
                            split_part(ark_nayte.luettelointinumero, ':', 2) as luettelointinumero2,
                            case when split_part(luettelointinumero, ':', 3) ~ '^[0-9\.]+$' -- sisältää pelkästään numeroita -> säilytetään originaali
                                then split_part(luettelointinumero, ':', 3)
                                when split_part(luettelointinumero, ':', 3) ~ '[0-9a-z]' -- sisältää numeroita tai kirjaimia -> korvataan kirjaimet ascii koodilla
                                then regexp_replace(split_part(luettelointinumero, ':', 3), '[^0-9]', ascii(substring(split_part(luettelointinumero, ':', 3), '[^0-9]'))::text)
                                else '0' -- jos ei kumpikaan (eli tyhjä) -> korvataan null 0:lla
                            end::text as luettelointinumero3");
        }
    }

    /**
     * Näytteen luettelointinumeron juokseva alanumero.
     * Haetaan per tutkimus ja näytekoodi, suurin alanumero.
     */
    public static function haeAlanumero($tutkimus_id, $naytekoodi_id) {
        $tutkimus = Tutkimus::getSingle($tutkimus_id)->first();
        if($tutkimus->ark_tutkimuslaji_id == 6 || $tutkimus->ark_tutkimuslaji_id == 11) { //CASE IRTOLÖYTÖ
            return self::select('ark_nayte.alanumero')
            ->join('ark_tutkimusalue', 'ark_nayte.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id')
            ->join('ark_tutkimus', 'ark_tutkimusalue.ark_tutkimus_id', '=', 'ark_tutkimus.id')
            ->where('ark_nayte.ark_naytekoodi_id', '=', $naytekoodi_id)
            ->where('ark_tutkimus.id', '=', $tutkimus_id)
            ->max('ark_nayte.alanumero');
        } else {
            return self::select('ark_nayte.alanumero')
            ->join('ark_tutkimusalue_yksikko', 'ark_nayte.ark_tutkimusalue_yksikko_id', '=', 'ark_tutkimusalue_yksikko.id')
            ->join('ark_tutkimusalue', 'ark_tutkimusalue_yksikko.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id')
            ->join('ark_tutkimus', 'ark_tutkimusalue.ark_tutkimus_id', '=', 'ark_tutkimus.id')
            ->where('ark_nayte.ark_naytekoodi_id', '=', $naytekoodi_id)
            ->where('ark_tutkimus.id', '=', $tutkimus_id)
            ->max('ark_nayte.alanumero');
        }
    }

    /**
     * Mutators. Muunnetaan koordinaattikenttien pilkut pisteiksi ja toisinpäin. Tyhjät = null kantaan.
     */
    public function setKoordinaattiNAttribute($value)
    {
        if($value){
            $this->attributes['koordinaatti_n'] = str_replace(',', '.', $value);
        }else{
            $this->attributes['koordinaatti_n'] = null;
        }
    }
    public function getKoordinaattiNAttribute($value)
    {
        return str_replace('.', ',', $value);
    }
    public function setKoordinaattiEAttribute($value)
    {
        if($value){
            $this->attributes['koordinaatti_e'] = str_replace(',', '.', $value);
        }else{
            $this->attributes['koordinaatti_e'] = null;
        }
    }
    public function getKoordinaattiEAttribute($value)
    {
        return str_replace('.', ',', $value);
    }
    public function setKoordinaattiNMinAttribute($value)
    {
        if($value){
            $this->attributes['koordinaatti_n_min'] = str_replace(',', '.', $value);
        }else{
            $this->attributes['koordinaatti_n_min'] = null;
        }
    }
    public function getKoordinaattiNMinAttribute($value)
    {
        return str_replace('.', ',', $value);
    }
    public function setKoordinaattiNMaxAttribute($value)
    {
        if($value){
            $this->attributes['koordinaatti_n_max'] = str_replace(',', '.', $value);
        }else{
            $this->attributes['koordinaatti_n_max'] = null;
        }
    }
    public function getKoordinaattiNMaxAttribute($value)
    {
        return str_replace('.', ',', $value);
    }
    public function setKoordinaattiEMinAttribute($value)
    {
        if($value){
            $this->attributes['koordinaatti_e_min'] = str_replace(',', '.', $value);
        }else{
            $this->attributes['koordinaatti_e_min'] = null;
        }
    }
    public function getKoordinaattiEMinAttribute($value)
    {
        return str_replace('.', ',', $value);
    }
    public function setKoordinaattiEMaxAttribute($value)
    {
        if($value){
            $this->attributes['koordinaatti_e_max'] = str_replace(',', '.', $value);
        }else{
            $this->attributes['koordinaatti_e_max'] = null;
        }
    }
    public function getKoordinaattiEMaxAttribute($value)
    {
        return str_replace('.', ',', $value);
    }

    /**
     * Relaatiot
     */
    public function yksikko() {
        return $this->belongsTo('App\Ark\TutkimusalueYksikko', 'ark_tutkimusalue_yksikko_id');
    }
    public function naytekoodi() {
        return $this->belongsTo('App\Ark\Naytekoodi', 'ark_naytekoodi_id');
    }
    public function naytetyyppi() {
        return $this->belongsTo('App\Ark\Naytetyyppi', 'ark_naytetyyppi_id');
    }
    public function tila() {
        return $this->belongsTo('App\Ark\NayteTila', 'ark_nayte_tila_id');
    }
    public function talteenottotapa() {
        return $this->belongsTo('App\Ark\NayteTalteenottotapa', 'ark_talteenottotapa_id');
    }
    public function tapahtumat(){
        return $this->hasMany('App\Ark\NayteTapahtumat', 'ark_nayte_id');
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

    // Konservoinnin kuntoarvion tekijä
    public function tekija() {
        return $this->belongsTo('App\Kayttaja', 'tekija');
    }

    //IRTOLÖYTÖ.
    public function tutkimusalue() {
        return $this->belongsTo('App\Ark\Tutkimusalue', 'ark_tutkimusalue_id');
    }

    public function sailytystila() {
        return $this->belongsTo('App\Ark\ArkSailytystila', 'vakituinen_sailytystila_id');
    }

    /**
     * Suodatukset
     */
    // Rivien mukaan
    public function scopeWithLimit($query, $start_row, $row_count) {
        return $query->skip($start_row)->take($row_count);
    }

    /*
     * Jos tarkka = true haetaan vain annettua
     */
    public function scopeWithLuettelointinumero($query, $keyword, $tarkka) {
        if($tarkka){
            return $query->where('ark_nayte.luettelointinumero', 'ILIKE', $keyword);
        }else{
            return $query->where('ark_nayte.luettelointinumero', 'ILIKE', "%".$keyword)
            ->orWhere('ark_nayte.alkup_luetnro', 'ILIKE', "%".$keyword);
        }
    }
    public function scopeWithUniikkiLuettelointinumero($query, $keyword) {
        return $query->where('ark_nayte.luettelointinumero', '=', $keyword);
    }
    /*
     * Päänumerohaku. Haetaan ark_tutkimus taulusta
     */
    public function scopeWithPaanumero($query, $keyword) {

        return $query->whereIn('ark_nayte.id', function($q) use ($keyword) {
            $q = self::joinTutkimus($q)
            ->where('ark_tutkimus.nayte_paanumero', 'ILIKE', "%".$keyword."%");
        });
    }
    /*
     * Tutkimuksen nimi. Haetaan ark_tutkimus taulusta
     */
    public function scopeWithTutkimuksenNimi($query, $keyword) {
        return $query->whereIn('ark_nayte.id', function($q) use ($keyword) {
            $q = self::joinTutkimus($q)
            ->where('ark_tutkimus.nimi', 'ILIKE', "%".$keyword."%");
        });
    }

    public function scopeWithTutkimusId($query, $keyword) {
        return $query->whereIn('ark_nayte.id', function($q) use ($keyword) {
            $q = self::joinTutkimus($q)
            ->where('ark_tutkimus.id', '=', $keyword);
        });
    }

    /*
     * Yksikön tunnus. Haetaan ark_tutkimusalue_yksikko taulusta
     */
    public function scopeWithYksikkotunnus($query, $keyword) {

        return $query->whereIn('ark_nayte.id', function($q) use ($keyword) {
            $q = self::joinTutkimus($q)
            ->where('ark_tutkimusalue_yksikko.yksikkotunnus', 'ILIKE', $keyword);
        });
    }

    /*
     * Tutkimuksen lyhenne
     */
    public function scopeWithTutkimusLyhenne($query, $keyword) {
        return $query->whereIn('ark_nayte.id', function($q) use ($keyword) {
            $q = self::joinTutkimus($q)
            ->where('ark_tutkimus.tutkimuksen_lyhenne', 'ILIKE', $keyword."%");
        });
    }

    /*
     * Haku näytteiden id listalla. Koritoiminnallisuus käyttää
     */
    public function scopeWithNayteIdLista($query, $keyword) {
        return $query->whereIn('ark_nayte.id', $keyword);
    }

    /*
     * Näytekoodi
     */
    public function scopeWithNaytekoodit($query, $keyword) {
        // $keyword = pilkulla erotellut id:t
        return $query->whereIn('ark_nayte.ark_naytekoodi_id', explode(',', $keyword));
    }

    /*
     * Näytetyyppi
     */
    public function scopeWithNaytetyypit($query, $keyword) {
        // $keyword = pilkulla erotellut id:t
        return $query->whereIn('ark_nayte.ark_naytetyyppi_id', explode(',', $keyword));
    }

    /*
     * Näytettä jäljellä. 1 = Ei, 2 = Kyllä
     */
    public function scopeWithNaytettaJaljella($query, $keyword) {

        if($keyword == 1){
            return $query->where('ark_nayte.naytetta_jaljella', '=', false);
        }else{
            return $query->where('ark_nayte.naytetta_jaljella', '=', true);
        }
    }

    public function scopeWithKuvaus($query, $keyword) {
        return $query->where('ark_nayte.kuvaus', 'ILIKE', "%".$keyword."%");
    }

    public function scopeWithLisatiedot($query, $keyword) {
        return $query->where('ark_nayte.lisatiedot', 'ILIKE', "%".$keyword."%");
    }

    /*
     * Näytteen tila
     */
    public function scopeWithNaytteenTilat($query, $keyword) {
        return $query->whereIn('ark_nayte.ark_nayte_tila_id', explode(',', $keyword));
    }
    /*
     * Ajoitusnäytteen luokka
     */
    public function scopeWithLuokka($query, $keyword) {
        return $query->where('ark_nayte.luokka', '=', $keyword);
    }

    /*
     * Yksikön id:llä
     */
    public function scopeWithTutkimusalueYksikko($query, $id) {
        return $query->where('ark_tutkimusalue_yksikko_id', '=', $id);
    }

    /**
     * Suodatusjärjestys
     */
    public function scopeWithOrderBy($query, $jarjestys_kentta, $jarjestys_suunta) {

        if ($jarjestys_kentta == "luettelointinumero") {
            return $query->orderBy("luettelointinumero1", $jarjestys_suunta)
            ->orderBy("luettelointinumero2", $jarjestys_suunta)
            ->orderBy("luettelointinumero3", $jarjestys_suunta);
        }
    }

    /*
     * Palautetaan näytteet jotka kuuluvat annettuun tutkimusalueeseen. Tutkimusalueen itsessään pitää kuulua tutkimukseen, joka
     * on tyypiltään (ark_tutkimuslaji_id) irtolöytö (id=6)
     */
    public function scopeWithIrtoloytotutkimusAlueId($query, $keyword) {
        return $query->join('ark_tutkimusalue', 'ark_tutkimusalue.id', '=', 'ark_nayte.ark_tutkimusalue_id')
        ->where('ark_tutkimusalue.id', '=', $keyword);
    }

    /**
     * Joinaa tutkimuksen, tutkimusalueen ja yksikön näytteeseen.
     */
    private static function joinTutkimus($q){
        return $q->select('ark_nayte.id')
        ->from('ark_nayte')
        ->leftjoin('ark_tutkimusalue_yksikko', 'ark_nayte.ark_tutkimusalue_yksikko_id', '=', 'ark_tutkimusalue_yksikko.id')
        ->join('ark_tutkimusalue', function($join) { //Joinitaan mukaan irtolöytötutkimukset myös, suoraan löytö->tutkimusalue->
            $join->on('ark_tutkimusalue_yksikko.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id')
            ->orOn('ark_nayte.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id');
        })
        //->join('ark_tutkimusalue', 'ark_tutkimusalue_yksikko.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id')
        ->join('ark_tutkimus', 'ark_tutkimusalue.ark_tutkimus_id', '=', 'ark_tutkimus.id');
    }

    public function files() {
        return $this->belongsToMany('App\Ark\ArkTiedosto', 'ark_tiedosto_nayte', 'ark_nayte_id');
    }
}
