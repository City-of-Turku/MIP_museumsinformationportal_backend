<?php

namespace App\Muistot;

use DateTime;
use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Muistot_aihe extends Model {

    protected $table = "muistot_aihe";
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

    /**
     * Method to get the muistot of this Aihe
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * @version 1.0
     * @since 1.0
     */
    public function muistot()
    {
        return $this->hasMany('App\Muistot\Muistot_muisto', 'muistot_aihe_id', 'prikka_id')
            ->addSelect('*',DB::raw(MipGis::getGeometryFieldQueryString("tapahtumapaikka", "sijainti"))); // TODO
    }

    public function muistot_kysymys() {
      return $this->hasMany('App\Muistot\Muistot_kysymys', 'muistot_aihe_id', 'prikka_id');
    }


    /**
     * Get all Models from DB - order by given $order_field to given $order_direction
     *
     * @version 1.0
     * @since 1.0
     */
    public static function getAll() {

        $qry = Muistot_aihe::select('muistot_aihe.prikka_id', 'muistot_aihe.aukeaa', 'muistot_aihe.sulkeutuu', 'muistot_aihe.aihe_fi', 'muistot_aihe.aihe_en', 'muistot_aihe.aihe_sv',
            'esittely_fi', 'muistot_aihe.esittely_en', 'muistot_aihe.esittely_sv', 'aiheen_vari');

		return $qry;
    }

    public static function getAllForVisitor($kayttaja_id) {
        $qry = Muistot_aihe::select('muistot_aihe.prikka_id', 'muistot_aihe.aukeaa', 'muistot_aihe.sulkeutuu', 'muistot_aihe.aihe_fi', 'muistot_aihe.aihe_en', 'muistot_aihe.aihe_sv',
            'esittely_fi', 'muistot_aihe.esittely_en', 'muistot_aihe.esittely_sv', 'muistot_aihe.aiheen_vari')
            ->join('muistot_aihe_kayttaja', 'muistot_aihe.prikka_id', '=', 'muistot_aihe_kayttaja.muistot_aihe_id')
            ->whereNull('muistot_aihe_kayttaja.poistettu')
            ->where("muistot_aihe_kayttaja.kayttaja_id", "=", $kayttaja_id);

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

    public function scopeWithPrikkaId($query, $keyword) {
        return $query->where('muistot_aihe.prikka_id', '=', $keyword);
    }

    public function scopeWithAukeaa($query, $date) {
        return $query->where('muistot_aihe.aukeaa', '>=', $date);
    }

    public function scopeWithSulkeutuu($query, $date) {
        $dateObject = new DateTime($date);
        $dateObject->modify('+1 day');
        $nextDay = $dateObject->format('Y-m-d');
        return $query->where('muistot_aihe.sulkeutuu', '<', $nextDay);
    }

    public function scopeWithAihe($query, $keyword) {
        return $query->where('muistot_aihe.aihe_fi', 'ILIKE', "%".$keyword."%")
            ->orWhere('muistot_aihe.aihe_en', 'ILIKE', "%".$keyword."%")
            ->orWhere('muistot_aihe.aihe_sv', 'ILIKE', "%".$keyword."%");
    }

    public function scopeWithOrderBy($query, $bbox=null, $order_field=null, $order_direction=null) {

    	$order_table = "muistot_aihe";

    	/*
    	 * If orderfield AND orderDirection is given, ONLY then order the results by given field
    	 */
    	if ($order_field != null && $order_direction != null) {

    		$query->orderBy($order_table.'.'.$order_field, $order_direction);
    	}

    	return $query;
    }

    public function scopeWithLimit($query, $start_row, $row_count) {
        return $query->skip($start_row)->take($row_count);
    }
}
