<?php

namespace App\Muistot;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class Muistot_kuva extends Model {

    protected $table = "muistot_kuva";
    protected $primaryKey = null;
    public $timestamps = false;
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'muistot_muisto_id',
        'ccby',
        'ehdot',
        'kuva',
        'kuvaus',
        'ottohetki',
        'ottopaikka',
        'valokuvaaja'
    ];

    public function kysymys() {
        return $this->belongsTo('App\Muistot\Muistot_muisto');
    }

    /**
     * Get all Models from DB - order by given $order_field to given $order_direction
     *
     * @version 1.0
     * @since 1.0
     */
    public static function getAll() {

        $qry = Muistot_vastaus::select('muistot_kuva.*');

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
        return Muistot_vastaus::select('muistot_kuva.*')
            ->where('muistot_vastaus.muistot_muisto_id', '=', $id);
    }
}
