<?php

namespace App\Rak;

use App\Kayttaja;
use Illuminate\Support\Facades\DB;

/*
 * Hakulistojen Kuva-suodatin (1 = kyllä, 2 = ei, muut = ei suodatusta).
 * Sama ehto kuin Kuva::getTunnistekuvaUrls: välitaulun rivi (kuva_<taulu>.<taulu>_id) ja kuva.poistettu is null.
 * Ilman kuvien katseluoikeutta kaikki tulkitaan kuvattomiksi.
 * Scopena aiemmat ehdot (myös orWhere) ryhmitellään omiin sulkuihinsa, joten suodatin ei ohitu.
 */
trait KuvaSuodatin {

	public function scopeWithKuva($query, $valinta) {
		if($valinta != 1 && $valinta != 2) {
			return $query;
		}

		$taulu = $this->getTable();

		if(!Kayttaja::hasPermission('rakennusinventointi.kuva.katselu')) {
			return $valinta == 1 ? $query->whereRaw('false') : $query;
		}

		$ehto = function($q) use ($taulu) {
			$q->select(DB::raw(1))
			->from('kuva_'.$taulu)
			->join('kuva', 'kuva.id', '=', 'kuva_'.$taulu.'.kuva_id')
			->whereColumn('kuva_'.$taulu.'.'.$taulu.'_id', $taulu.'.id')
			->whereNull('kuva.poistettu');
		};

		return $valinta == 1 ? $query->whereExists($ehto) : $query->whereNotExists($ehto);
	}
}
