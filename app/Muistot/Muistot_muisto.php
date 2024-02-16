<?php

namespace App\Muistot;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class Muistot_muisto extends Model {

    protected $table = "muistot_muisto";
    protected $primaryKey = 'prikka_id';
    public $timestamps = false;
    public $incrementing = false;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'tapahtumapaikka'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'prikka_id',
        'muistot_aihe_id',
        'muistot_henkilo_id',
        'luotu',
        'paivitetty',
        'kuvaus',
        'alkaa',
        'loppuu',
        'poistettu',
        'ilmiannettu',
        'julkinen',
        'kieli',
        'paikka_summittainen'

    ];

    public function vastaukset() {
        return $this->hasMany('App\Muistot\Muistot_vastaus');
    }

    public function kuvat() {
        return $this->hasMany('App\Muistot\Muistot_kuva');
    }

    public function aihe() {
        return $this->belongsTo('App\Muistot\Muistot_aihe');
    }

    public function henkilo() {
        return $this->belongsTo('App\Muistot\Muistot_henkilo');
    }

    /**
     * Get all Models from DB - order by given $order_field to given $order_direction
     *
     * @version 1.0
     * @since 1.0
     */
    public static function getAll() {

        $qry = Muistot_muisto::select('muistot_muisto.prikka_id', 'muistot_muisto.muistot_aihe_id', 'muistot_muisto.muistot_henkilo_id', 
            'muistot_muisto.luotu', 'muistot_muisto.paivitetty', 'muistot_muisto.kuvaus', 'muistot_muisto.alkaa', 'muistot_muisto.loppuu', 'muistot_muisto.poistettu', 
            'muistot_muisto.ilmiannettu', 'muistot_muisto.julkinen', 'muistot_muisto.kieli', 'muistot_muisto.paikka_summittainen',
            DB::raw('ST_AsGeoJson(ST_transform(tapahtumapaikka, 4326)) as sijainti')
            )
            ->leftJoin('muistot_aihe', 'muistot_aihe.prikka_id', '=', 'muistot_muisto.muistot_aihe_id')
            ->leftJoin('muistot_vastaus', 'muistot_vastaus.muistot_muisto_id', '=', 'muistot_muisto.prikka_id');

		return $qry;
    }

    /**
     * Method to get single entity from db with given ID
     *
     * @param int $id
     * @version 1.0
     * @since 1.0
     */
    public static function getSingle($id) {
        return Muistot_muisto::select('muistot_muisto.prikka_id', 'muistot_muisto.muistot_aihe_id', 'muistot_muisto.muistot_henkilo_id', 
            'muistot_muisto.luotu', 'muistot_muisto.paivitetty', 'muistot_muisto.kuvaus', 'muistot_muisto.alkaa', 'muistot_muisto.loppuu', 'muistot_muisto.poistettu', 
            'muistot_muisto.ilmiannettu', 'muistot_muisto.julkinen', 'muistot_muisto.kieli', 'muistot_muisto.paikka_summittainen',
            DB::raw('ST_AsGeoJson(ST_transform(tapahtumapaikka, 4326)) as sijainti')
            )
            ->where('muistot_muisto.prikka_id', '=', $id);
    }
}
