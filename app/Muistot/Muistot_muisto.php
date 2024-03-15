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
    public $incrementing = false;

    public $timestamps = true;

    const CREATED_AT 		= 'luotu_mip';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu_mip";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

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
    
    public function muistot_vastaus() {
        return $this->hasMany('App\Muistot\Muistot_vastaus', 'muistot_muisto_id', 'prikka_id');
    }

    public function muistot_kuva() {
        return $this->hasMany('App\Muistot\Muistot_kuva', 'muistot_muisto_id', 'prikka_id');
    }

    public function muistot_aihe() {
        return $this->belongsTo('App\Muistot\Muistot_aihe', 'muistot_aihe_id', 'prikka_id');
    }

    public function muistot_henkilo() {
        return $this->belongsTo('App\Muistot\Muistot_henkilo', 'muistot_henkilo_id', 'prikka_id');
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
            ->leftJoin('muistot_aihe', 'muistot_aihe.prikka_id', '=', 'muistot_muisto.muistot_aihe_id')
            ->leftJoin('muistot_vastaus', 'muistot_vastaus.muistot_muisto_id', '=', 'muistot_muisto.prikka_id')
            ->leftJoin('muistot_henkilo', 'muistot_henkilo.prikka_id', '=', 'muistot_muisto.muistot_henkilo_id')
            ->where('muistot_muisto.prikka_id', '=', $id);
    }

    public function scopeWithPrikkaId($query, $keyword) {
        return $query->where('muistot_muisto.prikka_id', '=', $keyword);
    }

    public function scopeWithAihe($query, $keyword) {
        return $query->whereIn('muistot_muisto.muistot_aihe_id', function($q) use ($keyword) {
			
			$q->select('prikka_id')
			->from('muistot_aihe')
            ->where('muistot_aihe.aihe_fi', 'ILIKE', "%".$keyword."%")
            ->orWhere('muistot_aihe.aihe_en', 'ILIKE', "%".$keyword."%")
            ->orWhere('muistot_aihe.aihe_sv', 'ILIKE', "%".$keyword."%");
		});
    }

    public function scopeWithHenkilo($query, $keyword) {
        return $query->where('muistot_muisto.muistot_henkilo_id', '=', $keyword);
    }

    public function scopeWithAlkaa($query, $date) {
        return $query->where('muistot_muisto.alkaa', '>=', $date);
    }

    public function scopeWithLoppuu($query, $date) {
        return $query->where('muistot_muisto.loppuu', '<=', $date);
    }

    public function scopeWithPolygon($query, $polygon) {
        return $query->whereRaw(MipGis::getGeometryFieldPolygonQueryWhereString($polygon, "sijainti"));
    }

    public function scopeWithBoundingBox($query, $bbox) {
        return $query->whereRaw(MipGis::getGeometryFieldBoundingBoxQueryWhereString("sijainti", $bbox));
    }

    public function scopeWithLimit($query, $start_row, $row_count) {
        return $query->skip($start_row)->take($row_count);
    }

    public function scopeWithOrderBy($query, $bbox=null, $order_field=null, $order_direction=null) {
    	if ($order_field == "bbox_center" && !is_null($bbox)) {
    		return $query->orderByRaw(MipGis::getGeometryFieldOrderByBoundingBoxCenterString("sijainti", $bbox));
    	}

    	$order_table = "muistot_muisto";

    	/*
    	 * If orderfield AND orderDirection is given, ONLY then order the results by given field
    	 */
    	if ($order_field != null && $order_direction != null) {

    		//We may not be able to order the data by the bbox
    		if($order_field == 'bbox_center' && is_null($bbox)) {
    			$order_field = 'id';
    		}

    		$query->orderBy($order_table.'.'.$order_field, $order_direction);
    	}

    	return $query;
    }
}
