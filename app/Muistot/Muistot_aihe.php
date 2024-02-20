<?php

namespace App\Muistot;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class Muistot_aihe extends Model {

    protected $table = "muistot_aihe";
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
        'aukeaa',
        'sulkeutuu',
        'aihe_fi',
        'aihe_en',
        'aihe_sv',
        'esittely_fi',
        'esittely_en',
        'esittely_sv',
        'aiheen_vari'
    ];

    public function muistot()
    {
        $this->hasMany('App\Muistot\Muistot_muisto');
    }

    public function kysymykset()
    {
        $this->hasMany('App\Muistot\Muistot_kysymys');
    }

    /**
     * Get all Models from DB - order by given $order_field to given $order_direction
     *
     * @version 1.0
     * @since 1.0
     */
    public static function getAll() {

        $qry = Muistot_aihe::select('muistot_aihe.prikka_id', 'muistot_aihe.aukeaa', 'muistot_aihe.sulkeutuu', 'muistot_aihe.aihe_fi', 'muistot_aihe.aihe_en', 'muistot_aihe.aihe_sv', 
            'esittely_fi', 'muistot_aihe.esittely_en', 'muistot_aihe.esittely_sv', 'aiheen_vari')
            ->leftJoin('muistot_kysymys', 'muistot_aihe.prikka_id', '=', 'muistot_kysymys.muistot_aihe_id');

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
        return Muistot_aihe::select('muistot_aihe.*')
            ->where('muistot_aihe.prikka_id', '=', $id);
    }
}
