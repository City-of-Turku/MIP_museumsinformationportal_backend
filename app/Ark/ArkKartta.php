<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArkKartta extends Model {

    use SoftDeletes;

    protected $table = "ark_kartta";

    protected $hidden = [
        "kartta_id",
        "polku",
        "pivot"
    ];

    protected $fillable = [
        "piirtaja",
    	"organisaatio",
        "karttanumero",
    	"kuvaus",
        "tekijanoikeuslauseke",
        "mittaukset_kentalla",
        "lisatiedot",
        "koko",
        "tyyppi",
        "mittakaava",
        "ark_tutkimus_id",
        "julkinen",
    	"polku",
    	"tiedostonimi",
    	"alkup_tiedostonimi",
		"ark_tutkimus_id"
    ];

    public $timestamps = false;


    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';


    public static function getAll() {
        //Distinct poistaa duplikaatit kartat, joita voi tulla jos yksi kartta linkataan moneen yksikköön ja löytöön tutkimuksen sisällä
        return ArkKartta::select("ark_kartta.*")->whereNotNull('ark_kartta.tyyppi')->distinct();
    }


	public function luoja() {
	    return $this->belongsTo('App\Kayttaja', 'luoja');
	}

	public function muokkaaja() {
	    return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}

	public function mittakaava() {
	    return $this->belongsTo('App\Ark\ArkMittakaava', 'mittakaava');
	}

	public function karttatyyppi() {
	    return $this->belongsTo('App\Ark\ArkKarttaTyyppi', 'tyyppi');
	}

	public function koko() {
	    return $this->belongsTo('App\Ark\ArkKarttaKoko', 'koko');
	}

	public function scopeWithLimit($query, $start_row, $row_count) {
	    return $query->skip($start_row)->take($row_count);
	}

	public function scopeWithLoytoId($query, $id) {
	    return $query->join("ark_kartta_loyto", "ark_kartta_loyto.ark_kartta_id", "=", "ark_kartta.id")
	    ->where("ark_kartta_loyto.ark_loyto_id", "=", $id);
	}

	public function scopeWithYksikkoId($query, $id) {
	    return $query->join("ark_kartta_yksikko", "ark_kartta_yksikko.ark_kartta_id", "=", "ark_kartta.id")
	    ->where("ark_kartta_yksikko.ark_yksikko_id", "=", $id);
	}

	public function scopeWithNayteId($query, $id) {
	    return $query->join("ark_kartta_nayte", "ark_kartta_nayte.ark_kartta_id", "=", "ark_kartta.id")
	    ->where("ark_kartta_nayte.ark_nayte_id", "=", $id);
	}

	public function scopeWithTutkimusId($query, $id) {
	    return $query->where("ark_tutkimus_id", "=", $id);
	}

	public function scopeWithTutkimustyyppi($query, $keyword) {
	    $keyword = explode(',', $keyword);
	    return $query->leftJoin('ark_tutkimus', 'ark_tutkimus.id', '=', 'ark_kartta.ark_tutkimus_id')
	    ->whereIn("ark_tutkimus.ark_tutkimuslaji_id", $keyword);
	}

	/*
	 * $keyword = explode(',' , $keyword);
	    return $query->whereIn('arvoalue.aluetyyppi_id', function($q) use ($keyword) {
	        $q->select('id')
	        ->from('aluetyyppi')
	        ->whereNull('aluetyyppi.poistettu')
	        ->where(function($query) use ($keyword) {
	            return $query->whereIn('aluetyyppi.id', $keyword);
	        });
	    });
	 */

	public function scopeWithTutkimusNimi($query, $keyword) {
	    return $query->leftJoin('ark_tutkimus', 'ark_tutkimus.id', '=', 'ark_kartta.ark_tutkimus_id')
	    ->where("ark_tutkimus.nimi", 'ilike', $keyword."%");
	}

	public function scopeWithTutkimusLyhenne($query, $keyword) {
	    return $query->leftJoin('ark_tutkimus', 'ark_tutkimus.id', '=', 'ark_kartta.ark_tutkimus_id')
	    ->where("ark_tutkimus.tutkimuksen_lyhenne", 'ilike', $keyword);
	}

	public function scopeWithTutkimusPaanumero($query, $keyword) {
	    return $query->leftJoin('ark_tutkimus', 'ark_tutkimus.id', '=', 'ark_kartta.ark_tutkimus_id')
	    ->where("ark_tutkimus.loyto_paanumero", '=', $keyword)
	    ->orWhere("ark_tutkimus.digikuva_paanumero", '=', $keyword)
	    ->orWhere("ark_tutkimus.mustavalko_paanumero", '=', $keyword)
	    ->orWhere("ark_tutkimus.dia_paanumero", '=', $keyword)
	    ->orWhere("ark_tutkimus.nayte_paanumero", '=', $keyword);
	}

	public function scopeWithTutkimusKlKoodi($query, $keyword) {
	    return $query->leftJoin('ark_tutkimus', 'ark_tutkimus.id', '=', 'ark_kartta.ark_tutkimus_id')
	    ->where("ark_tutkimus.kl_koodi", '=', $keyword);
	}

	public function scopeWithKarttatyyppi($query, $keyword) {
	    return $query->where("ark_kartta.tyyppi", '=', $keyword);
	}

	public function scopeWithKarttanumero($query, $keyword) {
	    return $query->where("ark_kartta.karttanumero", '=', $keyword);
	}

	public function scopeWithKuvaus($query, $keyword) {
	    return $query->where("ark_kartta.kuvaus", 'ILIKE', "%".$keyword."%");
	}

	public function scopeWithYksikkotunnus($query, $keyword) {
	    return $query->join("ark_kartta_yksikko", "ark_kartta_yksikko.ark_kartta_id", "=", "ark_kartta.id")
	    ->join("ark_tutkimusalue_yksikko", "ark_tutkimusalue_yksikko.id", "=", "ark_kartta_yksikko.ark_yksikko_id")
	    ->where("ark_tutkimusalue_yksikko.yksikkotunnus", "ilike", $keyword);
	}

	public function scopeWithAsiasanat($query, $keywords) {
	    //$keywords = explode(" ", $keywords);

	    return $query->leftJoin("ark_kartta_asiasana", "ark_kartta_asiasana.ark_kartta_id", "=", "ark_kartta.id")
	    ->where("asiasana", 'ILIKE',  $keywords."%");
	}

	public function scopeWithLoytoLuettelointinumero($query, $keyword) {
	    return $query->join("ark_kartta_loyto", "ark_kartta_loyto.ark_kartta_id", "=", "ark_kartta.id")
	    ->join("ark_loyto", "ark_kartta_loyto.ark_loyto_id", '=', 'ark_loyto.id')
	    ->where("ark_loyto.luettelointinumero", "ilike", $keyword);
	}

	public function scopeWithNayteLuettelointinumero($query, $keyword) {
	    return $query->join("ark_kartta_nayte", "ark_kartta_nayte.ark_kartta_id", "=", "ark_kartta.id")
	    ->join("ark_nayte", "ark_kartta_nayte.ark_nayte_id", '=', 'ark_nayte.id')
	    ->where("ark_nayte.luettelointinumero", "ilike", $keyword);
	}

	public function scopeWithPiirtaja($query, $keyword) {
	    return $query->where("ark_kartta.piirtaja", 'ilike', $keyword.'%');
	}

	public function scopeWithMittakaava($query, $keyword) {
	    return $query->where("ark_kartta.mittakaava", '=', $keyword);
	}

	public function scopeWithAlkupKarttanumero($query, $keyword) {
		return $query->where("ark_kartta.alkup_karttanumero", "ilike", '%'.$keyword.'%');
	}

	public function tutkimukset() {
	    return $this->belongsTo('App\Ark\Tutkimus', 'ark_tutkimus_id');
	}

	public static function getSingle($id) {
	    return ArkKartta::select('ark_kartta.*')->where('ark_kartta.id', '=', $id);
	}

	public function asiasanat() {
	    return $this->hasMany('App\Ark\ArkKarttaAsiasana');
	}

	public function karttaloydot() {
	    return $this->hasMany('App\Ark\ArkKarttaLoyto');
	}
	public function karttayksikot() {
	    return $this->hasMany('App\Ark\ArkKarttaYksikko');
	}
	public function karttanaytteet() {
	    return $this->hasMany('App\Ark\ArkKarttaNayte');
	}

	//Poistetaan vanhat linkatut löydöt ja lisätään uudet jotka tulee $loydot-listassa
	public static function linkita_loydot($kartta_id, $loydot) {
	    if(!is_null($loydot)) {
	        DB::table('ark_kartta_loyto')->where('ark_kartta_id', $kartta_id)->delete();

	        foreach($loydot as $loyto) {

	            $kl = new ArkKarttaLoyto();
	            $kl->ark_kartta_id = $kartta_id;
	            $kl->ark_loyto_id = $loyto["id"];
	            $kl->luoja = Auth::user()->id;

	            $kl->save();
	        }
	    }
	}

	//Poistetaan vanhat linkatut löydöt ja lisätään uudet jotka tulee $yksikot-listassa
	public static function linkita_yksikot($kartta_id, $yksikot) {
	    if(!is_null($yksikot)) {
	        DB::table('ark_kartta_yksikko')->where('ark_kartta_id', $kartta_id)->delete();

	        foreach($yksikot as $yksikko) {
	            $ky = new ArkKarttaYksikko();
	            $ky->ark_kartta_id = $kartta_id;
	            $ky->ark_yksikko_id = $yksikko["id"];
	            $ky->luoja = Auth::user()->id;

	            $ky->save();
	        }
	    }
	}

	//Poistetaan vanhat linkatut löydöt ja lisätään uudet jotka tulee $naytteet-listassa
	public static function linkita_naytteet($kartta_id, $naytteet) {
	    if(!is_null($naytteet)) {
	        DB::table('ark_kartta_nayte')->where('ark_kartta_id', $kartta_id)->delete();

	        foreach($naytteet as $nayte) {
	            $kn = new ArkKarttaNayte();
	            $kn->ark_kartta_id = $kartta_id;
	            $kn->ark_nayte_id = $nayte["id"];
	            $kn->luoja = Auth::user()->id;

	            $kn->save();
	        }
	    }
	}

	public static function isKarttanumeroUnique($kn, $nykyisenKartanId, $tutkimusId, $karttatyyppi) {
	    $q = DB::select(DB::raw('select count(k.id)
                                from ark_kartta k
                                where k.karttanumero = :kn
                                and k.ark_tutkimus_id = :tId
                                and k.tyyppi = :kt
                                and k.poistettu is null
                                and k.id != :nykyId;'), array('kn' => $kn, 'nykyId' => $nykyisenKartanId, 'tId' => $tutkimusId, 'kt' => $karttatyyppi));
	    if(sizeof($q) > 0) {
	        if($q[0]->count ==  0) {
	            return true;
	        }
	        return false;
	    }
	    return false;
	}

	public static function tutkimus($karttaId) {
	    return Tutkimus::on()->fromQuery(DB::raw('select t.*
                                            from ark_kartta k
                                            left join ark_kartta_loyto kl on kl.ark_kartta_id = k.id
                                            left join ark_kartta_nayte kn on kn.ark_kartta_id = k.id
                                            left join ark_kartta_yksikko ky on ky.ark_kartta_id = k.id
                                            left join ark_loyto l on l.id = kl.ark_loyto_id
                                            left join ark_tutkimusalue_yksikko ty on ty.id = l.ark_tutkimusalue_yksikko_id
                                            left join ark_tutkimusalue ta on (ta.id = ty.ark_tutkimusalue_id or ta.id = l.ark_tutkimusalue_id)
                                            left join ark_tutkimus t on t.id = ta.ark_tutkimus_id
                                            where k.id = :karttaId
                                            and k.poistettu is null;'), array('karttaId' => $karttaId));
	}

}
