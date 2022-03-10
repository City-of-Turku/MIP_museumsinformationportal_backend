<?php

namespace App\Rak;

use App\Kayttaja;
use App\Library\Gis\MipGis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventointiprojektiAlue extends Model {
    
    use SoftDeletes;
	
	protected $table = "inventointiprojekti_alue";
	public $timestamps = false;
	protected $hidden = [];	
	protected $primaryKey = 'id';
	
	const DELETED_BY		= 'poistaja';
	const DELETED_AT 		= "poistettu";
	
	protected $fillable = array('inventointiprojekti_id', 'alue_id', 'inventoija_nimi', 'inventoija_id', 'inventoija_organisaatio', 'inventointipaiva', 'kenttapaiva');
	
	public function _alue() {
		return $this->belongsTo('App\Rak\Alue', 'alue_id')->addSelect('*', 
						DB::raw(MipGis::getGeometryFieldQueryString("alue.keskipiste", "sijainti")),
						DB::raw(MipGis::getGeometryFieldQueryString("alue.aluerajaus", "alue")));
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
		
		$query->whereIn('alue_id', function($q) {
			$q->select('id')
			->from('alue')
			->whereNull('poistettu');
		});
			
		return $query;
	}
	
	/*
	 * Get only one row from the database. The row that we want is the newest row (= biggest id value).
	 */
	public static function getInventointipaiva($inventoryproject_id, $entity_id, $inventoija_id) {
		return self::select('*')->where('inventointiprojekti_id', '=', $inventoryproject_id)
		->where('alue_id', '=', $entity_id)
		->where('inventoija_id', '=', $inventoija_id)
		->orderby('id', 'desc')->first();
	}	
	
	/*
	 * Get only one row from the database. The row that we want is the newest row (= biggest id value).
	 */
	public static function getKenttapaiva($inventoryproject_id, $entity_id, $inventoija_id) {
		return self::select('*')->where('inventointiprojekti_id', '=', $inventoryproject_id)
		->where('alue_id', '=', $entity_id)
		->where('inventoija_id', '=', $inventoija_id)
		->orderby('id', 'desc')->first();
	}
	
	public static function insertInventointitieto($inventointiprojekti, $alue_id) {
		$inventointiprojektiAlue = new InventointiprojektiAlue($inventointiprojekti);
		$inventointiprojektiAlue->alue_id = $alue_id;
		
		//Take the information from the user
		if (isset($inventointiprojekti["inventoija_id"])) {
			$inventoija = Kayttaja::where('id', '=', $inventointiprojekti["inventoija_id"])->first();
		
			$inventointiprojektiAlue->inventoija_nimi = $inventoija->etunimi . " " . $inventoija->sukunimi;
			$inventointiprojektiAlue->inventoija_organisaatio = $inventoija->organisaatio;
		

			$inventointiprojektiAlue->save();
		}
	}
	
	public static function deleteInventointitieto($inventointiprojektiId, $alueId, $inventoijaId) {
	    $ipk = InventointiprojektiAlue::where('inventointiprojekti_id', $inventointiprojektiId)
	    ->where('alue_id', $alueId)
	    ->where('inventoija_id', $inventoijaId)
	    ->update(['poistaja' => Auth::user()->id, 'poistettu' => \Carbon\Carbon::now()]);
	}
}
