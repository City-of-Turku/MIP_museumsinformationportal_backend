<?php

namespace App\Muistot;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class Muistot_vastaus extends Model {

    protected $table = "muistot_vastaus";
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'muistot_muisto_id',
        'muistot_kysymys_id',
        'vastaus'

    ];

    public function kysymys() {
        return $this->belongsTo('App\Muistot\Muistot_kysymys');
    }

    public function muisto() {
        return $this->belongsTo('App\Muistot\Muistot_aihe');
    }

    /**
     * Get all Models from DB - order by given $order_field to given $order_direction
     *
     * @version 1.0
     * @since 1.0
     */
    public static function getAll() 
    {
        $qry = Muistot_vastaus::select('muistot_vastaus.*');
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
        return Muistot_vastaus::select('muistot_vastaus.*')
            ->where('muistot_vastaus.muistot_muisto_id', '=', $id);
    }
}