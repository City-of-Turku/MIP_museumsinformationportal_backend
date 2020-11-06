<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArkTiedosto extends Model {

    use SoftDeletes;
    protected $table = "ark_tiedosto";
    protected $hidden = [
            "tiedosto_id",
            "tiedostonimi",
            "polku"
    ];
    protected $fillable = [
            "otsikko",
            "kuvaus"
    ];
    public $timestamps = true;
    const CREATED_AT = 'luotu';
    const UPDATED_AT = 'muokattu';
    const DELETED_AT = "poistettu";
    const CREATED_BY = 'luoja';
    const UPDATED_BY = 'muokkaaja';
    const DELETED_BY = 'poistaja';

    public static function getSingle($id) {
        return ArkTiedosto::select ( 'ark_tiedosto.*' )->where ( 'ark_tiedosto.id', '=', $id );
    }
    public function luoja() {
        return $this->belongsTo ( 'App\Kayttaja', 'luoja' );
    }
    public function muokkaaja() {
        return $this->belongsTo ( 'App\Kayttaja', 'muokkaaja' );
    }

    public function scopeWithLimit($query, $start_row, $row_count) {
        return $query->skip($start_row)->take($row_count);
    }

    public function scopeWithXrayId($query, $id) {
        return $query->join("ark_tiedosto_rontgenkuva", "ark_tiedosto_rontgenkuva.ark_tiedosto_id", "=", "ark_tiedosto.id")
        ->where("ark_tiedosto_rontgenkuva.ark_rontgenkuva_id", "=", $id);
    }

    public function scopeWithKohdeId($query, $id) {
        return $query->join("ark_tiedosto_kohde", "ark_tiedosto_kohde.ark_tiedosto_id", "=", "ark_tiedosto.id")
        ->where("ark_tiedosto_kohde.ark_kohde_id", "=", $id);
    }

    public function scopeWithTutkimusId($query, $id) {
        return $query->join("ark_tiedosto_tutkimus", "ark_tiedosto_tutkimus.ark_tiedosto_id", "=", "ark_tiedosto.id")
        ->where("ark_tiedosto_tutkimus.ark_tutkimus_id", "=", $id);
    }

    public function scopeWithToimenpiteetId($query, $id) {
        return $query->join("ark_tiedosto_kons_toimenpiteet", "ark_tiedosto_kons_toimenpiteet.ark_tiedosto_id", "=", "ark_tiedosto.id")
        ->where("ark_tiedosto_kons_toimenpiteet.ark_kons_toimenpiteet_id", "=", $id);
    }

    public function scopeWithKasittelyId($query, $id) {
        return $query->join("ark_tiedosto_kons_kasittely", "ark_tiedosto_kons_kasittely.ark_tiedosto_id", "=", "ark_tiedosto.id")
        ->where("ark_tiedosto_kons_kasittely.ark_kons_kasittely_id", "=", $id);
    }

    public function scopeWithLoytoId($query, $id) {
        return $query->join("ark_tiedosto_loyto", "ark_tiedosto_loyto.ark_tiedosto_id", "=", "ark_tiedosto.id")
        ->where("ark_tiedosto_loyto.ark_loyto_id", "=", $id);
    }

    public function scopeWithNayteId($query, $id) {
        return $query->join("ark_tiedosto_nayte", "ark_tiedosto_nayte.ark_tiedosto_id", "=", "ark_tiedosto.id")
        ->where("ark_tiedosto_nayte.ark_nayte_id", "=", $id);
    }

    public function scopeWithYksikkoId($query, $id) {
        return $query->join("ark_tiedosto_yksikko", "ark_tiedosto_yksikko.ark_tiedosto_id", "=", "ark_tiedosto.id")
        ->where("ark_tiedosto_yksikko.ark_yksikko_id", "=", $id);
    }

    public function tiedostorontgenkuvat() {
        return $this->hasMany('App\Ark\ArkTiedostoRontgenkuva');
    }

    public static function joinrontgenkuva($tiedosto_id) {
        return Rontgenkuva::on()->fromQuery(DB::raw('select r.*
                from ark_rontgenkuva r
                left join ark_tiedosto_rontgenkuva tr on tr.ark_rontgenkuva_id = r.id
                left join ark_tiedosto t on tr.ark_tiedosto_id = t.id
                where t.id = :tid
                and t.poistettu is null;'), array('tid' => $tiedosto_id));
    }

    public function loyto() {
        return $this->hasOne('App\Ark\ArkTiedostoLoyto', 'ark_tiedosto_id');
    }

    public function nayte() {
        return $this->hasOne('App\Ark\ArkTiedostoNayte', 'ark_tiedosto_id');
    }

    public function tiedostotutkimus() {
        return $this->hasOne('App\Ark\ArkTiedostoTutkimus', 'ark_tiedosto_id');
    }
    public function toimenpide() {
        return $this->hasOne('App\Ark\ArkTiedostoToimenpide', 'ark_tiedosto_id');
    }
    public function kasittely() {
        return $this->hasOne('App\Ark\ArkTiedostoKasittely', 'ark_tiedosto_id');
    }

    public function tiedostoloydot() {
        return $this->hasMany('App\Ark\ArkTiedostoLoyto');
    }

    public function tiedostonaytteet() {
        return $this->hasMany('App\Ark\ArkTiedostoNayte');
    }

    public function tiedostoyksikot() {
        return $this->hasMany('App\Ark\ArkTiedostoYksikko');
    }

    //Poistetaan vanhat linkatut löydöt ja lisätään uudet jotka tulee $loydot-listassa
    public static function linkita_loydot($tiedosto_id, $loydot) {
        if(!is_null($loydot)) {
            DB::table('ark_tiedosto_loyto')->where('ark_tiedosto_id', $tiedosto_id)->delete();

            foreach($loydot as $loyto) {
                $tl = new ArkTiedostoLoyto();
                $tl->ark_tiedosto_id = $tiedosto_id;
                $tl->ark_loyto_id = $loyto["id"];
                $tl->luoja = Auth::user()->id;

                $tl->save();
            }
        }
    }

    //Poistetaan vanhat linkatut naytteet ja lisätään uudet jotka tulee $loydot-listassa
    public static function linkita_naytteet($tiedosto_id, $naytteet) {
        if(!is_null($naytteet)) {
            DB::table('ark_tiedosto_nayte')->where('ark_tiedosto_id', $tiedosto_id)->delete();

            foreach($naytteet as $nayte) {
                $tn = new ArkTiedostoNayte();
                $tn->ark_tiedosto_id = $tiedosto_id;
                $tn->ark_nayte_id = $nayte["id"];
                $tn->luoja = Auth::user()->id;

                $tn->save();
            }
        }
    }

    //Poistetaan vanhat linkatut löydöt ja lisätään uudet jotka tulee $yksikot-listassa
    public static function linkita_yksikot($tiedosto_id, $yksikot) {
        if(!is_null($yksikot)) {
            DB::table('ark_tiedosto_yksikko')->where('ark_tiedosto_id', $tiedosto_id)->delete();

            foreach($yksikot as $yksikko) {
                $ty = new ArkTiedostoYksikko();
                $ty->ark_tiedosto_id = $tiedosto_id;
                $ty->ark_yksikko_id = $yksikko["id"];
                $ty->luoja = Auth::user()->id;

                $ty->save();
            }
        }
    }
}