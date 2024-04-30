<?php
namespace App\Integrations;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;
use Exception;

class Geoserver {

	private $username;
	private $password;
	private $geoserverUri;
	private $gnamespace;
	private $workspace;
	private $datastore;

	function __construct() {
		/*
		 * Assign values
		 */
		 $this->username = config('app.geoserver_username');
		 $this->password = config('app.geoserver_password');
		 $this->geoserverUri = config('app.geoserver_url');
		 $this->gnamespace = config('app.geoserver_namespace');
		 $this->workspace = config('app.geoserver_workspace');
		 $this->datastore = config('app.geoserver_datastore');

	}


	/*
	 * SQL lausekkeisiin menevät kenttien nimet ja tason XMLlään menevät attribuuttien määritykset.
	 * TODO: Mitä kenttiä millekin halutaan julkaista?
	 * Jos esimerkiksi julkinen kuva halutaan mukaan, lisätään julkinen kuva kenttälistaan JA muokataan sql lauseketta siten, että kuva haetaan siihen.
	 * Samoin myös mäppäystä vaativien arvojen kanssa (esimerkiksi rakennustyypit)
	 * Järjestyksellä on merkitystä, kentät näytetään Geoserverin templatella alla olevassa järjestyksessä
	 * poislukien id (ei näytetä), geometria ja kuva (näytetään aina ensimmäisenä).
	 */
	static $kiinteisto_kentat = array(
		'kiinteisto.id' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.Integer'],
		'kiinteisto.nimi'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'kiinteisto.kiinteiston_sijainti'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'com.vividsolutions.jts.geom.Geometry'],
	    'kiinteisto.kiinteistotunnus'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'kiinteisto.linkit_paikallismuseoihin' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'kiinteisto.paikallismuseot_kuvaus' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'kunta.kunta' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.String'],
		'kyla.kyla' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.String'],
		'kunta.kunta_se' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.String'],
		'kyla.kyla_se' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.String'],
		'kiinteisto.yhteenveto' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'kiinteisto.arvoluokka' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'kiinteisto.arvoluokka_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'kiinteisto.kulttuurihistorialliset_arvot' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'kiinteisto.kulttuurihistorialliset_arvot_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'kiinteisto.kuva_url' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
        'kiinteisto.paikkakunta'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
        'inventointiprojekti.inventointiprojekti'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
        'inventointiprojekti_kiinteisto.inventoija'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
        'inventointiprojekti_kiinteisto.inventointipaiva'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
        'altyyp.aluetyypit'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'altyyp.aluetyypit_se'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'histtilatyyp.tilatyypit'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'histtilatyyp.tilatyypit_se'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'kiinteisto.asutushistoria'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'kiinteisto.lahiymparisto'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'kiinteisto.pihapiiri'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'kiinteisto.arkeologinen_intressi'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'kiinteisto.muu_historia'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'kiinteisto.perustelut'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String']
	);

	static $rakennus_kentat = array(
		'rakennus.id' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.Integer'],
		'kunta.kunta' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.String'],
		'kyla.kyla' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.String'],
		'kunta.kunta_se' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.String'],
		'kyla.kyla_se' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.String'],
		'kiinteisto.kiinteisto' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'kiinteisto.kiinteistotunnus' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.osoitteet' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.postinumero' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.inventointinumero' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.Integer'],
		'rakennus.rakennustunnus' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.rakennustyypit' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.rakennustyypit_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.rakennustyyppi_kuvaus' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.rakennusvuosi_alku' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.rakennusvuosi_loppu' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.rakennusvuosi_selite' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.muutosvuodet' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.alkuperainen_kaytto' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.alkuperainen_kaytto_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.nykykaytto' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.nykykaytto_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.kerroslukumaara' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.asuin_ja_liikehuoneistoja' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.perustus' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.perustus_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.runko' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.runko_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.julkisivumateriaali' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.julkisivumateriaali_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.ulkovari' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.kattotyypit' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.kattotyypit_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.katetyypit' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.katetyypit_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.kunto' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.kunto_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.nykytyyli' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.nykytyyli_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.purettu' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.Boolean'],
		'rakennus.erityispiirteet' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.kulttuurihistorialliset_arvot' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.kulttuurihistorialliset_arvot_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.arvoluokka' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.arvoluokka_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.kulttuurihistoriallisetarvot_perustelut' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'rakennus.rakennuksen_sijainti' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'com.vividsolutions.jts.geom.Geometry'],
		'suunnittelijat.suunnittelija' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'rakennus.rakennushistoria' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'rakennus.sisatilakuvaus' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'rakennus.muut_tiedot' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'rsuojelutiedot.suojelutiedot' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'rsuojelutiedot.suojelutiedot_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String']
	);

	static $alue_kentat = array(
		'alue.id' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.Integer'],
		'alue.nimi' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'alue.historia' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'alue.maisema' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'alue.nykytila' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'alue.keskipiste' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'com.vividsolutions.jts.geom.Geometry'],
		'alue.aluerajaus' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'com.vividsolutions.jts.geom.Geometry'],
		'alue.kuntakyla' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'alue.kuntakyla_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'alue.kuva_url' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'alue.paikkakunta'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'inventointiprojekti.inventointiprojekti'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'inventointiprojekti_alue.inventoija'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'inventointiprojekti_alue.inventointipaiva'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String']
	);

	static $arvoalue_kentat = array(
		'aalue.id' => ['minOccurs' => 1, 'maxOccurs' => 1, 'nillable' => false, 'binding' => 'java.lang.Integer'],
		'alue.alue' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.nimi' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.aluetyyppi' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.aluetyyppi_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.kulttuurihistorialliset_arvot' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.kulttuurihistorialliset_arvot_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.arvoluokka' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.arvoluokka_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.perustelut' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.yhteenveto' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.kuntakyla' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.kuntakyla_se' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
		'aalue.keskipiste' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'com.vividsolutions.jts.geom.Geometry'],
		'aalue.aluerajaus' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'com.vividsolutions.jts.geom.Geometry'],
		'aalue.inventointinumero' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.Integer'],
		'aalue.kuva_url' => ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'aalue.paikkakunta'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'inventointiprojekti.inventointiprojekti'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'inventointiprojekti_arvoalue.inventoija'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String'],
	    'inventointiprojekti_arvoalue.inventointipaiva'=> ['minOccurs' => 0, 'maxOccurs' => 1, 'nillable' => true, 'binding' => 'java.lang.String']
	);

	/*
	 * XML jota käytetään määrittämään jokaiselle tasolle käytettävät geometriakentät. Ei muutu toivottavasti koskaan, siksi kovakoodattu.
	 */
	static $kiinteistoGeometryXml = '<geometry><name>kiinteiston_sijainti</name>
									   <type>Geometry</type>
						  			   <srid>-1</srid>
									 </geometry>';

	static $rakennusGeometryXml = '<geometry><name>rakennuksen_sijainti</name>
									 <type>Geometry</type>
						  			 <srid>-1</srid>
								   </geometry>';

	static $alueGeometryXml = '<geometry>
          					     <name>keskipiste</name>
          					     <type>Geometry</type>
          					     <srid>-1</srid>
        					   </geometry>
        					   <geometry>
          						 <name>aluerajaus</name>
          						 <type>Geometry</type>
          						 <srid>-1</srid>
        					   </geometry>';

	static $arvoalueGeometryXml= '<geometry>
          					         <name>keskipiste</name>
          					     	 <type>Geometry</type>
          					     	 <srid>-1</srid>
        					   	   </geometry>
        					   	  <geometry>
          						 	 <name>aluerajaus</name>
          						     <type>Geometry</type>
          						 	 <srid>-1</srid>
        					   	  </geometry>';

	/*
	 * Generoidaan tasokohtainen Sql-lauseke, jota geoserverin sql-viewillä määritetty taso käyttää.
	 */
	private static function generateSql($tasoNimi, $inventointiprojektit, $kuntaIdt, $kylaIdt) {
		$sql = '';
		$inventointiprojektit_str = '';
		$kentat_str = '';
		$kunnat_str = '';
		$kylat_str = '';
		$image_base_url = config('app.mip_backend_url') . 'raportti/kuva/';

		//Luodaan string joka sisältää kaikki julkaisun inventointiprojektien idt
		//Jos inventointiprojekteja ei ole annettu, on kuntaId tai kylaId pakko olla
		if(isset($inventointiprojektit)) {
    		foreach($inventointiprojektit as $ip) {
    			$inventointiprojektit_str .= $ip['id'] . ",";
    		}
    		//Siistitään ylimääräinen pilkku lopusta
    		$inventointiprojektit_str = substr($inventointiprojektit_str, 0, -1);
		}

		if(count($kuntaIdt) > 0) {
		    foreach($kuntaIdt as $kuntaId) {
		        $kunnat_str .= $kuntaId . ",";
		    }
		    $kunnat_str = substr($kunnat_str, 0, -1);
		}

		if(count($kylaIdt) > 0) {
		    foreach($kylaIdt as $kylaId) {
		        $kylat_str .= $kylaId . ",";
		    }
		    $kylat_str = substr($kylat_str, 0, -1);
		}

		//Sanitize
		$chars = ["\0", "\'", "\"", "\b", "\n", "\r", "\t", "\Z"];
		foreach($chars as $char) {
		    $inventointiprojektit_str = str_replace($char, "", $inventointiprojektit_str);
		    $kunnat_str = str_replace($char, "", $kunnat_str);
		    $kylat_str = str_replace($char, "", $kylat_str);
		}

		/*
		 * Luodaan tason mukainen SQL-lauseke.
		 */
		if($tasoNimi== 'kiinteisto') {
			$sql = "SELECT
					kiinteisto.id,
					kiinteisto.kiinteistotunnus,
					kiinteisto.nimi,
					kiinteisto.kiinteiston_sijainti,
					kunta.nimi as kunta,
					kunta.nimi_se as kunta_se,
					kyla.nimi as kyla,
					kyla.nimi_se as kyla_se,
					kiinteisto.perustelut_yhteenveto as yhteenveto,
					at.nimi_fi as arvoluokka,
					at.nimi_se as arvoluokka_se,
					kiinteisto.arvotustyyppi_id,
					kh.kulttuurihistorialliset_arvot,
					kh.kulttuurihistorialliset_arvot_se,
					'$image_base_url' || kuva.id || '/pieni' as kuva_url,
                	kiinteisto.paikkakunta,
                	inventointiprojekti_kiinteisto.inventointiprojekti,
                	inventointiprojekti_kiinteisto.inventoija_nimi as inventoija,
                	inventointiprojekti_kiinteisto.inventointipaiva,
                	altyyp.aluetyypit, altyyp.aluetyypit_se,
                	histtilatyyp.tilatyypit, histtilatyyp.tilatyypit_se,
                	kiinteisto.asutushistoria,
                	kiinteisto.lahiymparisto,
                	kiinteisto.pihapiiri,
                	kiinteisto.arkeologinen_intressi,
                	kiinteisto.muu_historia,
                	kiinteisto.perustelut,
                    kiinteisto.linkit_paikallismuseoihin,
                    kiinteisto.paikallismuseot_kuvaus
					FROM kiinteisto
					LEFT JOIN arvotustyyppi at on (at.id = kiinteisto.arvotustyyppi_id)
					LEFT JOIN (
					    SELECT kkkh.kiinteisto_id, string_agg(kkh.nimi_fi, ',') AS kulttuurihistorialliset_arvot, string_agg(kkh.nimi_se, ',') as kulttuurihistorialliset_arvot_se
					    FROM kiinteisto_kiinteistokulttuurihistoriallinenarvo kkkh, kiinteistokulttuurihistoriallinenarvo kkh
					    WHERE kkkh.kulttuurihistoriallinenarvo_id = kkh.id
					    GROUP BY kkkh.kiinteisto_id
					) kh ON (kh.kiinteisto_id = kiinteisto.id)";
			// If kunnat or kylat were set, left join inventointiprojekti information
			// Otherwise the inventointiprojekti is the only input value and right join them
			if(strlen($kylat_str) > 0 || strlen($kunnat_str) > 0) {
			    $sql .= " left ";
			} else {
			    $sql .= " right ";
			}
			$sql .= "join (
                    	select
                    		ik.kiinteisto_id,
                    		string_agg(distinct ik.inventoija_nimi, ', ') as inventoija_nimi,
                    		string_agg(distinct concat(date_part('day', ik.inventointipaiva), '.', date_part('month', ik.inventointipaiva), '.', date_part('year', ik.inventointipaiva)), ', ') as inventointipaiva,
                            string_agg(distinct ip.nimi, ',') as inventointiprojekti
                    	from inventointiprojekti_kiinteisto ik
                        left join inventointiprojekti ip on ip.id = ik.inventointiprojekti_id
                    	where ik.poistettu is null ";
			if(strlen($inventointiprojektit_str) > 0) {
			    $sql .= "and ik.inventointiprojekti_id in (" . $inventointiprojektit_str . ") ";
			}
             $sql .= "group by ik.kiinteisto_id
                    ) inventointiprojekti_kiinteisto on (inventointiprojekti_kiinteisto.kiinteisto_id = kiinteisto.id)
                    LEFT JOIN (
                		select kat.kiinteisto_id, string_agg(alt.nimi_fi, ',') as aluetyypit, string_agg(alt.nimi_se, ',') as aluetyypit_se
                		from kiinteisto_aluetyyppi kat, aluetyyppi alt
                		where kat.aluetyyppi_id = alt.id
                		group by kat.kiinteisto_id
                	) altyyp on (altyyp.kiinteisto_id = kiinteisto.id)
                	LEFT JOIN (
                		select khtt.kiinteisto_id, string_agg(tt.nimi_fi, ',') as tilatyypit, string_agg(tt.nimi_se, ',') as tilatyypit_se
                		from kiinteisto_historiallinen_tilatyyppi khtt, tilatyyppi tt
                		where khtt.tilatyyppi_id = tt.id
                		group by khtt.kiinteisto_id
                	) histtilatyyp on (histtilatyyp.kiinteisto_id = kiinteisto.id)
					left join (
						select kk.kuva_id, kk.kiinteisto_id from kuva_kiinteisto kk, (
						                select kiinteisto_id, min(jarjestys) as jarjestys
						                from kuva_kiinteisto skk, kuva sk
						                where skk.kuva_id = sk.id
						                and sk.julkinen = true
						                group by kiinteisto_id
						) x
						where x.kiinteisto_id = kk.kiinteisto_id and x.jarjestys = kk.jarjestys
					) min_kuva on (kiinteisto.id = min_kuva.kiinteisto_id)
					LEFT JOIN kuva on kuva.id = min_kuva.kuva_id
					JOIN kyla ON (kiinteisto.kyla_id = kyla.id)
			        JOIN kunta ON (kyla.kunta_id = kunta.id) where";
			if(strlen($kunnat_str) > 0) {
			    $sql .= " kunta.id in (".$kunnat_str.")";
			}
			if(strlen($kunnat_str) > 0 && strlen($kylat_str) == 0) {
			    $sql .= " AND";
			} else if(strlen($kunnat_str) > 0 && strlen($kylat_str) > 0) {
			    $sql .= " OR";
			}
			if(strlen($kylat_str) > 0) {
			    $sql .= " kyla.id in (".$kylat_str.") AND ";
			}
			$sql .= " kiinteisto.poistettu IS null
					AND kiinteisto.julkinen = true";
		} else if($tasoNimi== 'rakennus') {
			$sql = "select
					rakennus.id,
					kunta.nimi as kunta,
					kunta.nimi_se as kunta_se,
					kyla.nimi as kyla,
					kyla.nimi_se as kyla_se,
					kiinteisto.nimi as kiinteisto,
					kiinteisto.kiinteistotunnus,
					ro.osoitteet,
					rakennus.postinumero,
					rakennus.inventointinumero,
					rakennus.rakennustunnus,
					raktyyp.rakennustyypit,
					raktyyp.rakennustyypit_se,
					rakennus.rakennustyyppi_kuvaus,
					rakennus.rakennusvuosi_alku,
					rakennus.rakennusvuosi_loppu,
					rakennus.rakennusvuosi_selite,
					rm.muutosvuodet,
					ralkuperainen_kaytto.alkuperainen_kaytto,
                    ralkuperainen_kaytto.alkuperainen_kaytto_se,
					rnykykaytto.nykykaytto,
                    rnykykaytto.nykykaytto_se,
					rakennus.kerroslukumaara,
					rakennus.asuin_ja_liikehuoneistoja,
					rperustus.perustus,
                    rperustus.perustus_se,
					rrunko.runko,
                    rrunko.runko_se,
					rvuoraus.vuoraus as julkisivumateriaali,
                    rvuoraus.vuoraus_se as julkisivumateriaali_se,
					rakennus.ulkovari,
					rkatto.kattotyypit,
					rkatto.kattotyypit_se,
					rkate.katetyypit,
					rkate.katetyypit_se,
					kuntotyyppi.nimi_fi as kunto,
                    kuntotyyppi.nimi_se as kunto_se,
					tyylisuunta.nimi_fi as nykytyyli,
                    tyylisuunta.nimi_se as nykytyyli_se,
					rakennus.purettu,
					rakennus.erityispiirteet,
					rh.kulttuurihistorialliset_arvot,
					rh.kulttuurihistorialliset_arvot_se,
					arvotustyyppi.nimi_fi as arvoluokka,
					arvotustyyppi.nimi_se as arvoluokka_se,
					rakennus.kulttuurihistoriallisetarvot_perustelut,
					rakennus.rakennuksen_sijainti,
                    suunnittelijat.suunnittelija,
                    rakennus.rakennushistoria,
                    rakennus.sisatilakuvaus,
                    rakennus.muut_tiedot,
                    rsuojelutiedot.suojelutiedot,
                    rsuojelutiedot.suojelutiedot_se
					from rakennus
					join kiinteisto on rakennus.kiinteisto_id = kiinteisto.id
					LEFT JOIN (
					    SELECT rrkh.rakennus_id, string_agg(rkh.nimi_fi, ',') AS kulttuurihistorialliset_arvot, string_agg(rkh.nimi_se, ',') AS kulttuurihistorialliset_arvot_se
					    FROM rakennus_rakennuskulttuurihistoriallinenarvo rrkh, rakennuskulttuurihistoriallinenarvo rkh
					    WHERE rrkh.kulttuurihistoriallinenarvo_id = rkh.id
					    GROUP BY rrkh.rakennus_id
					) rh ON (rh.rakennus_id = rakennus.id)
					LEFT JOIN (
					    SELECT rrt.rakennus_id, string_agg(rt.nimi_fi, ',') AS rakennustyypit, string_agg(rt.nimi_se, ',') AS rakennustyypit_se
					    FROM rakennus_rakennustyyppi rrt, rakennustyyppi rt
					    WHERE rrt.rakennustyyppi_id = rt.id
					    GROUP BY rrt.rakennus_id
					) raktyyp ON (raktyyp.rakennus_id = rakennus.id)
                    LEFT JOIN (
					    SELECT rrt.rakennus_id, string_agg(rt.nimi_fi, ',') AS runko, string_agg(rt.nimi_se, ',') AS runko_se
					    FROM rakennus_runkotyyppi rrt, runkotyyppi rt
					    WHERE rrt.runkotyyppi_id = rt.id
					    GROUP BY rrt.rakennus_id
					) rrunko ON (rrunko.rakennus_id = rakennus.id)
                    LEFT JOIN (
					    SELECT rap.rakennus_id, string_agg(ak.nimi_fi, ',') AS alkuperainen_kaytto, string_agg(ak.nimi_se, ',') AS alkuperainen_kaytto_se
					    FROM rakennus_alkuperainenkaytto rap, kayttotarkoitus ak
					    WHERE rap.kayttotarkoitus_id = ak.id
					    GROUP BY rap.rakennus_id
					) ralkuperainen_kaytto ON (ralkuperainen_kaytto.rakennus_id = rakennus.id)
                    LEFT JOIN (
					    SELECT rnk.rakennus_id, string_agg(ak.nimi_fi, ',') AS nykykaytto, string_agg(ak.nimi_se, ',') AS nykykaytto_se
					    FROM rakennus_nykykaytto rnk, kayttotarkoitus ak
					    WHERE rnk.kayttotarkoitus_id = ak.id
					    GROUP BY rnk.rakennus_id
					) rnykykaytto ON (rnykykaytto.rakennus_id = rakennus.id)
                    LEFT JOIN (
					    SELECT rpt.rakennus_id, string_agg(pt.nimi_fi, ',') AS perustus, string_agg(pt.nimi_se, ',') AS perustus_se
					    FROM rakennus_perustustyyppi rpt, perustustyyppi pt
					    WHERE rpt.perustustyyppi_id = pt.id
					    GROUP BY rpt.rakennus_id
					) rperustus ON (rperustus.rakennus_id = rakennus.id)
                    LEFT JOIN (
					    SELECT rvt.rakennus_id, string_agg(vt.nimi_fi, ',') AS vuoraus, string_agg(vt.nimi_se, ',') AS vuoraus_se
					    FROM rakennus_vuoraustyyppi rvt, vuoraustyyppi vt
					    WHERE rvt.vuoraustyyppi_id = vt.id
					    GROUP BY rvt.rakennus_id
					) rvuoraus ON (rvuoraus.rakennus_id = rakennus.id)
					LEFT JOIN (
					    SELECT rakennus_muutosvuosi.rakennus_id, string_agg(rakennus_muutosvuosi.alkuvuosi::text || '-' || rakennus_muutosvuosi.loppuvuosi::text || ' : ' || rakennus_muutosvuosi.selite, ',') as muutosvuodet
					    FROM rakennus_muutosvuosi
					    GROUP BY rakennus_muutosvuosi.rakennus_id
					) rm on (rm.rakennus_id = rakennus.id)
					LEFT JOIN (
					    SELECT rk.rakennus_id, string_agg(katot.nimi_fi, ',') AS kattotyypit, string_agg(katot.nimi_se, ',') AS kattotyypit_se
					    FROM rakennus_kattotyyppi rk, kattotyyppi katot
					    WHERE rk.kattotyyppi_id = katot.id
					    GROUP BY rk.rakennus_id
					) rkatto ON (rkatto.rakennus_id = rakennus.id)
					LEFT JOIN (
					    SELECT rka.rakennus_id, string_agg(katteet.nimi_fi, ',') AS katetyypit, string_agg(katteet.nimi_se, ',') AS katetyypit_se
					    FROM rakennus_katetyyppi rka, katetyyppi katteet
					    WHERE rka.katetyyppi_id = katteet.id
					    GROUP BY rka.rakennus_id
					) rkate ON (rkate.rakennus_id = rakennus.id)
					LEFT JOIN (
					    SELECT roj.rakennus_id, string_agg(roj.katunimi || ' ' || roj.katunumero, ',') as osoitteet
					    FROM (select * from rakennus_osoite order by jarjestysnumero asc) roj
					    GROUP BY roj.rakennus_id
					) ro on (ro.rakennus_id = rakennus.id)
					LEFT JOIN (
                        SELECT sr.rakennus_id, string_agg(s.sukunimi || ' ' || s.etunimi, ', ') AS suunnittelija
                        FROM suunnittelija_rakennus sr, suunnittelija s
                        WHERE sr.suunnittelija_id = s.id
                        GROUP BY sr.rakennus_id
                        ) suunnittelijat on (suunnittelijat.rakennus_id = rakennus.id)
                    left join (
                    	select rst.rakennus_id, string_agg(st.nimi_fi || ' - ' || coalesce(rst.merkinta, '') || ' - ' || rst.selite, '\n') as suojelutiedot, string_agg(st.nimi_fi || ' - ' || coalesce(rst.merkinta, '') || ' - ' || rst.selite, '\n') as suojelutiedot_se
                    	from rakennus_suojelutyyppi rst, suojelutyyppi st
                    	where rst.suojelutyyppi_id = st.id
                    	group by rst.rakennus_id
                    ) rsuojelutiedot on (rsuojelutiedot.rakennus_id = rakennus.id)
					left join arvotustyyppi on rakennus.arvotustyyppi_id = arvotustyyppi.id
                    left join kuntotyyppi on rakennus.kuntotyyppi_id = kuntotyyppi.id
                    left join tyylisuunta on rakennus.nykyinen_tyyli_id = tyylisuunta.id";
			// If kunnat or kylat were set, left join inventointiprojekti information
			// Otherwise the inventointiprojekti is the only input value and right join them
			if(strlen($kylat_str) > 0 || strlen($kunnat_str) > 0) {
			    $sql .= " left ";
			} else {
			    $sql .= " right ";
			}
			$sql .= " join (
                    	select
                    		ik.kiinteisto_id
                    	from inventointiprojekti_kiinteisto ik
                        left join inventointiprojekti ip on ip.id = ik.inventointiprojekti_id
                    	where ik.poistettu is null ";
			if(strlen($inventointiprojektit_str) > 0) {
			    $sql .= "and ik.inventointiprojekti_id in (" . $inventointiprojektit_str . ") ";
			}
			$sql .= " group by ik.kiinteisto_id
                    ) inventointiprojekti_kiinteisto on (inventointiprojekti_kiinteisto.kiinteisto_id = kiinteisto.id)
					join kyla on (kiinteisto.kyla_id = kyla.id)
                    join kunta on (kyla.kunta_id = kunta.id) where";
			if(strlen($kunnat_str) > 0) {
			    $sql .= " kunta.id in (".$kunnat_str.")";
			}
			if(strlen($kunnat_str) > 0 && strlen($kylat_str) == 0) {
			    $sql .= " AND";
			} else if(strlen($kunnat_str) > 0 && strlen($kylat_str) > 0) {
			    $sql .= " OR";
			}
			if(strlen($kylat_str) > 0) {
			    $sql .= " kyla.id in (".$kylat_str.") AND ";
			}
			$sql .= " kiinteisto.poistettu is null
					and kiinteisto.julkinen = true
					and rakennus.poistettu is null";
		} else if($tasoNimi== 'alue') {
			$sql = "select
					alue.id,
					alue.nimi,
					alue.historia,
					alue.maisema,
					alue.nykytila,
					alue.keskipiste,
					alue.aluerajaus,
                    alue.paikkakunta,
					kk.kuntakyla,
					kk.kuntakyla_se,
					'$image_base_url' || kuva.id || '/pieni' as kuva_url,
                    inventointiprojekti_alue.inventointiprojekti,
                	inventointiprojekti_alue.inventoija_nimi as inventoija,
                	inventointiprojekti_alue.inventointipaiva
					from alue
					join (
					    select ak.alue_id, string_agg(kunta.nimi || ' - ' || ky.nimi, ', ') as kuntakyla, string_agg(kunta.nimi_se || ' - ' || ky.nimi_se, ', ') as kuntakyla_se
					    from alue_kyla ak, kyla ky
					    join kunta on ky.kunta_id = kunta.id
					    where ak.kyla_id = ky.id";
			// If kylat were set, use them in the query.
			// Otherwise if kunnat were set, use them in the query
			if(strlen($kylat_str) > 0) {
			    $sql .= " AND ky.id in (".$kylat_str.")";
			} else if(strlen($kunnat_str) > 0) {
			    $sql .= " AND kunta.id in (".$kunnat_str.")";
			}
			$sql .= " group by ak.alue_id
					) kk on (kk.alue_id = alue.id)";

			// If kunnat or kylat were set, left join inventointiprojekti information
			// Otherwise the inventointiprojekti is the only input value and right join them
			if(strlen($kylat_str) > 0 || strlen($kunnat_str) > 0) {
			    $sql .= " left ";
			} else {
			    $sql .= " right ";
			}
			$sql .= "join (
                    	select
                    		ia.alue_id,
                    		string_agg(distinct ia.inventoija_nimi, ', ') as inventoija_nimi,
                    		string_agg(distinct concat(date_part('day', ia.inventointipaiva), '.', date_part('month', ia.inventointipaiva), '.', date_part('year', ia.inventointipaiva)), ', ') as inventointipaiva,
                            string_agg(distinct ip.nimi, ',') as inventointiprojekti
                    	from inventointiprojekti_alue ia
                        left join inventointiprojekti ip on ip.id = ia.inventointiprojekti_id
                    	where ia.poistettu is null ";
			if(strlen($inventointiprojektit_str) > 0) {
			    $sql .= "and ia.inventointiprojekti_id in (" . $inventointiprojektit_str . ") ";
			}
            $sql .= " group by ia.alue_id
                    ) inventointiprojekti_alue on (inventointiprojekti_alue.alue_id = alue.id)
					left join (
						select ak.kuva_id, ak.alue_id from kuva_alue ak, (
							select alue_id, min(jarjestys) as jarjestys
							from kuva_alue skk, kuva sk
							where skk.kuva_id = sk.id
							and sk.julkinen = true
							group by alue_id
						) x
						where x.alue_id = ak.alue_id and x.jarjestys = ak.jarjestys
					) min_kuva on (alue.id = min_kuva.alue_id)
					LEFT JOIN kuva on kuva.id = min_kuva.kuva_id
					where alue.poistettu is null
                    and alue.id is not null";
		} else if($tasoNimi== 'arvoalue') {
			$sql = "select
					aalue.id,
					alue.nimi as alue,
					aalue.nimi,
					aluetyyppi.nimi_fi as aluetyyppi,
					aluetyyppi.nimi_se as aluetyyppi_se,
					ah.kulttuurihistorialliset_arvot,
					ah.kulttuurihistorialliset_arvot_se,
					arvotustyyppi.nimi_fi as arvoluokka,
					arvotustyyppi.nimi_se as arvoluokka_se,
					aalue.kuvaus as perustelut,
					aalue.yhteenveto,
					aalue.keskipiste,
					aalue.aluerajaus,
					aalue.inventointinumero,
                    aalue.paikkakunta,
					kk.kuntakyla,
					kk.kuntakyla_se,
					'$image_base_url' || kuva.id || '/pieni' as kuva_url,
                    inventointiprojekti_arvoalue.inventointiprojekti,
                	inventointiprojekti_arvoalue.inventoija_nimi as inventoija,
                	inventointiprojekti_arvoalue.inventointipaiva
					from arvoalue aalue
					join alue on aalue.alue_id = alue.id
					join aluetyyppi on aalue.aluetyyppi_id = aluetyyppi.id
					left join arvotustyyppi on aalue.arvotustyyppi_id = arvotustyyppi.id
					LEFT JOIN (
						SELECT aakh.arvoalue_id, string_agg(akh.nimi_fi, ',') AS kulttuurihistorialliset_arvot, string_agg(akh.nimi_se, ',') AS kulttuurihistorialliset_arvot_se
						FROM arvoalue_arvoaluekulttuurihistoriallinenarvo aakh, arvoaluekulttuurihistoriallinenarvo akh
						WHERE aakh.kulttuurihistoriallinenarvo_id = akh.id
						GROUP BY aakh.arvoalue_id
					) ah ON (ah.arvoalue_id = aalue.id)";
			// If kunnat or kylat were set, left join inventointiprojekti information
			// Otherwise the inventointiprojekti is the only input value and right join them
			if(strlen($kylat_str) > 0 || strlen($kunnat_str) > 0) {
			    $sql .= " left ";
			} else {
			    $sql .= " right ";
			}
			$sql .= "join (
                    	select
                    		iaa.arvoalue_id,
                    		string_agg(distinct iaa.inventoija_nimi, ', ') as inventoija_nimi,
                    		string_agg(distinct concat(date_part('day', iaa.inventointipaiva), '.', date_part('month', iaa.inventointipaiva), '.', date_part('year', iaa.inventointipaiva)), ', ') as inventointipaiva,
                            string_agg(distinct ip.nimi, ',') as inventointiprojekti
                    	from inventointiprojekti_arvoalue iaa
                        left join inventointiprojekti ip on ip.id = iaa.inventointiprojekti_id
                    	where iaa.poistettu is null ";
			if(strlen($inventointiprojektit_str) > 0) {
			    $sql .= "and iaa.inventointiprojekti_id in (" . $inventointiprojektit_str . ") ";
			}
			$sql .= "group by iaa.arvoalue_id
                    ) inventointiprojekti_arvoalue on (inventointiprojekti_arvoalue.arvoalue_id = aalue.id)
					join (
					    select ak.arvoalue_id, string_agg(kunta.nimi || ' - ' || ky.nimi, ', ') as kuntakyla, string_agg(kunta.nimi_se || ' - ' || ky.nimi_se, ', ') as kuntakyla_se
					    from arvoalue_kyla ak, kyla ky
					    join kunta on ky.kunta_id = kunta.id
					    where ak.kyla_id = ky.id";
			if(strlen($kylat_str) > 0) {
			    $sql .= " AND ky.id in (".$kylat_str.")";
			} else if(strlen($kunnat_str) > 0) {
			    $sql .= " AND kunta.id in (".$kunnat_str.")";
			}
			$sql .= " group by ak.arvoalue_id
					) kk on (kk.arvoalue_id = aalue.id)
					left join (
						select ak.kuva_id, ak.arvoalue_id from kuva_arvoalue ak, (
							select arvoalue_id, min(jarjestys) as jarjestys
							from kuva_arvoalue skk, kuva sk
							where skk.kuva_id = sk.id
							and sk.julkinen = true
							group by arvoalue_id
						) x
						where x.arvoalue_id = ak.arvoalue_id and x.jarjestys = ak.jarjestys
					) min_kuva on (aalue.id = min_kuva.arvoalue_id)
					LEFT JOIN kuva on kuva.id = min_kuva.kuva_id
					where aalue.poistettu is null
                    and aalue.id is not null";
		}

		if(strlen($sql) == 0) {
			throw new Exception("Unknown layer definition: " . $tasoNimi);
		}

		//Prettify the sql a bit by removing extra whitespaces
		$sql = preg_replace('/\s+/S', ' ', $sql);

		return $sql;
	}

	//Filtteröidään kaikista mahdollisista kentistä pois ne joita ei ole valituissa kentissä
	private static function filterFields($kaikkiKentat, $valitutKentat) {
	    $retFields = $kaikkiKentat;

	    for($i = 0; $i<count($kaikkiKentat); $i++) {
	        $selected = false;

	        $kkNimiKey = array_keys($kaikkiKentat)[$i]; //esim. kiinteisto.kiinteiston_sijainti
	        //Arvot tämän jälkeen kkKokoNimi:
	        //[0] = kiinteisto, [1] = "kiinteiston_sijainti"
	        $kkKokoNimi = explode(".", $kkNimiKey);
            $tyyppi = $kkKokoNimi[0];
	        $kkNimi = $kkKokoNimi[1]; //esim. kiinteiston_sijainti
	        //Lisäksi pitää ottaa huomioon, että nimessä voi olla _se ruotsinnoksien kohdalla
	        $kkNimi = explode("_se", $kkNimi)[0];

	        for($j = 0; $j<count($valitutKentat); $j++) {
	            //Jos valituissa kentissä on tämä kenttä, merkitään se jätettäväksi
                if($kkNimi == $valitutKentat[$j]['name']) {
                    $selected = true;
                }
                //Poikkeukset:
                //Frontilta voi tulla esim "alueen sijainti", joka
                //pitää mäpätä keskupisteeksi tai aluerajaukseksi
                if($tyyppi == "alue" && $kkNimi == "keskipiste" || $kkNimi == "aluerajaus") {
                    if($valitutKentat[$j]['name'] == "alueen_sijainti") {
                        $selected = true;
                    }
                }
                if($tyyppi == "aalue" && $kkNimi == "keskipiste" || $kkNimi == "aluerajaus") {
                    if($valitutKentat[$j]['name'] == "aalueen_sijainti") {
                        $selected = true;
                    }
                }

	        }
	        //Jos kenttää ei ole valituissa, poistetaan se
	        if($selected == false) {
	            unset($retFields[$kkNimiKey]);
	        }
	    }
	    return $retFields;
	}

	/*
	 * Generoidaan XMLn attribuuttiosio
	 */
	private static function generateXmlAttributes($kaikkiKentat, $valitutKentat) {
		/*
		 * EXAMPLE XML TO GENERATE:
		 *
		 * <name>id</name>
		 * <minOccurs>1</minOccurs>
		 * <maxOccurs>1</maxOccurs>
		 * <nillable>false</nillable>
		 * <binding>java.lang.Integer</binding>
		 *
		 */

	    //Filtteröidään allFields siten, että jäljelle jää ainoastaan ne jotka annetaan $kentat muuttujassa
	    $kentat = self::filterFields($kaikkiKentat, $valitutKentat);

		$attrs = '';

		foreach($kentat as $k => $v) {
			$attr = '<attribute>';
			$attr .= '<name>'. explode('.', $k)[1] . '</name>'; //Otetaan ainostaan pisteen jälkeen oleva osio, esim Kiinteisto.id --> id

			foreach ($v as $valueName => $value) {
				//We use var_export() to get the string representation of a value; otherwise true is 1 and false is empty, which is no good for Geoserver.
				//Downside is that string representation includes ' characters aroung id, but we strip those off later on.
				$attr .= '<'. $valueName . '>' . var_export($value, true) . '</' . $valueName . '>';
			}

			$attr .= '</attribute>';


			$attrs .= $attr;

		}

		//Replace ' from the attributes.
		$attrs = str_replace("'", "", $attrs);
		return $attrs;
	}

	/*
	 * Palauttaa tason nimen joka geoserverille luodaan.
	 * Jos nimeä halutaan muuttaa, muokkaa tämän toteutusta.
	 * Tason nimeksi geoserverillä tulee "<julkaisunimi> <tasonimi>", esimerkiksi "Alastaron rakennusinventointi kiinteisto"
	 */
	private static function generateLayername($julkaisuNimi, $tasoNimi) {
		return $julkaisuNimi . ' ' . $tasoNimi;
	}

	private static function generateLayernameWithUnderscores($julkaisuNimi, $tasoNimi) {
		return preg_replace('/\s+/', '_', $julkaisuNimi) . '_' . $tasoNimi;
	}

	/*
	 * Generoidaan XML jonka avulla tasoja voidaan luoda Geoserverille.
	 */
	private static function generateCreateXml($julkaisuNimi, $tasoNimi, $inventointiprojektit, $gnamespace, $geoserverUri, $workspace, $datastore, $kentat, $kuntaIdt, $kylaIdt) {
		$kokonimi = self::generateLayername($julkaisuNimi, $tasoNimi);
		$nimiAlaviivoilla = self::generateLayernameWithUnderscores($julkaisuNimi, $tasoNimi);
		$sql = self::generateSql($tasoNimi, $inventointiprojektit, $kuntaIdt, $kylaIdt);

		$xml = '<featureType>
				  <name>' . $nimiAlaviivoilla  . '</name>
				  <nativeName>' . $nimiAlaviivoilla . '</nativeName>
				  <namespace>
					<name>' . $gnamespace. '</name>
					<atom:link xmlns:atom="http://www.w3.org/2005/Atom" rel="alternate" href="' . $geoserverUri . 'namespaces/' . $gnamespace. '.xml" type="application/xml"/>
				  </namespace>
				  <title>' . $kokonimi . '</title>
				  <keywords>
					<string>features</string>
					<string>' . $kokonimi . '</string>
				  </keywords>
				  <srs>EPSG:3067</srs>
				  <nativeBoundingBox>
					<minx>43547.78932226647</minx>
					<maxx>764796.7155847414</maxx>
					<miny>6523158.091198515</miny>
					<maxy>7795461.187543589</maxy>
					<crs class="projected">EPSG:3067</crs>
				  </nativeBoundingBox>
				  <latLonBoundingBox>
					<minx>15.053785270822845</minx>
					<maxx>33.993537468175056</maxx>
					<miny>58.6074564998815</miny>
					<maxy>70.2641566115493</maxy>
					<crs>GEOGCS[&quot;WGS84(DD)&quot;,
				  DATUM[&quot;WGS84&quot;,
					SPHEROID[&quot;WGS84&quot;, 6378137.0, 298.257223563]],
				  PRIMEM[&quot;Greenwich&quot;, 0.0],
				  UNIT[&quot;degree&quot;, 0.017453292519943295],
				  AXIS[&quot;Geodetic longitude&quot;, EAST],
				  AXIS[&quot;Geodetic latitude&quot;, NORTH]]</crs>
				  </latLonBoundingBox>
				  <projectionPolicy>FORCE_DECLARED</projectionPolicy>
				  <enabled>true</enabled>
				  <metadata>
					<entry key="JDBC_VIRTUAL_TABLE">
					  <virtualTable>
						<name>' . $nimiAlaviivoilla . '</name>
						<sql> ' . $sql . '</sql>
						<escapeSql>false</escapeSql>';

		if($tasoNimi== 'kiinteisto') {
							$xml .= self::$kiinteistoGeometryXml;
		} else if($tasoNimi== 'rakennus') {
							$xml .= self::$rakennusGeometryXml;
		} else if($tasoNimi== 'alue') {
							$xml .= self::$alueGeometryXml;
		} else if($tasoNimi== 'arvoalue') {
							$xml .= self::$arvoalueGeometryXml;
						}
			$xml .= '</virtualTable>
					</entry>
					<entry key="cachingEnabled">false</entry>
				  </metadata>
				  <store class="dataStore">
					<name>' . $workspace . ':' . $datastore . '</name>
					<atom:link xmlns:atom="http://www.w3.org/2005/Atom" rel="alternate" href="' . $geoserverUri . 'workspaces/' . $workspace . '/datastores/' . $datastore . '.xml" type="application/xml"/>
				  </store>
				  <maxFeatures>0</maxFeatures>
				  <numDecimals>0</numDecimals>
				  <overridingServiceSRS>false</overridingServiceSRS>
				  <skipNumberMatched>false</skipNumberMatched>
				  <circularArcPresent>false</circularArcPresent>
				  <attributes>';

			//Add the attribute elements to the xml above.
			if($tasoNimi== 'kiinteisto') {
				$xml .= self::generateXmlAttributes(self::$kiinteisto_kentat, $kentat);
			} else if($tasoNimi== 'rakennus') {
			    $xml .= self::generateXmlAttributes(self::$rakennus_kentat, $kentat);
			} else if ($tasoNimi== 'alue') {
			    $xml .= self::generateXmlAttributes(self::$alue_kentat, $kentat);
			} else if($tasoNimi== 'arvoalue') {
			    $xml .= self::generateXmlAttributes(self::$arvoalue_kentat, $kentat);
			}

		$xml .=  '</attributes></featureType>';
		return $xml;
	}

	/*
	 * Luodaan taso geoserverille. Taso joka käyttää SQL-viewiä on oikeasti oma featuretype geoserverillä.
	 */
	private function addFeatureType($julkaisuNimi, $layerNimi, $inventointiprojektit, $kentat, $kuntaIdt, $kylaIdt) {
		$createAddress = $this->geoserverUri . "workspaces/" . $this->workspace . "/datastores/" . $this->datastore . "/featuretypes/";
		$createXML = self::generateCreateXml($julkaisuNimi, $layerNimi, $inventointiprojektit, $this->gnamespace, $this->geoserverUri, $this->workspace, $this->datastore, $kentat, $kuntaIdt, $kylaIdt);
        //app('log')->debug($createXML);
		//post create xml, if return OK, post edit xml
		$client = new Client();

		try {
			$createRes = $client->request('POST', $createAddress, [
					'headers' => ['Content-Type' => 'text/xml'],
					'auth' => [$this->username, $this->password],
					'body' => $createXML
			]);

			if($createRes->getStatusCode() !="201") {
				throw new Exception($julkaisuNimi . " " . $layerNimi . " luonti epäonnistui: " . $createRes->getStatusCode() . " : " . $createRes->getReasonPhrase());
			}
		} catch(Exception $e) {
			Log::channel('geoserver')->error($julkaisuNimi . " " . $layerNimi . " luonti epäonnistui.");
			throw new Exception($julkaisuNimi . " " . $layerNimi . " luonti epäonnistui.");
		}
	}

	private static function isArvoluokkaSelected($kentat) {
	    $arvoluokkaIsFound = false;
	    for($j = 0; $j<count($kentat); $j++) {
	        //Jos valituissa kentissä on arvoluokka
	        if($kentat[$j]['name'] == 'arvoluokka') {
	            $arvoluokkaIsFound = true;
	            break;
	        }
	    }
	    return $arvoluokkaIsFound;
	}

	private static function isInventointinumeroSelected($kentat) {
	    $inventointinumeroIsFound = false;
	    for($j = 0; $j<count($kentat); $j++) {
	        //Jos valituissa kentissä on arvoluokka
	        if($kentat[$j]['name'] == 'inventointinumero') {
	            $inventointinumeroIsFound = true;
	            break;
	        }
	    }
	    return $inventointinumeroIsFound;
	}

	private static function isPurettuSelected($kentat) {
	    $purettuIsFound = false;
	    for($j = 0; $j<count($kentat); $j++) {
	        //Jos valituissa kentissä on arvoluokka
	        if($kentat[$j]['name'] == 'inventointinumero') {
	            $purettuIsFound = true;
	            break;
	        }
	    }
	    return $purettuIsFound;
	}

	/*
	 * Asetetaan featuretypelle tyyli
	 * HUOM1: defaultStype sisältää viittauksen mip -datastoreen. Voimme käyttää toisen datastoren tyylejä, ei tarvetta duplikoida tyylejä moneen paikkaans.
	 * HUOM2: Vaatii että mip:tasonimi niminen tyyli löytyy, eli kiinteisto, rakennus, alue, arvoalue.
	 */
	private function editFeatureType($julkaisuNimi, $tasoNimi, $kentat) {
		$nimiAlaviivoilla = self::generateLayernameWithUnderscores($julkaisuNimi, $tasoNimi);

		$updateAddress = $this->geoserverUri . "layers/" . $nimiAlaviivoilla;

		//Tehdään kaikille tasoille testi onko arvoluokkaa valittuna. (Alueella ei ole koskaan valittuna)
		$useStyleWithArvoluokka = self::isArvoluokkaSelected($kentat);
		//Rakennuksella ja Arvoalueella voi olla inventointinumero valittuna
		$useStyleWithInventointinumero = false;
		//Rakennuksella on voi olla purettu valittuna
		$useStyleWithPurettu = false;

		if($tasoNimi == 'rakennus') {
		    $useStyleWithInventointinumero = self::isInventointinumeroSelected($kentat);
		    $useStyleWithPurettu = self::isPurettuSelected($kentat);
		    if($useStyleWithArvoluokka == true && $useStyleWithInventointinumero == true && $useStyleWithPurettu == true) {
		        $tyyli = $tasoNimi;
		    } else {
		        $tyyli = $tasoNimi . "_nocolors";
		    }
		} else if($tasoNimi == 'arvoalue') {
		    $useStyleWithInventointinumero = self::isInventointinumeroSelected($kentat);
		    if($useStyleWithArvoluokka == true && $useStyleWithInventointinumero == true) {
		        $tyyli = $tasoNimi;
		    } else {
		        $tyyli = $tasoNimi . "_nocolors";
		    }
		} else {
		  $tyyli = $useStyleWithArvoluokka == true ? $tasoNimi : $tasoNimi."_nocolors";
		}

		$editXML = '<layer>
					    <defaultStyle>
					        <name>mip:' . $tyyli. '</name>
					        <workspace>mip</workspace>
					        <atom:link rel="alternate" href= "'. $this->geoserverUri . 'workspaces/mip/styles/' . $tyyli . '.xml" type="application/xml"/>
					    </defaultStyle>
					</layer>';

		$client = new Client();

		$editRes = $client->request('PUT', $updateAddress, [
				'headers' => ['Content-Type' => 'text/xml'],
				'auth' => [$this->username, $this->password],
				'body' => $editXML
		]);

		if($editRes->getStatusCode() !="200") {
			throw new Exception($nimiAlaviivoilla . " muokkaus epäonnistui: " . $editRes->getStatusCode() . " : " . $editRes->getReasonPhrase());
		}
	}

	/*
	 * Julkinen metodi jolla tasoja voidaan luoda ja niiden oletustyyli asetetaan automaattisesti.
	 */
	public function publishLayer($julkaisuNimi, $tasoNimi, $inventointiprojektit, $kentat, $kuntaIdt, $kylaIdt) {
		try {
		    $this->addFeatureType($julkaisuNimi, $tasoNimi, $inventointiprojektit, $kentat, $kuntaIdt, $kylaIdt);
			$this->editFeatureType($julkaisuNimi, $tasoNimi, $kentat);
			Log::channel('geoserver')->info(self::generateLayernameWithUnderscores($julkaisuNimi, $tasoNimi) ." tason luonti onnistui");
		} catch (Exception $e) {
			Log::channel('geoserver')->error("Exception " . $e);
			throw $e;
		}
	}

	/*
	 * Tason poisto
	 */
	private function deleteLayer($julkaisuNimi, $tasoNimi) {
		$nimiAlaviivoilla = self::generateLayernameWithUnderscores($julkaisuNimi, $tasoNimi);
		$deleteLayerAddress = $this->geoserverUri . "layers/" . $nimiAlaviivoilla;

		//Send the delete layer request
		$client = new Client();

		$res = $client->request('DELETE', $deleteLayerAddress, [
				'auth' => [$this->username, $this->password]
		]);

		if($res->getStatusCode() !="200") {
			throw new Exception("Deleting layer " . $deleteLayerAddress. " failed: " . $res->getStatusCode() . " : " . $res->getReasonPhrase());
		}
		return $res;
	}

	/*
	 * Featuretypen poisto
	 */
	private function deleteFeatureType($julkaisuNimi, $tasoNimi) {

		$nimiAlaviivoilla = self::generateLayernameWithUnderscores($julkaisuNimi, $tasoNimi);

		$deleteFeatureTypeAddress = $this->geoserverUri . "workspaces/" . $this->workspace . "/datastores/" . $this->datastore . "/featuretypes/" . $nimiAlaviivoilla;

		//Send the delete layer request
		$client = new Client();

		$res = $client->request('DELETE', $deleteFeatureTypeAddress, [
				'auth' => [$this->username, $this->password]
		]);

		if($res->getStatusCode() !="200") {
			throw new Exception("Deleting featuretype " . $nimiAlaviivoilla. " failed: " . $res->getStatusCode() . " : " . $res->getReasonPhrase());
		}
	}

	private function deleteRequest($deleteAddress){
		try{
			$client = new Client();

			$res = $client->request('DELETE', $deleteAddress, [
					'auth' => [$this->username, $this->password]
			]);
		}
		catch (ClientException $e) {
			if($e->hasResponse()){
				return $e->getResponse()->getStatusCode();
			}
		}
		catch (Exception $e) {
			Log::channel('geoserver')->error("Exception: " . $e->getMessage());
			throw $e;
		}
		return $res->getStatusCode();
	}

	/*
	 * Julkinen metodi jolla poistetaan taso ja sen käyttämä featuretype
	 * Tason poistaminen EI poista featuretypeä. Jos pelkka taso poistetaan, featuretype jää elämään geoserverille ja tämän jälkeen uuden samannimisen tason (/featuretypen) luominen feilaa.
	 * Järjestys on tärkeä, koska featuretypeä EI voida poistaa jos joku taso käyttää sitä.
	 */
	public function deleteLayerAndFeatureType($julkaisuNimi, $tasoNimi) {
		try {
			Log::channel('geoserver')->info("Deleting layer " . self::generateLayername($julkaisuNimi, $tasoNimi));
			$response = $this->deleteLayer($julkaisuNimi, $tasoNimi);
			if ($response != "200"){
				Log::channel('geoserver')->error("Exception: Layer not deleted");
				throw new Exception("Problem deleting layer.");
			}
			$this->deleteFeatureType($julkaisuNimi, $tasoNimi);
		} catch (Exception $e) {
			Log::channel('geoserver')->error("Exception: " . $e->getMessage());
			throw $e;
		}
	}

	/*
	 * Create layergroup
	 * Returns 501 "Not Implemented", so the functionality is not in use.
	 * The function should be OK though...
	 * TODO: Separate method for Deleting layergroup.
	 */
	/*
	public function createLayerGroup($julkaisuNimi, $tasot) {
		$address = $this->geoserverUri . 'rest/workspaces/' . $this->workspace . '/layergroups';

		$createXML = '<layerGroup>
						<name>' . $julkaisuNimi . '</name>
						<mode>SINGLE</mode>
						<title>' . $julkaisuNimi . '</title>
						<workspace>
						<name>' . $this->workspace. '</name>
						</workspace>
						<publishables>';

		foreach($tasot as $taso) {
			$createXML .= '<published type="layer">
							 <name>' . $julkaisuNimi . '_' . $taso['nimi'] . '</name>
							 <atom:link xmlns:atom="http://www.w3.org/2005/Atom" rel="alternate" href="' . $this->geoserverUri . 'layers/' . $julkaisuNimi .'_' . $taso['nimi'] .'.xml" type="application/xml"/>
						   </published>';
		}

		$createXML .= ' </publishables>
						<styles>
						<style>
						<name>mip:alue</name>
						<atom:link xmlns:atom="http://www.w3.org/2005/Atom" rel="alternate" href="http://app049:8080/geoserver/rest/workspaces/mip/styles/alue.xml" type="application/xml"/>
						</style>
						<style>
						<name>mip:arvoalue</name>
						<atom:link xmlns:atom="http://www.w3.org/2005/Atom" rel="alternate" href="http://app049:8080/geoserver/rest/workspaces/mip/styles/arvoalue.xml" type="application/xml"/>
						</style>
						<style>
						<name>mip:kiinteisto</name>
						<atom:link xmlns:atom="http://www.w3.org/2005/Atom" rel="alternate" href="http://app049:8080/geoserver/rest/workspaces/mip/styles/kiinteisto.xml" type="application/xml"/>
						</style>
						<style>
						<name>mip:rakennus</name>
						<atom:link xmlns:atom="http://www.w3.org/2005/Atom" rel="alternate" href="http://app049:8080/geoserver/rest/workspaces/mip/styles/rakennus.xml" type="application/xml"/>
						</style>
						</styles>
						<bounds>
						<minx>43547.78932226647</minx>
						<maxx>764796.7155847414</maxx>
						<miny>6523158.091198515</miny>
						<maxy>7795461.187543589</maxy>
						<crs class="projected">EPSG:3067</crs>
						</bounds>
					  </layerGroup>';


		$client = new Client();


		try {
			$createRes = $client->request('POSTT', $address, [
					'headers' => ['Content-Type' => 'text/xml'],
					'auth' => [$this->username, $this->password],
					'body' => $createXML
			]);

			if($editRes->getStatusCode() !="200") {
				throw new Exception($julkaisuNimi . " tasoryhmän luonti epäonnistui: " . $createRes->getStatusCode() . " : " . $createRes->getReasonPhrase());
			}
		} catch (Exception $e) {
			throw $e;
		}
	}
	*/
}