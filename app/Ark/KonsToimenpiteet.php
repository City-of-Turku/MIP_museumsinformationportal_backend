<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Konservoinnin toimenpiteet
 *
 */
class KonsToimenpiteet extends Model
{
    use SoftDeletes;

    protected $table = 'ark_kons_toimenpiteet';
    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    /**
     * fillable elements
     */
    protected $fillable = array (
        "ark_kons_toimenpide_id", "ark_kons_menetelma_id", "ark_kons_kasittely_id", "alkaa", "lisatiedot", "menetelman_kuvaus"
    );

    public static function getAll() {
        return self::select('*');
    }

    public static function getSingle($id) {
        return self::select('*')->where('id', '=', $id);
    }

    /*
     * Suodatukset
     */
    public function scopeWithLimit($query, $start_row, $row_count) {
        return $query->skip($start_row)->take($row_count);
    }

    /*
     * Toimenpiteillä haku
     */
    public function scopeWithToimenpiteet($query, $keyword){
        return $query->whereIn('ark_kons_toimenpiteet.ark_kons_toimenpide_id', explode(',', $keyword));
    }

    /*
     * Menetelmillä haku
     */
    public function scopeWithMenetelmat($query, $keyword){
        return $query->whereIn('ark_kons_toimenpiteet.ark_kons_menetelma_id', explode(',', $keyword));
    }

    /*
     * Materiaaleilla haku
     */
    public function scopeWithMateriaalit($query, $keyword) {
        return $query->whereIn('ark_kons_toimenpiteet.id', function($q) use ($keyword) {
            $q->select('ark_kons_toimenpiteet_id')
            ->from('ark_kons_toimenpide_materiaalit')
            ->whereIn('ark_kons_toimenpide_materiaalit.ark_kons_materiaali_id', explode(',', $keyword));
        });
    }

    /*
     * Sorttaus
     */
    public function scopeWithOrderBy($query, $jarjestys_kentta, $jarjestys_suunta) {

        if ($jarjestys_kentta == "alkaa") {
            return $query->orderBy("alkaa", $jarjestys_suunta);
        }
    }

    // Löydöt
    public function scopeWithLoyto($query, $loyto_id){
        return $query->whereIn('ark_kons_toimenpiteet.id', function($q) use ($loyto_id) {
            $q->select('ark_kons_toimenpiteet_id')
            ->from('ark_kons_loyto')
            ->where('ark_kons_loyto.ark_loyto_id', '=', $loyto_id);
        });
    }

    // Näytteet
    public function scopeWithNayte($query, $nayte_id){
        return $query->whereIn('ark_kons_toimenpiteet.id', function($q) use ($nayte_id) {
            $q->select('ark_kons_toimenpiteet_id')
            ->from('ark_kons_nayte')
            ->where('ark_kons_nayte.ark_nayte_id', '=', $nayte_id);
        });
    }

    /*
     * Relaatiot
     */
    public function toimenpide(){
        return $this->hasOne('App\Ark\KonservointiToimenpide', 'id', 'ark_kons_toimenpide_id');
    }

    public function menetelma(){
        return $this->hasOne('App\Ark\KonservointiMenetelma', 'id', 'ark_kons_menetelma_id');
    }

    public function materiaalit(){
        return $this->belongsToMany('App\Ark\KonservointiMateriaali' ,'ark_kons_toimenpide_materiaalit' ,'ark_kons_toimenpiteet_id' ,'ark_kons_materiaali_id');
    }

    public function loydot(){
        return $this->hasMany('App\Ark\KonservointiLoyto' ,'ark_kons_toimenpiteet_id');
    }

    public function naytteet(){
        return $this->hasMany('App\Ark\KonservointiNayte' ,'ark_kons_toimenpiteet_id');
    }

    public function kasittely(){
        return $this->hasOne('App\Ark\KonservointiKasittely', 'id', 'ark_kons_kasittely_id');
    }

    public function tekija() {
        return $this->belongsTo('App\Kayttaja', 'tekija');
    }

    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }

    public function muokkaaja() {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }
    
    public function files() {
        return $this->belongsToMany('App\Ark\ArkTiedosto', 'ark_tiedosto_kons_toimenpiteet', 'ark_kons_toimenpiteet_id');
    }
}
