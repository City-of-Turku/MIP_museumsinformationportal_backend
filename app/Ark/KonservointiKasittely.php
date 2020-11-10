<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class KonservointiKasittely extends Model
{
    use SoftDeletes;

    protected $table = "ark_kons_kasittely";
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
        "kasittelytunnus", "alkaa", "paattyy", "kuvaus"
    );

    public static function getAll($jarjestys_kentta, $jarjestys_suunta) {
        return self::select('*')->orderBy($jarjestys_kentta, $jarjestys_suunta);
    }

    public static function getSingle($id) {
        return self::select('*')->where('id', '=', $id);
    }

    /**
     * Käsittelyyn kuuluvien löytöjen ja näytteiden haku. Yhdistetty lista.
     */
    public static function haeLoydotNaytteet($kasittely_id){
        $loydot = DB::table('ark_kons_loyto')
            ->join('ark_loyto', 'ark_loyto.id', '=', 'ark_kons_loyto.ark_loyto_id')
            ->join('ark_kons_toimenpiteet', 'ark_kons_toimenpiteet.id', '=', 'ark_kons_loyto.ark_kons_toimenpiteet_id')
            ->leftjoin('ark_tutkimusalue_yksikko', 'ark_tutkimusalue_yksikko.id', '=', 'ark_loyto.ark_tutkimusalue_yksikko_id')

            ->join('ark_tutkimusalue', function($join) { //Joinitaan mukaan irtolöytötutkimukset myös, suoraan löytö->tutkimusalue->
                $join->on('ark_tutkimusalue_yksikko.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id')
                ->orOn('ark_loyto.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id');
            })
            ->join('ark_tutkimus', 'ark_tutkimus.id', '=', 'ark_tutkimusalue.ark_tutkimus_id')
            ->select(
                DB::raw("'L' as tyyppi"),
                'ark_tutkimus.id AS tutkimus_id',
                'ark_loyto.id AS loyto_nayte_id',
                'ark_kons_toimenpiteet.id AS toimenpide_id',
                'ark_tutkimus.tutkimuksen_lyhenne',
                'ark_loyto.luettelointinumero',
                'ark_kons_toimenpiteet.alkaa',
                'ark_kons_loyto.paattyy')
            ->where('ark_kons_toimenpiteet.poistettu', '=', null)
            ->where('ark_kons_loyto.ark_kons_kasittely_id', '=', $kasittely_id);

        $result = DB::table('ark_kons_nayte')
            ->join('ark_nayte', 'ark_nayte.id', '=', 'ark_kons_nayte.ark_nayte_id')
            ->join('ark_kons_toimenpiteet', 'ark_kons_toimenpiteet.id', '=', 'ark_kons_nayte.ark_kons_toimenpiteet_id')
            ->leftjoin('ark_tutkimusalue_yksikko', 'ark_tutkimusalue_yksikko.id', '=', 'ark_nayte.ark_tutkimusalue_yksikko_id')

            ->join('ark_tutkimusalue', function($join) { //Joinitaan mukaan irtolöytötutkimukset myös, suoraan löytö->tutkimusalue->
                $join->on('ark_tutkimusalue_yksikko.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id')
                ->orOn('ark_nayte.ark_tutkimusalue_id', '=', 'ark_tutkimusalue.id');
            })
            ->join('ark_tutkimus', 'ark_tutkimus.id', '=', 'ark_tutkimusalue.ark_tutkimus_id')
            ->select(
                DB::raw("'N' as tyyppi"),
                'ark_tutkimus.id AS tutkimus_id',
                'ark_nayte.id AS loyto_nayte_id',
                'ark_kons_toimenpiteet.id AS toimenpide_id',
                'ark_tutkimus.tutkimuksen_lyhenne',
                'ark_nayte.luettelointinumero',
                'ark_kons_toimenpiteet.alkaa',
                'ark_kons_nayte.paattyy')
            ->where('ark_kons_toimenpiteet.poistettu', '=', null)
            ->where('ark_kons_nayte.ark_kons_kasittely_id', '=', $kasittely_id)
            ->union($loydot); // tulokset samaan listaan
        return $result->get();
    }

    /*
     * Suodatukset
     */
    public function scopeWithLimit($query, $start_row, $row_count) {
        return $query->skip($start_row)->take($row_count);
    }

    public function scopeWithKasittelytunnus($query, $keyword) {
        return $query->where('ark_kons_kasittely.kasittelytunnus', 'ILIKE', "%".$keyword."%");
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
    public function tapahtumat(){
        return $this->hasMany('App\Ark\KonservointiKasittelytapahtumat' ,'ark_kons_kasittely_id');
    }

    public function toimenpiteet(){
        return $this->hasMany('App\Ark\KonsToimenpiteet' ,'ark_kons_kasittely_id');
    }

    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }

    public function muokkaaja() {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }

    public function files() {
        return $this->belongsToMany('App\Ark\ArkTiedosto', 'ark_tiedosto_kons_kasittely', 'ark_kons_kasittely_id');
    }
}
