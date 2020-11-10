<?php

namespace App\Rak;

use App\Kayttaja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class InventointiprojektiKiinteisto extends Model {
	
    use SoftDeletes;
    
	protected $table = "inventointiprojekti_kiinteisto";
	public $timestamps = false;
	protected $hidden = [];	
	protected $primaryKey = 'id';
	public $incrementing = false;
		
	const DELETED_BY		= 'poistaja';	
	const DELETED_AT 		= "poistettu";
	
	protected $fillable = array('inventointiprojekti_id', 'kiinteisto_id', 'inventoija_nimi', 'inventoija_id', 'inventoija_organisaatio', 'inventointipaiva', 'kenttapaiva');
	
	/*
	 * Get only one row from the database. The row that we want is the newest row (= biggest id value).
	 */
	public static function getInventointipaiva($inventoryproject_id, $entity_id, $inventoija_id) {
		return self::select('*')->where('inventointiprojekti_id', '=', $inventoryproject_id)
			->where('kiinteisto_id', '=', $entity_id)
			->where('inventoija_id', '=', $inventoija_id)
			->orderby('id', 'desc')->first();		
	}
	/*
	 * Get only one row from the database. The row that we want is the newest row (= biggest id value).
	 */
	public static function getKenttapaiva($inventoryproject_id, $entity_id, $inventoija_id) {
		return self::select('*')->where('inventointiprojekti_id', '=', $inventoryproject_id)
			->where('kiinteisto_id', '=', $entity_id)
			->where('inventoija_id', '=', $inventoija_id)
			->orderby('id', 'desc')->first();
	}
	
	public static function insertInventointitieto($inventointiprojekti, $kiinteisto_id) {			
		$inventointiprojektiKiinteisto = new InventointiprojektiKiinteisto($inventointiprojekti);
		$inventointiprojektiKiinteisto->kiinteisto_id = $kiinteisto_id;
		
		//Take the information from the user
		$inventoija = Kayttaja::where('id', '=', $inventointiprojekti["inventoija_id"])->first();
		
		$inventointiprojektiKiinteisto->inventoija_nimi = $inventoija->etunimi . " " . $inventoija->sukunimi;
		$inventointiprojektiKiinteisto->inventoija_organisaatio = $inventoija->organisaatio;
		
		$inventointiprojektiKiinteisto->save();
	}
	
	public static function deleteInventointitieto($inventointiprojektiId, $kiinteistoId, $inventoijaId) {
		$ipk = InventointiprojektiKiinteisto::where('inventointiprojekti_id', $inventointiprojektiId)
			->where('kiinteisto_id', $kiinteistoId)
			->where('inventoija_id', $inventoijaId)
			->update(['poistaja' => Auth::user()->id, 'poistettu' => \Carbon\Carbon::now()]);		
	}
}
