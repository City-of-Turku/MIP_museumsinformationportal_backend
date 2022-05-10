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
    public static function haeKayttajanKorit($id) {
        Log::debug("Haetaan korit " . $id);
        //$kysely = DB::table('kori AS k')
        //->select('k.*')
        /*
        $kysely = Kori::select('kori.*')
        ->leftJoin('kori_kayttajat AS kk', 'kk.kori_id', '=', 'kori.id')
        ->where('kori.luoja', '=', $id)
        ->whereNull('kori.poistettu')
        ->orWhere(function($query) use ($id) {
            $query->where("kk.kayttaja_id",'=', $id);
        })
        ->orderBy('kori.nimi', 'asc');
        */
        //$jaetut = Kori::select('kori.id', 'korityyppi_id', 'nimi', 'kuvaus', 'julkinen', 'luotu', 'luoja', 'muokattu', 'muokkaaja', 'kori_id_lista::text', 'mip_alue', 'poistaja', 'poistettu')
        $jaetut = Kori::select(DB::raw('kori.id, korityyppi_id, nimi, kuvaus, julkinen, luotu, luoja, muokattu, muokkaaja, kori_id_lista::text, mip_alue, poistaja, poistettu'))
        ->leftJoin('kori_kayttajat AS kk', 'kk.kori_id', '=', 'kori.id')
        ->where("kk.kayttaja_id",'=', $id);

        Log::debug("Jaetut ".json_encode($jaetut));

        //$kysely = Kori::select('kori.id', 'korityyppi_id', 'nimi', 'kuvaus', 'julkinen', 'luotu', 'luoja', 'muokattu', 'muokkaaja', 'kori_id_lista::text', 'mip_alue', 'poistaja', 'poistettu')
        $kysely = Kori::select(DB::raw('kori.id, korityyppi_id, nimi, kuvaus, julkinen, luotu, luoja, muokattu, muokkaaja, kori_id_lista::text, mip_alue, poistaja, poistettu'))
        ->where('kori.luoja', '=', $id)
        ->whereNull('kori.poistettu')
        ->union($jaetut)
        ->orderBy('nimi', 'asc');

        Log::debug("Queryy sql ".$kysely->toSql());
        Log::debug("Queryy ".json_encode($kysely));
        return $kysely;

        /*
        return self::select('kori.*')
        ->leftJoin('kori_kayttajat AS kk', 'kk.kori_id', '=', 'kori.id')
        ->where("kk.kayttaja_id",'=',$id)
        ->orWhere(function($query) {
            Log::debug("Orwheeree " .json_encode($query));
            $query->where('k.luoja', '=', $id)
                ->whereNull('k.poistettu');
            Log::debug("Orwheeree2 " .json_encode($query));
        })
        ->orderBy('nimi', 'asc');*/

        ////->orWhere("kk.kayttaja_id",'=',$id)
    }

    /**
     * Suodatukset
     */
    public function scopeWithKorityyppi($query, $id) {
        return $query->where('korityyppi_id', '=', $id)->whereNull('poistettu');
    }

    public function scopeWithKoriNimi($query, $nimi){
        return $query->where('nimi', 'ILIKE', $nimi)->whereNull('poistettu');
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
