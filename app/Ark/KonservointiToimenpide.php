<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KonservointiToimenpide extends Model
{
    use SoftDeletes;

    protected $table = "ark_kons_toimenpide";
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
        "nimi", "aktiivinen"
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
        return $query->where('ark_kons_toimenpide.nimi', 'ILIKE', "%".$keyword."%");
    }

    public function scopeWithId($query, $keyword) {
        return $query->where('ark_kons_toimenpide.id', '=', $keyword);
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
    public function scopeWithMenetelmat($query,$keyword) {
        return $query->whereIn('ark_kons_toimenpide.id', function($q) use ($keyword) {
            $q->select('toimenpide_id')
            ->from('ark_kons_toimenpide_menetelma')
            ->whereIn('ark_kons_toimenpide_menetelma.menetelma_id', explode(',', $keyword));
        });
    }

    /*
     * Relaatiot
     */
    public function menetelmat(){
        return $this->belongsToMany('App\Ark\KonservointiMenetelma' ,'ark_kons_toimenpide_menetelma' , 'toimenpide_id', 'menetelma_id');
    }

    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }

    public function muokkaaja() {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }
}
