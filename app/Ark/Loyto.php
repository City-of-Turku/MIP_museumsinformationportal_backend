<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Ark\TutkimusalueYksikko;
use Illuminate\Support\Facades\DB;

/**
 * Löydön model
 */
class Loyto extends Model
{
    use SoftDeletes;

    protected $table = "ark_loyto";

    protected $fillable = array(
        'ark_tutkimusalue_yksikko_id', 'ark_tutkimusalue_kerros_id', 'ark_loyto_materiaalikoodi_id', 'ark_loyto_ensisijainen_materiaali_id',
        'luettelointinumero', 'alanumero', 'kenttanumero_vanha_tyonumero', 'loytopaikan_tarkenne', 'koordinaatti_n', 'koordinaatti_e', 'koordinaatti_z',
        'kuvaus', 'kappalemaara', 'paino', 'painoyksikko', 'pituus', 'pituusyksikko', 'leveys', 'leveysyksikko', 'korkeus', 'korkeusyksikko', 'halkaisija',
        'halkaisijayksikko', 'paksuus', 'paksuusyksikko', 'muut_mitat', 'tulkinta', 'alkuvuosi', 'alkuvuosi_ajanlasku', 'paatosvuosi',
        'paatosvuosi_ajanlasku', 'ajoitus_kuvaus', 'ajoituksen_perusteet', 'tutkimukset_lahteet', 'lisatiedot', 'loydon_tila_id', 'konservointi',
        'loytopaivamaara', 'kappalemaara_arvio', 'ark_tutkimusalue_id', 'vakituinen_sailytystila_id', 'vakituinen_hyllypaikka', 'tilapainen_sijainti',
        'paino_ennen', 'paino_ennen_yksikko', 'paino_jalkeen', 'paino_jalkeen_yksikko', 'kunto', 'kunto_paivamaara', 'sailytysolosuhteet', 'konservointi_lisatiedot',
        'siirtyy_finnaan', 'sisaiset_lisatiedot'
    );

    /**
     * Laravel ei ymmärrä kaikkia SQL-tyyppejä. Castataan kentät, jotta erityisesti floatit menevät oikein.
     */
    protected $casts = ['paino' => 'float', 'pituus' => 'float', 'leveys' => 'float', 'korkeus' => 'float', 'halkaisija' => 'float',
        'paksuus' => 'float', 'koordinaatti_z' => 'float', 'paino_ennen' => 'float', 'paino_jalkeen' => 'float'];

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
        return self::select('ark_loyto.*')->where('id', '=', $id);
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

            $q = self::select("ark_loyto.*")
                ->selectRaw("split_part(ark_loyto.luettelointinumero, ':', 1) as luettelointinumero1,
                            split_part(ark_loyto.luettelointinumero, ':', 2) as luettelointinumero2,
                            case when split_part(luettelointinumero, ':', 3) ~ '^[0-9\.]+$' -- sisältää pelkästään numeroita -> säilytetään originaali
                                then split_part(luettelointinumero, ':', 3)
                                when split_part(luettelointinumero, ':', 3) ~ '[0-9a-z]' -- sisältää numeroita tai kirjaimia -> korvataan kirjaimet ascii koodilla
                                then regexp_replace(split_part(luettelointinumero, ':', 3), '[^0-9]', ascii(substring(split_part(luettelointinumero, ':', 3), '[^0-9]'))::text)
                                else '0' -- jos ei kumpikaan (eli tyhjä) -> korvataan null 0:lla
                            end::int as luettelointinumero3");

            $q->whereIn('ark_loyto.id', function($q) use ($ids) {
                $q = self::joinTutkimus($q);
                $q->whereIn('ark_tutkimus.id', $ids);
            });
            return $q;

        } else {
            // notice comments inside SQL
            return self::select("ark_loyto.*")
                ->selectRaw("split_part(ark_loyto.luettelointinumero, ':', 1) as luettelointinumero1,
                            split_part(ark_loyto.luettelointinumero, ':', 2) as luettelointinumero2,
                            case when split_part(luettelointinumero, ':', 3) ~ '^[0-9\.]+$' -- sisältää pelkästään numeroita -> säilytetään originaali
                                then split_part(luettelointinumero, ':', 3)
                                when split_part(luettelointinumero, ':', 3) ~ '[0-9a-z]' -- sisältää numeroita tai kirjaimia -> korvataan kirjaimet ascii koodilla
                                then regexp_replace(split_part(luettelointinumero, ':', 3), '[^0-9]', ascii(substring(split_part(luettelointinumero, ':', 3), '[^0-9]'))::text)
                                else '0' -- jos ei kumpikaan (eli tyhjä) -> korvataan null 0:lla
                            end::int as luettelointinumero3");
        }

    }

