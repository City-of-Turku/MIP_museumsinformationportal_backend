<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


/**
 * Korin tiedot. kori_id_lista = koriin tallennetut id:t. Korityyppi kertoo minkä tyyppistä tietoa sisältää. esim löytöjä
 */
class Kori extends Model
{
    protected $table = "kori";

    protected $fillable = array(
        'korityyppi_id', 'nimi', 'kuvaus', 'julkinen', 'kori_id_lista', 'mip_alue'
    );

    /**
     * Id listan cast
     */
    protected $casts = ['kori_id_lista' => 'array'];

    /*
     * Aikaleimat päivitetään automaattisesti by Laravel
     */
    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';

    const DELETED_AT 		= "poistettu";
    const DELETED_BY		= 'poistaja';

    /**
     * Haku id:n mukaan.
     */
    public static function getSingle($id) {
        return self::select('kori.*')->where('id', '=', $id);
    }

    /**
     * Palauttaa korin id listan
     */
    public static function haeKoriIdLista($id) {
        return self::select('kori.kori_id_lista')->where('id', '=', $id);
    }

    /**
     * Kaikkien haku
     */
    public static function getAll($id) {
        return self::select('kori.*')->whereNull('poistettu');
    }

    /**
     * Käyttäjän korit
     */
    public static function haeKayttajanKorit($id, $korityyppi, $korijako, $nimi, $mip_alue, $tarkka) {
        switch ($korijako) {
            case 1: //Omat
                $query = Kori::select(DB::raw('kori.id, korityyppi_id, nimi, kuvaus, julkinen,kori.luotu, kori.luoja, kori.muokattu, kori.muokkaaja, kori_id_lista::text, mip_alue, kori.poistaja, kori.poistettu, kk.museon_kori'), 'kk.kayttaja_id_lista')
                ->leftJoin('kori_kayttajat AS kk', 'kk.kori_id', '=', 'kori.id')
                ->whereNull('kori.poistettu')
                ->where('kori.luoja', '=', $id)
                ->orderBy('nimi', 'asc');

                if($nimi){
                    $query->withKoriNimi($nimi, false);
                }
                break;
            case 2: //Jaetut
                $query = Kori::select(DB::raw('kori.id, korityyppi_id, nimi, kuvaus, julkinen,kori.luotu, kori.luoja, kori.muokattu, kori.muokkaaja, kori_id_lista::text, mip_alue, kori.poistaja, kori.poistettu, kk.museon_kori'), 'kk.kayttaja_id_lista')
                ->leftJoin('kori_kayttajat AS kk', function($join) use($id){
                    $join->on('kk.kori_id', '=', 'kori.id');
                })
                ->leftJoin('kayttaja as k', function($join){
                    $join->on('kk.museon_kori', '=', DB::raw('true'))
                    ->whereIn('k.ark_rooli', ["pääkäyttäjä", "tutkija"]);
                })
                ->whereNull('kori.poistettu')
                ->where(function($subwhere) use($id){
                    $subwhere->whereRaw('kk.kayttaja_id_lista @> ?', $id)
                    ->orWhere('k.id', '=', $id);
                })
                ->where('kori.luoja', '!=', DB::raw($id))
                ->where('kori.mip_alue', '=', $mip_alue);


                if($korityyppi != null){
                    $query->withKorityyppi($korityyppi);
                }

                if($nimi){
                    $query->withKoriNimi($nimi, false);
                }

                $query->groupBy('nimi', 'kori.id', 'kk.museon_kori', 'kk.kayttaja_id_lista');
                break;
            case 3: //Kaikki
                $jaetut = Kori::select(DB::raw('kori.id, korityyppi_id, nimi, kuvaus, julkinen,kori.luotu, kori.luoja, kori.muokattu, kori.muokkaaja, kori_id_lista::text, mip_alue, kori.poistaja, kori.poistettu, kk.museon_kori'), 'kk.kayttaja_id_lista')
                ->leftJoin('kori_kayttajat AS kk', function($join) use($id){
                    $join->on('kk.kori_id', '=', 'kori.id');
                })
                ->leftJoin('kayttaja as k', function($join){
                    $join->on('kk.museon_kori', '=', DB::raw('true'))
                    ->whereIn('k.ark_rooli', ["pääkäyttäjä", "tutkija"]);
                })
                ->whereNull('kori.poistettu')
                ->where(function($subwhere) use($id){
                    $subwhere->whereRaw('kk.kayttaja_id_lista @> ?', $id)
                    ->orWhere('k.id', '=', $id);
                })
                ->where('kori.luoja', '!=', DB::raw($id))
                ->where('kori.mip_alue', '=', $mip_alue);

                if($korityyppi != null){
                    $jaetut->withKorityyppi($korityyppi);
                }

                if($nimi){
                    $jaetut->withKoriNimi($nimi, $tarkka);
                }

                $jaetut->groupBy('nimi', 'kori.id', 'kk.museon_kori', 'kk.kayttaja_id_lista');

                $query = Kori::select(DB::raw('kori.id, korityyppi_id, nimi, kuvaus, julkinen,kori.luotu, kori.luoja, kori.muokattu, kori.muokkaaja, kori_id_lista::text, mip_alue, kori.poistaja, kori.poistettu, kk.museon_kori'), 'kk.kayttaja_id_lista')
                ->leftJoin('kori_kayttajat AS kk', 'kk.kori_id', '=', 'kori.id')
                ->whereNull('kori.poistettu')
                ->where('kori.luoja', '=', $id)
                ->union($jaetut)
                ->orderBy('nimi', 'asc');

                if($nimi){
                    $query->withKoriNimi($nimi, $tarkka);
                }
                break;
        }
        return $query;
    }

    /**
     * Suodatukset
     */
    public function scopeWithKorityyppi($query, $id) {
        return $query->where('korityyppi_id', '=', $id);
    }

    public function scopeWithKoriNimi($query, $nimi, $tarkka){
        if ($tarkka){
            return $query->where('nimi', '=', $nimi);
        }
        else{
            return $query->where('nimi', 'ILIKE', $nimi."%");
        }
    }

    /**
     * Relaatiot
     */
    public function korityyppi() {
        return $this->belongsTo('App\Korityyppi', 'korityyppi_id');
    }

    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }
    public function muokkaaja() {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }
}
