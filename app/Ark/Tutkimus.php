<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
/**
 * Tutkimus.
 *
 */
class Tutkimus extends Model
{
    use SoftDeletes;

    protected $table = "ark_tutkimus";

    protected $fillable = array(
        'ark_tutkimuslaji_id', 'nimi', 'tutkimuksen_lyhenne', 'rahoittaja', 'alkupvm', 'loppupvm', 'kenttatyo_alkupvm', 'kenttatyo_loppupvm',
        'loyto_paanumero', 'nayte_paanumero', 'digikuva_paanumero', 'mustavalko_paanumero', 'dia_paanumero', 'valmis', 'julkinen',
        'katuosoite', 'katunumero', 'postinumero', 'kl_koodi', 'tiivistelma', 'ark_loyto_kokoelmalaji_id',
        'ark_raportti_kokoelmalaji_id', 'ark_kartta_kokoelmalaji_id', 'ark_valokuva_kokoelmalaji_id', 'ark_nayte_kokoelmalaji_id',
        'muokattu', 'muokkaaja', 'lisatiedot', 'kenttatyojohtaja'
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
        return self::select('ark_tutkimus.*')->where('id', '=', $id);
    }

    /**
     * Kaikkien haku - Rajoitetaan katselijoiden näkemät rivit suoraan
     */
    public static function getAll() {
    	if(Auth::user()->ark_rooli == 'katselija') {
    		return self::query()->withKatselija(Auth::user()->id);
    	} else {
    		return self::select('ark_tutkimus.*');
    	}
    }
    
    /**
     * Sama metodi kuin staattinen getAllForKatselija, mutta voidaan käyttää
     * yhdessä eri hakuehtojen kanssa esimerkiksi TutkimusControllerissa.
     */
    public function scopeWithKatselija($query, $kayttaja_id) {
        return $query->where('ark_tutkimus.valmis', '=', true)
        ->where('ark_tutkimus.julkinen', '=', true)
        ->orWhereIn('ark_tutkimus.id', function($q) use($kayttaja_id) {
            $q->select('ark_tutkimus_id')
            ->from ('ark_tutkimus_kayttaja')
            ->where(function($query) use($kayttaja_id) {
                return $query->where('ark_tutkimus_kayttaja.kayttaja_id', $kayttaja_id)
                ->whereNull('ark_tutkimus_kayttaja.poistettu');
            });
        });
    }

    /**
     * Kaikkien haku katselijalle. Eli palauttaa ainoastaan sellaiset, joihin katselijalla on oikeus.
     * Katselijalla on oikeus tutkimuksiin jotka ovat valmiita ja julkisia TAI katselija-käyttäjälle
     * on erikseen annettu tutkimukseen oikeus.
     *
     * DEPRECATED: Tech upgraden yhteydessä.
     */
    public static function getAllForKatselija($kayttaja_id) {
    	return self::select('ark_tutkimus.*')
	    	->where('ark_tutkimus.valmis', '=', true)
	    	->where('ark_tutkimus.julkinen', '=', true)
    		->orWhereIn('ark_tutkimus.id', function($q) use($kayttaja_id) {
    		$q->select('ark_tutkimus_id')
    			->from ('ark_tutkimus_kayttaja')
    				->where(function($query) use($kayttaja_id) {
    				return $query->where('ark_tutkimus_kayttaja.kayttaja_id', $kayttaja_id)
    						->whereNull('ark_tutkimus_kayttaja.poistettu');
    			});
    		});
    }

    /**
     * Palauttaa tutkimusten id-listan joihin katselijalla on oikeus.
     */
    public static function getAllIdsForKatselija($kayttaja_id) {
        return self::select('ark_tutkimus.id')
        ->where('ark_tutkimus.valmis', '=', true)
        ->where('ark_tutkimus.julkinen', '=', true)
        ->orWhereIn('ark_tutkimus.id', function($q) use($kayttaja_id) {
            $q->select('ark_tutkimus_id')
            ->from ('ark_tutkimus_kayttaja')
            ->where(function($query) use($kayttaja_id) {
                return $query->where('ark_tutkimus_kayttaja.kayttaja_id', $kayttaja_id)
                ->whereNull('ark_tutkimus_kayttaja.poistettu');
            });
        });
    }

    /**
     * Suodatukset
     */

    /*
     * Tutkimustyypillä haku
     */
    public function scopeWithTutkimuslajit($query, $keyword){
        return $query->whereIn('ark_tutkimus.ark_tutkimuslaji_id', explode(',', $keyword));
    }

    /*
     * Kenttätyön aikavälillä haku vuosien mukaan.
     */
    public function scopeWithKenttatyoAikajakso($query, $alkuvuosi, $paatosvuosi) {
        if($alkuvuosi  && $paatosvuosi){
            $query->whereYear('ark_tutkimus.kenttatyo_alkupvm', '>=', $alkuvuosi)
            ->whereYear('ark_tutkimus.kenttatyo_alkupvm', '<=', $paatosvuosi)
            ->whereYear('ark_tutkimus.kenttatyo_loppupvm', '>=', $alkuvuosi)
            ->whereYear('ark_tutkimus.kenttatyo_loppupvm', '<=', $paatosvuosi);
        }
        return $query;
    }

    /*
     * Tutkimukseen liitetyn tutkijan haku
     */
    public function scopeWithTutkija($query, $keyword) {
        return $query->whereIn('ark_tutkimus.id', function($q) use ($keyword) {
            $q->select('ark_tutkimus_id')
            ->from('ark_tutkimus_kayttaja')
            ->where('kayttaja_id', '=', $keyword);
        });
    }