    /*
     * Haetaan löydöt jotka kuuluvat
     *  tutkimuksiin jotka ovat
     *      julkisia ja valmiita
     *  ja joiden
     *      'siirtyy_finnaan' on true
     *  tai jotka
     *      on jo aikaisemmin siirretty finnaan
     */
    public static function getAllForFinna() {
        return self::select("ark_loyto.*")
        ->leftjoin('ark_tutkimusalue_yksikko', 'ark_loyto.ark_tutkimusalue_yksikko_id', '=', 'ark_tutkimusalue_yksikko.id')
        ->join('ark_tutkimusalue', function($join) { //Joinitaan mukaan irtolöytötutkimukset myös, suoraan löytö->tutkimusalue->
            $join->on('ark_tutkimusalue_yksikko.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id')
            ->orOn('ark_loyto.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id');
        })
        ->join('ark_tutkimus', 'ark_tutkimusalue.ark_tutkimus_id', '=', 'ark_tutkimus.id')
        ->where(function($q) {
            $q->whereExists(function($q) {
                 // haetaan mukaan löydöt jotka on jo aikaisemmin siirtynyt finnaan, jotta ne voidaan asettaa poistetuiksi (jos ne on poistettu ensimmäisen siirron jälkeen)
                $q->select(DB::raw(1))->from('finna_log')->whereColumn('finna_log.ark_loyto_id', 'ark_loyto.id');
                // sekä otetaan mukaan muutoin ainoastaan valmiiden ja julkisten tutkimusten löydöt, joiden siirtyy_finnaan on true
            })->orWhere('ark_tutkimus.valmis', '=', true)->where('ark_tutkimus.julkinen', '=', true)->where('ark_loyto.siirtyy_finnaan', '=', true);
        });
    }

    /**
     * Haku id listan mukaan
     */
    public static function haeKoriin() {
        return self::select('ark_loyto.*');
    }

