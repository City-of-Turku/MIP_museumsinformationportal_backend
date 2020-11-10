<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KonservointiMateriaali extends Model
{
    use SoftDeletes;

    protected $table = "ark_kons_materiaali";
    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    /**
     * fillable elements
     */
    protected $fillable = array (
        "nimi",
        "muut_nimet",
        "kemiallinen_kaava",
        "lisatiedot",
        "aktiivinen"
    );

    public static function getAll($jarjestys_kentta, $jarjestys_suunta) {
        return self::select('*')->orderBy($jarjestys_kentta, $jarjestys_suunta);
    }

    public static function getSingle($id) {
        return self::select('*')->where('id', '=', $id);
    }

    /*
     * Suodatukset
     */
   	public function scopeWithLimit($query, $start_row, $row_count) {
		return $query->skip($start_row)->take($row_count);
	}

	public function scopeWithNimi($query, $keyword) {
	    return $query->where('ark_kons_materiaali.nimi', 'ILIKE', "%".$keyword."%");
	}

	public function scopeWithKemiallinenKaava($query, $keyword) {
	    return $query->where('ark_kons_materiaali.kemiallinen_kaava', 'ILIKE', "%".$keyword."%");
	}

	public function scopeWithMuutNimet($query, $keyword) {
	    return $query->where('ark_kons_materiaali.muut_nimet', 'ILIKE', "%".$keyword."%");
	}

	/**
	 * Suodatusjärjestys
	 */
	public function scopeWithOrderBy($query, $jarjestys_kentta, $jarjestys_suunta) {

	    if ($jarjestys_kentta == "nimi") {
	        return $query->orderBy("nimi", $jarjestys_suunta);
	    }
	}

    public function luoja() {
        return $this->belongsTo('App\Kayttaja', 'luoja');
    }

    public function muokkaaja() {
        return $this->belongsTo('App\Kayttaja', 'muokkaaja');
    }
}
