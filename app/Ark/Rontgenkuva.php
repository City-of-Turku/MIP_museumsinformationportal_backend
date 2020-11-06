<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
/**
 * Röntgenkuva.
 *
 */
class Rontgenkuva extends Model
{
    use SoftDeletes;

    protected $table = "ark_rontgenkuva";

    protected $fillable = array(
        'numero', 'pvm', 'kuvaaja', 'lisatiedot'
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
        return self::select('ark_rontgenkuva.*')->where('id', '=', $id);
    }

    /**
     * Kaikkien haku
     */
    public static function getAll() {
    	return self::select('ark_rontgenkuva.*');
    }

    /**
     * Relaatiot
     */
    public function loyto() {
        return $this->hasManyThrough('App\Ark\RontgenkuvaLoyto', 'App\Ark\Loyto', 'ark_loyto_id', 'id', 'id');
    }

    public function scopeWithLoytoId($query, $id) {
        return $query->join("ark_rontgenkuva_loyto", "ark_rontgenkuva_loyto.ark_rontgenkuva_id", "=", "ark_rontgenkuva.id")
        ->where("ark_rontgenkuva_loyto.ark_loyto_id", "=", $id);
    }

    public function nayte() {
        return $this->hasManyThrough('App\Ark\RontgenkuvaNayte', 'App\Ark\Nayte', 'ark_nayte_id', 'id', 'id');
    }

    public function scopeWithNayteId($query, $id) {
        return $query->join("ark_rontgenkuva_nayte", "ark_rontgenkuva_nayte.ark_rontgenkuva_id", "=", "ark_rontgenkuva.id")
        ->where("ark_rontgenkuva_nayte.ark_nayte_id", "=", $id);
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

    public function xrayloydot() {
        return $this->hasMany('App\Ark\RontgenkuvaLoyto', 'ark_rontgenkuva_id');
    }
    public function xraynaytteet() {
        return $this->hasMany('App\Ark\RontgenkuvaNayte', 'ark_rontgenkuva_id');
    }
    public function files() {
        return $this->belongsToMany('App\Ark\ArkTiedosto', 'ark_tiedosto_rontgenkuva', 'ark_rontgenkuva_id');
    }

    /**
     * Suodatusjärjestys
     */
    public function scopeWithOrderBy($query, $jarjestys_kentta, $jarjestys_suunta) {
        if ($jarjestys_kentta == "numero") {
            return $query->orderBy("ark_rontgenkuva.numero", $jarjestys_suunta);
        }
        //todo muut kentät

        return $query->orderBy("ark_rontgenkuva.id", $jarjestys_suunta);
    }


    public function scopeWithLimit($query, $start_row, $row_count) {
        return $query->skip($start_row)->take($row_count);
    }

    //Poistetaan vanhat linkatut löydöt ja lisätään uudet jotka tulee $loydot-listassa
    public static function linkita_loydot($xray_id, $loydot) {
        if(!is_null($loydot)) {
            DB::table('ark_rontgenkuva_loyto')->where('ark_rontgenkuva_id', $xray_id)->delete();
            foreach($loydot as $loyto) {
                $rl = new RontgenkuvaLoyto();
                $rl->ark_rontgenkuva_id = $xray_id;
                $rl->ark_loyto_id = $loyto["id"];
                $rl->luoja = Auth::user()->id;
                $rl->save();
            }
        }
    }

    //Poistetaan vanhat linkatut naytteet ja lisätään uudet jotka tulee $loydot-listassa
    public static function linkita_naytteet($xray_id, $naytteet) {
        if(!is_null($naytteet)) {
            DB::table('ark_rontgenkuva_nayte')->where('ark_rontgenkuva_id', $xray_id)->delete();
            foreach($naytteet as $nayte) {
                $rn = new RontgenkuvaNayte();
                $rn->ark_rontgenkuva_id = $xray_id;
                $rn->ark_nayte_id = $nayte["id"];
                $rn->luoja = Auth::user()->id;
                $rn->save();
            }
        }
    }

    public static function joinloyto($xray_id) {
        return Loyto::on()->fromQuery(DB::raw('select l.*
                from ark_rontgenkuva r
                left join ark_rontgenkuva_loyto rl on rl.ark_rontgenkuva_id = r.id
                left join ark_loyto l on rl.ark_loyto_id = l.id
                where r.id = :rid
                and r.poistettu is null;'), array('rid' => $xray_id));
    }
    public static function joinnayte($xray_id) {
        return Nayte::on()->fromQuery(DB::raw('select n.*
                from ark_rontgenkuva r
                left join ark_rontgenkuva_nayte rn on rn.ark_rontgenkuva_id = r.id
                left join ark_nayte n on rn.ark_nayte_id = n.id
                where r.id = :rid
                and r.poistettu is null;'), array('rid' => $xray_id));
    }



}