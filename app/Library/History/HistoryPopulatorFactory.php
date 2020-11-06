<?php
namespace App\Library\History;

class HistoryPopulatorFactory {

	public static function getPopulator($tablename) {
		switch ($tablename) {
			case "kiinteisto":
				return new KiinteistoHistoryPopulator();
				break;
			case "kiinteisto_aluetyyppi":
				return new KiinteistoAluetyyppiHistoryPopulator();
				break;
			case "kiinteisto_historiallinen_tilatyyppi":
				return new KiinteistoHistoriallinenTilaTyyppiPopulator();
				break;
			case "kiinteisto_kiinteistokulttuurihistoriallinenarvo":
				return new KiinteistoKulttuuriHistoriallinenArvoHistoryPopulator();
				break;
			case "kiinteisto_suojelutyyppi":
				return new KiinteistoSuojeluTyyppiHistoryPopulator();
				break;
			case "inventointiprojekti_kiinteisto":
				return new KiinteistoInventointiProjektiHistoryPopulator();
				break;
			case "kuva_kiinteisto":
				return new KuvaHistoryPopulator();
				break;
			case "tiedosto_kiinteisto":
				return new TiedostoHistoryPopulator();
				break;

			case "rakennus":
				return new RakennusHistoryPopulator();
				break;
			case "rakennus_rakennustyyppi":
				return new RakennusRakennusTyyppiHistoryPopulator();
				break;
			case "rakennus_kattotyyppi":
				return new RakennusKattotyyppiHistoryPopulator();
			case "rakennus_alkuperainenkaytto":
			case "rakennus_nykykaytto":
				return new RakennusKayttotarkoitusHistoryPopulator();
				break;
			case "rakennus_runkotyyppi":
				return new RakennusRunkotyyppiHistoryPopulator();
				break;;
			case "rakennus_vuoraustyyppi":
				return new RakennusVuoraustyyppiHistoryPopulator();
				break;
			case "rakennus_katetyyppi":
				return new RakennusKatetyyppiHistoryPopulator();
				break;
			case "rakennus_rakennuskulttuurihistoriallinenarvo":
				return new RakennusKulttuuriHistoriallinenArvoHistoryPopulator();
				break;
			case "rakennus_perustustyyppi":
				return new RakennusPerustustyyppiHistoryPopulator();
				break;
			case "rakennus_suojelutyyppi":
				return new RakennusSuojeluTyyppiHistoryPopulator();
				break;
			// rakennus_muutosvuosi - no need, no link to other table
			// rakennus_osoite - no need, no link to other table
			// rakennus_omistaja - no need, no link to other table
			case "kuva_rakennus":
				return new KuvaHistoryPopulator();
				break;
			case "tiedosto_rakennus":
				return new TiedostoHistoryPopulator();
				break;
			case "suunnittelija_rakennus":
				return new SuunnittelijaRakennusHistoryPopulator();
				break;

			case "kyla":
				return new KylaHistoryPopulator();
				break;
			case "kuva_kyla":
				return new KuvaHistoryPopulator();
				break;

			case "porrashuone":
				return new PorrashuoneHistoryPopulator();
				break;
			case "kuva_porrashuone":
				return new KuvaHistoryPopulator();
				break;
			case "tiedosto_porrashuone":
				return new TiedostoHistoryPopulator();
				break;

			case "alue":
				return new AlueHistoryPopulator();
				break;
			case "kuva_alue":
				return new KuvaHistoryPopulator();
				break;
			case "tiedosto_alue":
				return new TiedostoHistoryPopulator();
				break;
			case "inventointiprojekti_alue":
				return new AlueInventointiProjektiHistoryPopulator();
				break;
			case "alue_kyla":
				return new KylaHistoryPopulator();
				break;

			case "kunta":
				return new KuntaHistoryPopulator();
				break;
			case "tiedosto_kunta":
				return new TiedostoHistoryPopulator();
				break;

			case "suunnittelija":
				return new SuunnittelijaHistoryPopulator();
				break;

			case "inventointiprojekti":
				return new InventointiprojektiHistoryPopulator();
				break;
			case "inventointiprojekti_kunta":
				return new InventointiprojektiKuntaHistoryPopulator();
				break;

			case "arvoalue":
				return new ArvoalueHistoryPopulator();
				break;
			case "kuva_arvoalue":
				return new KuvaHistoryPopulator();
				break;
			case "tiedosto_arvoalue":
				return new TiedostoHistoryPopulator();
				break;
			case "inventointiprojekti_arvoalue":
				return new ArvoalueInventointiProjektiHistoryPopulator();
				break;
			case "arvoalue_arvoaluekulttuurihistoriallinenarvo":
				return new ArvoalueKulttuuriHistoriallinenArvoHistoryPopulator();
				break;
			case "arvoalue_suojelutyyppi":
				return new ArvoalueSuojeluTyyppiHistoryPopulator();
				break;

			case "inventointijulkaisu":
				return new InventointijulkaisuHistoryPopulator();
				break;
			case "inventointijulkaisu_inventointiprojekti":
				return new InventointijulkaisuInventointiprojektiHistoryPopulator();
				break;
			case "inventointijulkaisu_taso":
				return new InventointijulkaisuTasoHistoryPopulator();
				break;

			case "matkaraportti":
				return new MatkaraporttiHistoryPopulator();
				break;
			case "matkaraportti_syy":
				return new MatkaraporttiSyyHistoryPopulator();
				break;
			case "ark_tutkimus":
			    return new ArkTutkimusHistoryPopulator();
			    break;
			case "ark_kohde_tutkimus":
			    return new ArkTutkimusKohdeHistoryPopulator();
			    break;
			case "ark_kohde":
			    return new ArkKohdeHistoryPopulator();
			    break;
			case "ark_kohde_tyyppi":
			    return new ArkKohdeTyyppiHistoryPopulator();
			    break;
			case "ark_kohde_ajoitus":
			    return new ArkKohdeAjoitusHistoryPopulator();
			    break;
			case "ark_kohde_kuntakyla":
			    return new ArkKohdeKuntaKylaHistoryPopulator();
			    break;
			case "ark_kohde_sijainti":
			    return new ArkKohdeSijaintiHistoryPopulator();
			    break;
			case "ark_kohde_suojelutiedot":
			    return new ArkKohdeSuojeluHistoryPopulator();
			    break;
			case "ark_kohde_alakohde":
			    return new ArkKohdeAlakohdeHistoryPopulator();
			    break;
			case "ark_alakohde_sijainti":
			    return new ArkKohdeSijaintiHistoryPopulator(); // kohteella ja alakohteella sama sijainti populator
			    break;
			case "ark_alakohde_ajoitus":
			    return new ArkKohdeAjoitusHistoryPopulator(); // kohteella ja alakohteella sama ajoitus populator
			    break;
			case "kuva_suunnittelija":
			    return new KuvaHistoryPopulator();
			    break;
			case "tiedosto_suunnittelija":
			    return new TiedostoHistoryPopulator();
			    break;
			default:
				return new EmptyPopulator();
				//throw new \Exception("No populator for ".$tablename);
		}
	}

}