    /**
     * Löydön luettelointinumeron juokseva alanumero.
     * Haetaan per luettelointinumeron alkuosa (=kaikki paitsi juokseva numero) kasvatetaan juoksevaa numeroa yhdellä.
     * TAI jos ark_tutkimusalue_id on annettu, tiedetään, että löytö kuuluu irtolöytö- tai tarkastus
     * tutkimukselle, jolloin alanumero haetaan suoraan tutkimukselta (US9678)
     */
    public static function getAlanumero($yksikko_id, $materiaalikoodi_id, $ark_tutkimus_id, $ark_tutkimusalue_id) {
        if($yksikko_id && $materiaalikoodi_id) {
            /*
             * Haetaan yksikön tunnuksella kaikki samannumeroiset yksiköt,
             * Esim: M100A, M100B, M100C...
             * Tämän jälkeen haetaan näiden ID:n perusteella viimeisin juokseva numero
             * sen sijaan, että haettaisiin ainoastaan yhden yksikön ID:n mukaan
             * Eli jos
             *  M100A sisältää löydöt XXXX:100:1, XXXX:100:2, XXXX:100:3 ja
             *  M100B sisältää löydöt XXXX:100:4, XXXX:100:5, niin
             *  seuraava lisättävä löytö tulee saamaan XXXX:100:6 numeroksi.
             *
             * Myös yksikkötunnukset ilman loppunumeroa toimivat.
             *
             * Otetaan yksiköltä joka tässä on kyseessä tyyppi ja yksikon_numero,
             * jonka jälkeen voidaan näitä hyödyntämällä hakea seuraava vapaa juokseva alanumero.
             * Vaikka yksikön perässä oleva kirjain olisi eri, niin yksikon_numero on
             * kuitenkin sama kaikille (esim. M80a, M80b, M80c).
             */
            // Log::debug("Yksikko_id ja materiaalikoodi_id löytyy");
            $yksikko = TutkimusalueYksikko::getSingle($yksikko_id)->first();
            $tutkimusId = $yksikko->tutkimusalue->tutkimus->id;

            // Haetaan saman tutkimuksen MAX alanumero materiaalikoodilla, yksikkotyypillä ja yksikon numerolla.
            $alanumero = self::select('ark_loyto')
            ->leftjoin('ark_tutkimusalue_yksikko', 'ark_loyto.ark_tutkimusalue_yksikko_id', '=', 'ark_tutkimusalue_yksikko.id')
            ->join('ark_tutkimusalue', 'ark_tutkimusalue_yksikko.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id')
            ->join('ark_tutkimus', 'ark_tutkimusalue.ark_tutkimus_id', '=', 'ark_tutkimus.id')
            //->where('ark_tutkimusalue_yksikko.yksikko_tyyppi_id', '=', $yksikko->yksikko_tyyppi_id)
            ->where('ark_tutkimusalue_yksikko.yksikon_numero', '=', $yksikko->yksikon_numero)
            ->where('ark_loyto_materiaalikoodi_id', '=', $materiaalikoodi_id)
            ->where('ark_tutkimus.id', '=', $tutkimusId)
            ->max('alanumero');
        } else if($ark_tutkimusalue_id && $ark_tutkimus_id) { // CASE IRTOLÖYTÖ tai tarkastus
            $alanumero = self::select('ark_loyto')
            ->leftjoin('ark_tutkimusalue', 'ark_loyto.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id')
            ->leftjoin('ark_tutkimus', 'ark_tutkimusalue.ark_tutkimus_id', '=', 'ark_tutkimus.id')
            ->whereNull('ark_loyto.poistettu')
            ->where('ark_tutkimus_id', '=', $ark_tutkimus_id)
            ->max('alanumero');
        } else {
            // Normaali käsittely - juokseva luettelointinumero per tutkimus, kaikki löydöt joinitaan mukaan
            $alanumero = self::select('ark_loyto')
            ->leftjoin('ark_tutkimusalue_yksikko', 'ark_loyto.ark_tutkimusalue_yksikko_id', '=', 'ark_tutkimusalue_yksikko.id')
            ->join('ark_tutkimusalue', function($join) { //Joinitaan mukaan irtolöytötutkimukset myös, suoraan löytö->tutkimusalue->
                $join->on('ark_tutkimusalue_yksikko.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id')
                ->orOn('ark_loyto.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id');
            })
            ->leftjoin('ark_tutkimus', 'ark_tutkimusalue.ark_tutkimus_id', '=', 'ark_tutkimus.id')
            ->whereNull('ark_loyto.poistettu')
            ->where('ark_tutkimus_id', '=', $ark_tutkimus_id)
            ->max('alanumero');
        }
        return $alanumero + 1;
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

    /**
     * Suodatukset
     */
    public function scopeWithTutkimusalueYksikko($query, $id) {
        return $query->where('ark_tutkimusalue_yksikko_id', '=', $id);
    }

    public function scopeWithTutkimusalue($query, $nimi) {
        return $query->whereIn('ark_loyto.id', function($q) use ($nimi) {
            $q = self::joinTutkimus($q)
            ->where('ark_tutkimusalue.nimi', 'ILIKE', "%".$nimi."%");
        });
    }

    public function scopeWithLimit($query, $start_row, $row_count) {
        return $query->skip($start_row)->take($row_count);
    }

    /*
     * Päänumerohaku. Haetaan ark_tutkimus taulusta
     */
    public function scopeWithPaanumero($query, $keyword) {

        return $query->whereIn('ark_loyto.id', function($q) use ($keyword) {
            $q = self::joinTutkimus($q)
            ->where('ark_tutkimus.loyto_paanumero', 'ILIKE', "%".$keyword."%");
        });
    }

    // 1 = ajoitetut, 2 = ajoittamattomat löydöt
    public function scopeWithAjoitus($query, $keyword) {

        if($keyword == 1){
            return $query->where('ark_loyto.alkuvuosi', '!=', null);
        }else{
            return $query->where('ark_loyto.alkuvuosi', '=', null);
        }
    }

    public function scopeWithMateriaalikoodit($query, $keyword) {
        // $keyword = pilkulla erotellut id:t
        return $query->whereIn('ark_loyto.ark_loyto_materiaalikoodi_id', explode(',', $keyword));
    }

    public function scopeWithMateriaalit($query, $keyword) {
        return $query->whereIn('ark_loyto.id', function($q) use ($keyword) {
            $q->select('ark_loyto_id')
            ->from('ark_loyto_materiaalit')
            ->whereIn('ark_loyto_materiaalit.ark_loyto_materiaali_id', explode(',', $keyword));
        });
    }

    public function scopeWithEnsisijaisetMateriaalit($query, $keyword) {
        // $keyword = pilkulla erotellut id:t
        return $query->whereIn('ark_loyto.ark_loyto_ensisijainen_materiaali_id', explode(',', $keyword));
    }

    /*
     * Jos tarkka = true haetaan vain annettua
     */
    public function scopeWithLuettelointinumero($query, $keyword, $tarkka) {
        if($tarkka){
            return $query->where('ark_loyto.luettelointinumero', 'ILIKE', $keyword);
        }else{
            return $query->where('ark_loyto.luettelointinumero', 'ILIKE', "%".$keyword);
        }
    }

    public function scopeWithLoytotyypit($query, $keyword) {
        return $query->whereIn('ark_loyto.ark_loyto_tyyppi_id', explode(',', $keyword));
    }

    public function scopeWithLoytotyyppiTarkenteet($query, $keyword) {
        return $query->whereIn('ark_loyto.id', function($q) use ($keyword) {
            $q->select('ark_loyto_id')
            ->from('ark_loyto_tyyppi_tarkenteet')
            ->whereIn('ark_loyto_tyyppi_tarkenteet.ark_loyto_tyyppi_tarkenne_id', explode(',', $keyword));
        });
    }

    /**
     * Haku löytöjen id listalla. Koritoiminnallisuus tarvitsee
     */
    public function scopeWithLoytoIdLista($query, $keyword) {
        return $query->whereIn('ark_loyto.id', $keyword);
    }

    public function scopeWithMerkinnat($query, $keyword) {
        return $query->whereIn('ark_loyto.id', function($q) use ($keyword) {
            $q->select('ark_loyto_id')
            ->from('ark_loyto_merkinnat')
            ->whereIn('ark_loyto_merkinnat.ark_loyto_merkinta_id', explode(',', $keyword));
        });
    }

    public function scopeWithTulkinta($query, $keyword) {
        return $query->where('ark_loyto.tulkinta', 'ILIKE', "%".$keyword."%");
    }

    public function scopeWithAsiasana($query, $keyword) {
        return $query->whereIn('ark_loyto.id', function($q) use ($keyword) {
            $q->select('ark_loyto_asiasanat.ark_loyto_id')
            ->from('ark_loyto_asiasanat')
            ->where('ark_loyto_asiasanat.asiasana', 'ILIKE', "%".$keyword."%");
        });
    }

    public function scopeWithLoydonTilat($query, $keyword) {
        // $keyword = pilkulla erotellut id:t
        return $query->whereIn('ark_loyto.loydon_tila_id', explode(',', $keyword));
    }

    /*
     * Tutkimuksen nimi. Haetaan ark_tutkimus taulusta
     */
    public function scopeWithTutkimuksenNimi($query, $keyword) {

        return $query->whereIn('ark_loyto.id', function($q) use ($keyword) {
            $q = self::joinTutkimus($q)
            ->where('ark_tutkimus.nimi', 'ILIKE', "%".$keyword."%");
        });
    }

    public function scopeWithTutkimusId($query, $keyword) {
        return $query->whereIn('ark_loyto.id', function($q) use ($keyword) {
            $q = self::joinTutkimus($q)
            ->where('ark_tutkimus.id', '=', $keyword);
        });
    }

    /*
     * Yksikön tunnus. Haetaan ark_tutkimusalue_yksikko taulusta
     */
    public function scopeWithYksikkotunnus($query, $keyword) {

        return $query->whereIn('ark_loyto.id', function($q) use ($keyword) {
            $q = self::joinTutkimus($q)
            ->where('ark_tutkimusalue_yksikko.yksikkotunnus', 'ILIKE', $keyword);
        });
    }

    /*
     * Tutkimuksen lyhenne
     */
    public function scopeWithTutkimusLyhenne($query, $keyword) {
        return $query->whereIn('ark_loyto.id', function($q) use ($keyword) {
            $q = self::joinTutkimus($q)
            ->where('ark_tutkimus.tutkimuksen_lyhenne', 'ILIKE', $keyword."%");
        });
    }

    public function scopeWithVaatiiKonservointia($query, $keyword) {
        return $query->where('ark_loyto.konservointi', '=', $keyword);
    }

    public function scopeWithKuvaus($query, $keyword) {
        return $query->where('ark_loyto.kuvaus', 'ILIKE', "%".$keyword."%");
    }

    public function scopeWithLisatiedot($query, $keyword) {
        return $query->where('ark_loyto.lisatiedot', 'ILIKE', "%".$keyword."%");
    }

    public function scopeWithLoytopaikanTarkenne($query, $keyword) {
        return $query->where('ark_loyto.loytopaikan_tarkenne', 'ILIKE', "%".$keyword."%");
    }

    public function scopeWithKenttanumeroVanhaTyonumero($query, $keyword) {
        return $query->where('ark_loyto.kenttanumero_vanha_tyonumero', 'ILIKE', "%".$keyword."%");
    }

    /*
     * Ajoituksen aikavälillä haku.
     */
    public function scopeWithAjoitusAikajakso($query, $alkuvuosi, $alku_ajanlasku, $paatosvuosi, $paatos_ajanlasku) {
        if($alku_ajanlasku == 'eaa' && $paatos_ajanlasku == 'eaa' || $alku_ajanlasku == 'jaa' && $paatos_ajanlasku == 'jaa' ){

            $query->where('ark_loyto.alkuvuosi_ajanlasku', '=', $alku_ajanlasku)
            ->where('ark_loyto.alkuvuosi', '>=', $alkuvuosi)
            ->where('ark_loyto.alkuvuosi', '<=', $paatosvuosi)

            ->orWhere('ark_loyto.paatosvuosi_ajanlasku', '=', $paatos_ajanlasku)
            ->where('ark_loyto.paatosvuosi', '>=', $alkuvuosi)
            ->where('ark_loyto.paatosvuosi', '<=', $paatosvuosi);

        }
        else if($alku_ajanlasku == 'eaa' && $paatos_ajanlasku == 'jaa'){
            $query->where('ark_loyto.alkuvuosi', '>=', $alkuvuosi)
            ->where('ark_loyto.alkuvuosi_ajanlasku', '=', $alku_ajanlasku)
            ->where('ark_loyto.paatosvuosi', '<=', $paatosvuosi)
            ->where('ark_loyto.paatosvuosi_ajanlasku', '=', $paatos_ajanlasku);
        }

        return $query;
    }

    public function scopeWithAlkuvuosiAjanlasku($query, $vuosi, $ajanlasku) {
        return $query->where('ark_loyto.alkuvuosi', '=', $vuosi)->where('ark_loyto.alkuvuosi_ajanlasku', '=', $ajanlasku);
    }

    public function scopeWithPaatosvuosiAjanlasku($query, $vuosi, $ajanlasku) {
        return $query->where('ark_loyto.paatosvuosi', '=', $vuosi)->where('ark_loyto.paatosvuosi_ajanlasku', '=', $ajanlasku);
    }

    public function scopeWithLoytoId($query, $id) {
        return $query->where('ark_loyto.id', '=', $id);
    }

    /**
     * Suodatusjärjestys
     */
    public function scopeWithOrderBy($query, $jarjestys_kentta, $jarjestys_suunta) {

        if ($jarjestys_kentta == "luettelointinumero") {
            return $query->orderBy("luettelointinumero1", $jarjestys_suunta)
                ->orderBy("luettelointinumero2", $jarjestys_suunta)
                ->orderBy("luettelointinumero3", $jarjestys_suunta);
            //return $query->orderBy("luettelointinumero", $jarjestys_suunta);
        }else if($jarjestys_kentta == "alkuvuosi"){
            return $query->orderBy("alkuvuosi", $jarjestys_suunta);
        }
    }

    /*
     * Palautetaan löydöt jotka kuuluvat annettuun tutkimusalueeseen. Tutkimusalueen itsessään pitää kuulua tutkimukseen, joka
     * on tyypiltään (ark_tutkimuslaji_id) irtolöytö (id=6) tai tarkastustutkimus (id=11)
     */
    public function scopeWithIrtoloytotutkimusAlueId($query, $keyword) {
        return $query->join('ark_tutkimusalue', 'ark_tutkimusalue.id', '=', 'ark_loyto.ark_tutkimusalue_id')
        ->where('ark_tutkimusalue.id', '=', $keyword);
    }

    /*
     * If parameters are not given, default to
     * FROM: 1970, TO: now()
     */
    public function scopeWithDate($query, $from=null, $to=null) {
        $from = $from == null ? new Carbon('1970-01-01T00:00:00Z') : $from;
        $to = $to  == null ? Carbon::now() : $to;
        return $query->whereBetween('ark_loyto.luotu', [$from, $to])->orWhereBetween('ark_loyto.muokattu', [$from, $to])->orWhereBetween('ark_loyto.poistettu', [$from, $to]);
    }

    public function scopeWithSiirtyyFinnaan($query, $keyword) {
        return $query->where('ark_loyto.siirtyy_finnaan', '=', $keyword);
    }

    /**
     * Relaatiot
     */
    public function yksikko() {
        return $this->belongsTo('App\Ark\TutkimusalueYksikko', 'ark_tutkimusalue_yksikko_id');
    }
    public function materiaalikoodi() {
        return $this->belongsTo('App\Ark\LoytoMateriaalikoodi', 'ark_loyto_materiaalikoodi_id');
    }
    public function ensisijainenMateriaali() {
        return $this->belongsTo('App\Ark\LoytoMateriaali', 'ark_loyto_ensisijainen_materiaali_id');
    }
    public function loydonTila() {
        return $this->belongsTo('App\Ark\LoytoTila', 'loydon_tila_id');
    }

    /*
     * Hakee välitaulun 'ark_loyto_materiaalit' mukaiset löydölle kuuluvat materiaalit
     */
    public function materiaalit() {
        return $this->belongsToMany('App\Ark\LoytoMateriaali' ,'ark_loyto_materiaalit' ,'ark_loyto_id' ,'ark_loyto_materiaali_id');
    }

    public function loytotyyppi() {
        return $this->belongsTo('App\Ark\ArkLoytotyyppi', 'ark_loyto_tyyppi_id');
    }
    /*
     * Hakee välitaulun mukaiset löydön tyyppitarkenteet
     */
    public function loytotyyppiTarkenteet() {
        return $this->belongsToMany('App\Ark\ArkLoytotyyppiTarkenne' ,'ark_loyto_tyyppi_tarkenteet' ,'ark_loyto_id' ,'ark_loyto_tyyppi_tarkenne_id');
    }
    /*
     * Hakee välitaulun 'ark_loyto_merkinnat' mukaiset löydölle kuuluvat merkinnät
     */
    public function merkinnat() {
        return $this->belongsToMany('App\Ark\LoytoMerkinta' ,'ark_loyto_merkinnat' ,'ark_loyto_id' ,'ark_loyto_merkinta_id');
    }

    /*
     * Hakee kaikki löydön asiasanat
     */
    public function loydonAsiasanat()
    {
        return $this->hasMany('App\Ark\LoytoAsiasanat', 'ark_loyto_id');
    }

    /*
     * Löydön tapahtumat
     */
    public function tapahtumat(){
        return $this->hasMany('App\Ark\LoytoTapahtumat', 'ark_loyto_id');
    }

    public function luettelointinrohistoria() {
        return $this->hasMany('App\Ark\LoytoLuettelointinroHistoria', 'ark_loyto_id');
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

    public function images() {
        return $this->belongsToMany('App\Ark\ArkKuva', 'ark_kuva_loyto', 'id', 'ark_loyto_id');
    }

    //IRTOLÖYTÖ tai tarkastus
    public function tutkimusalue() {
        return $this->belongsTo('App\Ark\Tutkimusalue', 'ark_tutkimusalue_id');
    }

    public function sailytystila() {
        return $this->belongsTo('App\Ark\ArkSailytystila', 'vakituinen_sailytystila_id');
    }

    public function kuntoraportit() {
        return $this->hasMany('App\Ark\ArkKuntoraportti', 'ark_loyto_id', 'id');
    }

    /**
     * Joinaa tutkimuksen, tutkimusalueen ja yksikön löytöön.
     */
    private static function joinTutkimus($q){
        return $q->select('ark_loyto.id')
        ->from('ark_loyto')
        ->leftjoin('ark_tutkimusalue_yksikko', 'ark_loyto.ark_tutkimusalue_yksikko_id', '=', 'ark_tutkimusalue_yksikko.id')
        ->join('ark_tutkimusalue', function($join) { //Joinitaan mukaan irtolöytötutkimukset myös, suoraan löytö->tutkimusalue->
            $join->on('ark_tutkimusalue_yksikko.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id')
            ->orOn('ark_loyto.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id');
        })
        ->join('ark_tutkimus', 'ark_tutkimusalue.ark_tutkimus_id', '=', 'ark_tutkimus.id');
    }

    public function files() {
        return $this->belongsToMany('App\Ark\ArkTiedosto', 'ark_tiedosto_loyto', 'ark_loyto_id');
    }


    public function finnaLog() {
        return $this->hasOne('App\Ark\ArkFinnaLog', 'ark_loyto_id', 'id');
    }

}
