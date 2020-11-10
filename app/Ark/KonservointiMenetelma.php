<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KonservointiMenetelma extends Model
{
    use SoftDeletes;

    protected $table = "ark_kons_menetelma";
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
        "nimi", "kuvaus", "aktiivinen"
    );

    public static function getAll($jarjestys_kentta, $jarjestys_suunta) {
        return self::select('*')->orderBy($jarjestys_kentta, $jarjestys_suunta);
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

    public function scopeWithNimi($query, $keyword) {
        return $query->where('ark_kons_menetelma.nimi', 'ILIKE', "%".$keyword."%");
    }

    public function scopeWithKuvaus($query, $keyword) {
        return $query->where('ark_kons_menetelma.kuvaus', 'ILIKE', "%".$keyword."%");
    }

    /*
     * Suodatusjärjestys
     */
    public function scopeWithOrderBy($query, $jarjestys_kentta, $jarjestys_suunta) {

        if ($jarjestys_kentta == "nimi") {
            return $query->orderBy("nimi", $jarjestys_suunta);
        }
    }

    // Haku välitaulusta
    public function scopeWithToimenpiteet($query,$keyword) {
        return $query->whereIn('ark_kons_menetelma.id', function($q) use ($keyword) {
            $q->select('menetelma_id')
            ->from('ark_kons_toimenpide_menetelma')
            ->whereIn('ark_kons_toimenpide_menetelma.toimenpide_id', explode(',', $keyword));
        });
    }

    /*
     * Relaatiot
     */
    public function toimenpiteet(){
        return $this->belongsToMany('App\Ark\KonservointiToimenpide' ,'ark_kons_toimenpide_menetelma' , 'menetelma_id', 'toimenpide_id');
    }

    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }

    public function muokkaaja() {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }
}
