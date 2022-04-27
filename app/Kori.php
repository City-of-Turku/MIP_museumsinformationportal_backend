<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

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
        return self::select('kori.*');
    }

    /**
     * Käyttäjän korit
     */
    public static function haeKayttajanKorit($id) {
        return self::select('kori.*')->where('luoja', '=', $id)->orderBy('nimi', 'asc');
    }
    /**
     * Suodatukset
     */
    public function scopeWithKorityyppi($query, $id) {
        return $query->where('korityyppi_id', '=', $id);
    }

    public function scopeWithKoriNimi($query, $nimi){
        return $query->where('nimi', 'ILIKE', $nimi);
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
