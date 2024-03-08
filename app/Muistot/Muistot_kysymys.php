<?php

namespace App\Muistot;

use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class muistot_kysymys extends Model {

    protected $table = "muistot_kysymys";
    protected $primaryKey = 'prikka_id';
    public $incrementing = false;

    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'prikka_id',
        'muistot_aihe_id',
        'teksti_fi',
        'teksti_en',
        'teksti_sv'

    ];

    public function aihe()
    {
        $this->belongsTo('App\Muistot\Muistot_aihe', 'muistot_aihe_id', 'prikka_id');
    }

    public function vastaukset()
    {
        $this->hasMany('App\Muistot\Muistot_vastaus', 'muistot_vastaus_id', 'prikka_id');
    }

    /**
     * Get all Models from DB - order by given $order_field to given $order_direction
     *
     * @version 1.0
     * @since 1.0
     */
    public static function getAll() {

        $qry = muistot_kysymys::select('muistot_kysymys.*')
            ->leftJoin('muistot_aihe', 'muistot_aihe.prikka_id', '=', 'muistot_kysymys.muistot_aihe_id');

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
        return muistot_kysymys::select('muistot_kysymys.*')
            ->where('muistot_kysymys.prikka_id', '=', $id);
    }
}
