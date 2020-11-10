<?php

namespace App\Rak;

use App\Kayttaja;
use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventointiprojektiArvoalue extends Model {
    
    use SoftDeletes;
	
	protected $table = "inventointiprojekti_arvoalue";
	public $timestamps = false;
	protected $hidden = [];	
	protected $primaryKey = 'id';
	public $incrementing = false;
	
	const DELETED_BY		= 'poistaja';
	const DELETED_AT 		= "poistettu";
	
	protected $fillable = array('inventointiprojekti_id', 'arvoalue_id', 'inventoija_nimi', 'inventoija_id', 'inventoija_organisaatio', 'inventointipaiva', 'kenttapaiva');

	public function arvoalue() {
		return $this->belongsTo('App\Rak\Arvoalue', 'arvoalue_id')->addSelect('*', 
						DB::raw(MipGis::getGeometryFieldQueryString("arvoalue.keskipiste", "sijainti")),
						DB::raw(MipGis::getGeometryFieldQueryString("arvoalue.aluerajaus", "alue")));
	}
	
	public function inventointiprojekti() {
		return $this->belongsTo('App\Rak\Inventointiprojekti');
	}
	
	public function inventoija() {
		return $this->belongsTo('App\Kayttaja', 'inventoija_id');
	}
	
	public function scopeWithInventointiprojektiOrInventoija($query, $inventointiprojekti_id, $inventoija_id) {
	
		if($inventointiprojekti_id && $inventointiprojekti_id != 'null') {
			$query->where('inventointiprojekti_id', '=', $inventointiprojekti_id);
		}
		if($inventoija_id && $inventoija_id != 'null') {
			$query->where('inventoija_id', '=', $inventoija_id);
		}
	
		return $query;
	}
	
	/*
	 * Get only one row from the database. The row that we want is the newest row (= biggest id value).
	 */
	public static function getInventointipaiva($inventoryproject_id, $entity_id, $inventoija_id) {
		return self::select('*')->where('inventointiprojekti_id', '=', $inventoryproject_id)
		->where('arvoalue_id', '=', $entity_id)		
		->where('inventoija_id', '=', $inventoija_id)
		->orderby('id', 'desc')->first();
	}	
	
	/*
	 * Get only one row from the database. The row that we want is the newest row (= biggest id value).
	 */
	public static function getKenttapaiva($inventoryproject_id, $entity_id, $inventoija_id) {
		return self::select('*')->where('inventointiprojekti_id', '=', $inventoryproject_id)
		->where('arvoalue_id', '=', $entity_id)
		->where('inventoija_id', '=', $inventoija_id)
		->orderby('id', 'desc')->first();
	}
	
	public static function insertInventointitieto($inventointiprojekti, $arvoalue_id) {
		$inventointiprojektiArvoalue = new InventointiprojektiArvoalue($inventointiprojekti);
		$inventointiprojektiArvoalue->arvoalue_id= $arvoalue_id;
		
		//Take the information from the user
		$inventoija = Kayttaja::where('id', '=', $inventointiprojekti["inventoija_id"])->first();
		
		$inventointiprojektiArvoalue->inventoija_nimi = $inventoija->etunimi . " " . $inventoija->sukunimi;
		$inventointiprojektiArvoalue->inventoija_organisaatio = $inventoija->organisaatio;
		
		$inventointiprojektiArvoalue->save();
	}
	
	public static function deleteInventointitieto($inventointiprojektiId, $arvoalueId, $inventoijaId) {
	    $ipk = InventointiprojektiArvoalue::where('inventointiprojekti_id', $inventointiprojektiId)
	    ->where('arvoalue_id', $arvoalueId)
	    ->where('inventoija_id', $inventoijaId)
	    ->update(['poistaja' => Auth::user()->id, 'poistettu' => \Carbon\Carbon::now()]);
	}
}
