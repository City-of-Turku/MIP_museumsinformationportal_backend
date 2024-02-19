<?php

namespace App\Muistot;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class Muistot_kuva extends Model {

    protected $table = "muistot_kuva";
    protected $primaryKey = 'prikka_id';
    public $timestamps = false;
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'prikka_id',
        'muistot_muisto_id',
        'ccby',
        'ehdot',
        'polku',
        'nimi',
        'kuvaus',
        'ottohetki',
        'ottopaikka',
        'valokuvaaja'
    ];

    public function muisto() {
        return $this->belongsTo('App\Muistot\Muistot_muisto');
    }

    /**
     * Get all Models from DB - order by given $order_field to given $order_direction
     *
     * @version 1.0
     * @since 1.0
     */
    public static function getAll() {

        $qry = Muistot_kuva::select('muistot_kuva.*')
            ->leftJoin('muistot_muisto', 'muistot_muisto.prikka_id', '=', 'muistot_kuva.muistot_muisto_id');


		return $qry;
    }

    /**
     * Method to get all related to one muisto
     *
     * @param int $id
     * @version 1.0
     * @since 1.0
     */
    public static function get($id) {
        return Muistot_kuva::select('muistot_kuva.*')
            ->where('muistot_kuva.muistot_muisto_id', '=', $id);
    }

    /**
     * Method to get single entity from db with given ID
     *
     * @param int $id
     * @version 1.0
     * @since 1.0
     */
    public static function getSingle($id) {
        return Muistot_kuva::select('muistot_kuva.*')
            ->where('muistot_kuva.prikka_id', '=', $id);
    }
}