    // Rivimäärän rajoitus
    public function scopeWithLimit($query, $start_row, $row_count) {
        return $query->skip($start_row)->take($row_count);
    }

    /**
     * Relaatiot
     */
    public function tutkimuslaji() {
        return $this->belongsTo('App\Ark\Tutkimuslaji', 'ark_tutkimuslaji_id');
    }

    public function loytoKokoelmalaji() {
        return $this->belongsTo('App\Ark\Kokoelmalaji', 'ark_loyto_kokoelmalaji_id');
    }
    public function raporttiKokoelmalaji() {
        return $this->belongsTo('App\Ark\Kokoelmalaji', 'ark_raportti_kokoelmalaji_id');
    }
    public function karttaKokoelmalaji() {
        return $this->belongsTo('App\Ark\Kokoelmalaji', 'ark_kartta_kokoelmalaji_id');
    }
    public function valokuvaKokoelmalaji() {
        return $this->belongsTo('App\Ark\Kokoelmalaji', 'ark_valokuva_kokoelmalaji_id');
    }
    public function nayteKokoelmalaji() {
        return $this->belongsTo('App\Ark\Kokoelmalaji', 'ark_nayte_kokoelmalaji_id');
    }

    public function tutkimusalueet() {
    	return $this->hasMany('App\Ark\Tutkimusalue', 'ark_tutkimus_id');
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

    public function kiinteistotrakennukset() {
        return $this->hasMany('App\Ark\TutkimusKiinteistoRakennus', 'ark_tutkimus_id');
    }

    public function tutkimuskayttajat() {
    	return $this->hasMany('App\Ark\TutkimusKayttaja', 'ark_tutkimus_id')
    	->join('kayttaja', 'kayttaja.id', '=', 'ark_tutkimus_kayttaja.kayttaja_id')->whereNull('kayttaja.poistettu');
    }

    public function kunnatkylat() {
        return $this->hasMany('App\Ark\TutkimusKuntaKyla', 'ark_tutkimus_id');
    }

    public function tarkastus() {
        return $this->hasOne('App\Ark\Tarkastus', 'ark_tutkimus_id');
    }

    /**
     * Suodatusjärjestys
     */
    public function scopeWithOrderBy($query, $jarjestys_kentta, $jarjestys_suunta) {
        if ($jarjestys_kentta == "nimi") {
            return $query->orderBy("ark_tutkimus.nimi", $jarjestys_suunta);
        }elseif($jarjestys_kentta == "alkuvuosi") {
            return $query->orderBy("ark_tutkimus.kenttatyo_alkupvm", $jarjestys_suunta);
        }
        //todo muut kentät

        return $query->orderBy("ark_tutkimus.nimi", $jarjestys_suunta);
    }

    public function kuvat($tutkimus_id) {
        return ArkKuva::on()->fromQuery(DB::raw("select k.*
                                            from ark_kuva k
                                            left join ark_kuva_loyto kl on kl.ark_kuva_id = k.id
                                            left join ark_kuva_yksikko ky on ky.ark_kuva_id = k.id
                                            left join ark_loyto l on l.id = kl.ark_loyto_id
                                            left join ark_tutkimusalue_yksikko ty on ty.id = l.ark_tutkimusalue_yksikko_id
                                            left join ark_tutkimusalue ta on (ta.id = ty.ark_tutkimusalue_id or ta.id = l.ark_tutkimusalue_id)
                                            left join ark_tutkimus t on t.id = ta.ark_tutkimus_id
                                            where t.id = :tId
                                            and k.poistettu is null;"), array('tId' => $tutkimus_id));
    }

    //Haetaan viimeisin luettelointinumero annetun tutkimuksen kuvalle
    //Numerointi on AINA muotoa <vuosi>:<alanumero>:<juokseva_numero>
    //split_part(, , 3) luottaa tähän, muutoin ei saada juoksevaa numeroa automaattisesti.
    public static function getViimeisinLuettelointinumero($tutkimus_id) {
        // Koitetaan ensin vanhalla logiikalla, ja jos se feilaa niin sitten koitetaan uudella migroitujen kuvien tavalla...
//        DB::beginTransaction();
//        try {
            $q = DB::select(DB::raw("select k.luettelointinumero,
                                    split_part(k.luettelointinumero, ':',3)::int as juokseva
                                    from ark_kuva k
                                    where k.ark_tutkimus_id = :tId
                                    and k.poistettu is null
                                    and k.otsikko is null
                                    and k.luettelointinumero is not null
                                    order by juokseva desc
                                    limit 1;"), array('tId' => $tutkimus_id));
//        } catch(\PDOException $e) {
//            DB::rollback();
//            $q = DB::select(DB::raw("select k.luettelointinumero,
//                                    split_part(k.luettelointinumero, ':',2)::int as juokseva
//                                    from ark_kuva k
//                                    where k.ark_tutkimus_id = :tId
//                                    and k.poistettu is null
//                                    and k.otsikko is null
//                                    and k.luettelointinumero is not null
//                                    order by juokseva desc
//                                    limit 1;"), array('tId' => $tutkimus_id));
//        }
        if(sizeof($q) > 0) {
            return $q[0]->luettelointinumero;
        } else {
            return 0;
        }
    }
    
    public function files() {
        return $this->belongsToMany('App\Ark\ArkTiedosto', 'ark_tiedosto_tutkimus', 'ark_tutkimus_id');
    }
}