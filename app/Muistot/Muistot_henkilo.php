<?php

namespace App\Muistot;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class Muistot_henkilo extends Model {

    protected $table = "muistot_henkilo";
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
        'nimimerkki',
        'etunimi',
        'sukunimi',
        'sahkoposti',
        'syntymavuosi'

    ];

    public function muistot()
    {
        $this->hasMany('App\Muistot\Muistot_muisto');
    }

    /**
     * Get all Models from DB - order by given $order_field to given $order_direction
     *
     * @version 1.0
     * @since 1.0
     */
    public static function getAll() {

        $qry = Muistot_henkilo::select('muistot_henkilo.*');

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
        return Muistot_henkilo::select('muistot_henkilo.*')
            ->where('muistot_henkilo.prikka_id', '=', $id);
    }
}
