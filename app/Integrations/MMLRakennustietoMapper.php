<?php
namespace App\Integrations;

class MMLRakennustietoMapper {
	
	/*
	 * Contains static values to map MML building information values to text
	 * Original XSD files can be found from 
	 * http://www.maanmittauslaitos.fi/aineistot-palvelut/rajapintapalvelut/rakennustietojen-kyselypalvelu-wfs/kayttoonotto/versiohallinta
	 * http://xml.nls.fi/Rakennustiedot/VTJRaHu/2010/01/rahu.xsd
	 * 
	 */
	public static $OPTIONS = [
			'julkisivumateriaali' => [
					'1'=> [ 'nimi_fi' => 'betoni', 'nimi_se' => 'betong'],
					'2'=> [ 'nimi_fi' => 'tiili', 'nimi_se' => 'tegel'],
					'3'=> [ 'nimi_fi' => 'metallilevy', 'nimi_se' => 'stål'],
					'4'=> [ 'nimi_fi' => 'kivi', 'nimi_se' => 'sten'],
					'5'=> [ 'nimi_fi' => 'puu', 'nimi_se' => 'trä'],
					'6'=> [ 'nimi_fi' => 'lasi', 'nimi_se' => 'glas'],
					'7'=> [ 'nimi_fi' => 'muu', 'nimi_se' => 'annat']			
			],
			'kaytossaolotilanne' => [
					'01'=> [ 'nimi_fi' => 'käytetään vakituiseen asumiseen', 'nimi_se' => 'används for varaktigt boende'],
					'02'=> [ 'nimi_fi' => 'toimitila- tai tuotantokäytössä', 'nimi_se' => 'verksamhetslokal'],
					'03'=> [ 'nimi_fi' => 'käytetään loma-asumiseen', 'nimi_se' => 'används som fritidsbostad'],
					'04'=> [ 'nimi_fi' => 'käytetään muuhun tilapäiseen asumiseen', 'nimi_se' => 'används för annat tilldälligt boende'],
					'05'=> [ 'nimi_fi' => 'tyhjillään', 'nimi_se' => 'står tom'],
					'06'=> [ 'nimi_fi' => 'purettu uudisrakentamisen vuoksi', 'nimi_se' => 'riven på grund av nubyggnad'],
					'07'=> [ 'nimi_fi' => 'purettu muusta syystä', 'nimi_se' => 'riven av annan orsak'],
					'08'=> [ 'nimi_fi' => 'tuhoutunut', 'nimi_se' => 'förstörd'],
					'09'=> [ 'nimi_fi' => 'ränsistymisen vuoksi hylätty', 'nimi_se' => 'övergiven på grund av förfall'],
					'10'=> [ 'nimi_fi' => 'käytöstä ei ole tietoa', 'nimi_se' => 'ej kännedom om användningen'],
					'11'=> [ 'nimi_fi' => 'sauna, liiteri, kellotapuli ym.', 'nimi_se' => 'annan användning'],
					'12'=> [ 'nimi_fi' => 'tuntematon', 'nimi_se' => 'okänt']
			],
			'kayttotarkoitus' => [
					'011'=> [ 'nimi_fi' => 'yhden asunnon talo', 'nimi_se' => 'hus med en bostad'],
					'012'=> [ 'nimi_fi' => 'paritalot ja kaksikerroksiset omakotitalot, joissa on kaksi asuntoa', 'nimi_se' => 'parhus och egnahem i två våningar med två bostäder'],
					'013'=> [ 'nimi_fi' => 'muu erillinen pientalo', 'nimi_se' => 'övrig fristående småhus'],
					'021'=> [ 'nimi_fi' => 'rivitalo', 'nimi_se' => 'radhus'],
					'022'=> [ 'nimi_fi' => 'ketjutalo', 'nimi_se' => 'kedjehus'],
					'032'=> [ 'nimi_fi' => 'luhtitalo', 'nimi_se' => 'loftgångshus'],
					'039'=> [ 'nimi_fi' => 'muu asuinkerrostalo', 'nimi_se' => 'övrig flervåningsbostadshus'],
					'041'=> [ 'nimi_fi' => 'vapaa-ajan asuinrakennus', 'nimi_se' => 'fritidsbostadshus'],
					'111'=> [ 'nimi_fi' => 'myymälähalli', 'nimi_se' => 'butikshall'],
					'112'=> [ 'nimi_fi' => 'liike- ja tavaratalo, kauppakeskus', 'nimi_se' => 'affärs- och varuhus, handelscentral'],
					'119'=> [ 'nimi_fi' => 'muut myymälärakennukset', 'nimi_se' => 'övriga butiksbyggnader'],
					'121'=> [ 'nimi_fi' => 'hotellit yms.', 'nimi_se' => 'hotell o.dyl.'],
					'123'=> [ 'nimi_fi' => 'loma-, lepo- ja virkistyskodit', 'nimi_se' => 'semester-, vilo- och rekreationshem'],
					'124'=> [ 'nimi_fi' => 'vuokrattavat lomamökit ja -osakkeet', 'nimi_se' => 'semesterstugor och -aktier som hyrs ut'],
					'129'=> [ 'nimi_fi' => 'muut majoitusliikerakennukset', 'nimi_se' => 'övriga byggnader för inkvarteringsanläggningar'],
					'131'=> [ 'nimi_fi' => 'asuntolat yms.', 'nimi_se' => 'kollektivbostadsbyggnader o.dyl.'],
					'139'=> [ 'nimi_fi' => 'muut asuntolarakennukset', 'nimi_se' => 'övriga kollektivbostadsbyggnader'],
					'141'=> [ 'nimi_fi' => 'ravintolat yms.', 'nimi_se' => 'restaurant mm.'],
					'151'=> [ 'nimi_fi' => 'toimistorakennukset', 'nimi_se' => 'kontorsbyggnader'],
					'161'=> [ 'nimi_fi' => 'rautatie- ja linja-autoasemat, lento- ja satamaterminaalit', 'nimi_se' => 'järnvägs- och busstationer, flyg- och hamnterminaler'],
					'162'=> [ 'nimi_fi' => 'kulkuneuvojen suoja- ja huoltorakennukset', 'nimi_se' => 'garage och servicebyggnader för fordon'],
					'163'=> [ 'nimi_fi' => 'pysäköintitalo', 'nimi_se' => 'parkeringshus'],
					'164'=> [ 'nimi_fi' => 'tietoliikenteen rakennukset', 'nimi_se' => 'telekommunikationsbyggnader'],
					'169'=> [ 'nimi_fi' => 'muut liikenteen rakennukset', 'nimi_se' => 'övriga trafikbyggnader'],
					'211'=> [ 'nimi_fi' => 'keskussairaalat', 'nimi_se' => 'centralsjukhus'],
					'213'=> [ 'nimi_fi' => 'muut sairaalat', 'nimi_se' => 'övriga sjukhus'],
					'214'=> [ 'nimi_fi' => 'terveyskeskukset', 'nimi_se' => 'hälso(vårds)centraler'],
					'215'=> [ 'nimi_fi' => 'terveydenhuollon erityislaitokset', 'nimi_se' => 'specialbyggnader inom hälsovård'],
					'219'=> [ 'nimi_fi' => 'muut terveydenhuoltorakennukset', 'nimi_se' => 'övriga hälsovårdsbyggnader'],
					'221'=> [ 'nimi_fi' => 'vanhainkodit', 'nimi_se' => 'ålderdomshem'],
					'222'=> [ 'nimi_fi' => 'lasten- ja koulukodit', 'nimi_se' => 'barnhem och skolhem'],
					'223'=> [ 'nimi_fi' => 'kehitysvammaisten hoitolaitokset', 'nimi_se' => 'vårdanstalter för utvecklingsstörda'],
					'229'=> [ 'nimi_fi' => 'muut huoltolaitosrakennukset', 'nimi_se' => 'övriga vårdanstaltsbyggnader'],
					'231'=> [ 'nimi_fi' => 'lasten päiväkodit', 'nimi_se' => 'barndaghem'],
					'239'=> [ 'nimi_fi' => 'muualla luokittelemattomat sosiaalitoimen rakennukset', 'nimi_se' => 'byggnader inom socialväsendet som inte klassificerats annorstädes'],
					'241'=> [ 'nimi_fi' => 'vankilat', 'nimi_se' => 'fängelser'],
					'311'=> [ 'nimi_fi' => 'teatterit, ooppera-, konsertti- ja kongressitalot', 'nimi_se' => 'teatrar, opera-, konsert- och kongresshus'],
					'312'=> [ 'nimi_fi' => 'elokuvateatterit', 'nimi_se' => 'biografer'],
					'322'=> [ 'nimi_fi' => 'kirjastot ja arkistot', 'nimi_se' => 'bibliotek och arkiv'],
					'323'=> [ 'nimi_fi' => 'museot ja taidegalleriat', 'nimi_se' => 'museer och konstgallerier'],
					'324'=> [ 'nimi_fi' => 'näyttelyhallit', 'nimi_se' => 'utställningshallar'],
					'331'=> [ 'nimi_fi' => 'seura- ja kerhorakennukset yms.', 'nimi_se' => 'förenings- och klubblokalsbyggnader o.dyl.'],
					'341'=> [ 'nimi_fi' => 'kirkot, kappelit, luostarit ja rukoushuoneet', 'nimi_se' => 'kyrkor, kapell, kloster och bönehus'],
					'342'=> [ 'nimi_fi' => 'seurakuntatalot', 'nimi_se' => 'församlingshus'],
					'349'=> [ 'nimi_fi' => 'muut uskonnollisten yhteisöjen rakennukset', 'nimi_se' => 'övriga byggnader för religiösa samfund'],
					'351'=> [ 'nimi_fi' => 'jäähallit', 'nimi_se' => 'ishallar'],
					'352'=> [ 'nimi_fi' => 'uimahallit', 'nimi_se' => 'simhallar'],
					'353'=> [ 'nimi_fi' => 'tennis-, squash- ja sulkapallohallit', 'nimi_se' => 'tennis-, squash- och badmintonhallar'],
					'354'=> [ 'nimi_fi' => 'monitoimihallit ja muut urheiluhallit', 'nimi_se' => 'allaktivitetshallar och övriga idrottshallar'],
					'359'=> [ 'nimi_fi' => 'muut urheilu- ja kuntoilurakennukset', 'nimi_se' => 'övriga idrotts- och motionsbyggnader'],
					'369'=> [ 'nimi_fi' => 'muut kokoontumisrakennukset', 'nimi_se' => 'övriga byggnader för samlingslokaler'],
					'511'=> [ 'nimi_fi' => 'yleissivistävien oppilaitosten rakennukset', 'nimi_se' => 'byggnader för allmänbildande läroanstalter'],
					'521'=> [ 'nimi_fi' => 'ammatillisten oppilaitosten rakennukset', 'nimi_se' => 'byggnader för yrkesläroanstalter'],
					'531'=> [ 'nimi_fi' => 'korkeakoulurakennukset', 'nimi_se' => 'högskolebyggnader'],
					'532'=> [ 'nimi_fi' => 'tutkimuslaitosrakennukset', 'nimi_se' => 'forskningsanstaltsbyggnader'],
					'541'=> [ 'nimi_fi' => 'järjestöjen, liittojen, työnantajien yms. opetusrakennukset', 'nimi_se' => 'organisationers, förbunds, arbetsgivares o.dyl. undervisningsbyggnader'],
					'549'=> [ 'nimi_fi' => 'muualla luokittelemattomat opetusrakennukset', 'nimi_se' => 'undervisningsbyggnader som inte klassificerats annorstädes'],
					'611'=> [ 'nimi_fi' => 'voimalaitosrakennukset', 'nimi_se' => 'kraftverksbyggnader'],
					'613'=> [ 'nimi_fi' => 'yhdyskuntatekniikan rakennukset', 'nimi_se' => 'byggnader för samhällsteknik'],
					'691'=> [ 'nimi_fi' => 'teollisuushallit', 'nimi_se' => 'industrihallar'],
					'692'=> [ 'nimi_fi' => 'teollisuus- ja pienteollisuustalot', 'nimi_se' => 'industri- och småindustrihus'],
					'699'=> [ 'nimi_fi' => 'muut teollisuuden tuotantorakennukset', 'nimi_se' => 'övriga produktionsbyggnader inom industrin'],
					'711'=> [ 'nimi_fi' => 'teollisuusvarastot', 'nimi_se' => 'industrilager'],
					'712'=> [ 'nimi_fi' => 'kauppavarastot', 'nimi_se' => 'handelslager'],
					'719'=> [ 'nimi_fi' => 'muut varastorakennukset', 'nimi_se' => 'övriga lagerbyggnader'],
					'721'=> [ 'nimi_fi' => 'paloasemat', 'nimi_se' => 'brandstationer'],
					'722'=> [ 'nimi_fi' => 'väestönsuojat', 'nimi_se' => 'skyddsrum'],
					'729'=> [ 'nimi_fi' => 'muut palo- ja pelastustoimen rakennukset', 'nimi_se' => 'övriga byggnader för brand- och räddningsväsendet'],
					'811'=> [ 'nimi_fi' => 'navetat, sikalat, kanalat yms.', 'nimi_se' => 'ladugårdar, svinhus, hönsgårdar o.dyl.'],
					'819'=> [ 'nimi_fi' => 'eläinsuojat, ravihevostallit, maneesit yms.', 'nimi_se' => 'djurstall, stall för travhästar, maneger o.dyl.'],
					'891'=> [ 'nimi_fi' => 'viljankuivaamot ja viljan säilytysrakennukset', 'nimi_se' => 'spannmålstorkar och byggnader för uppbevaring av spannmål'],
					'892'=> [ 'nimi_fi' => 'kasvihuoneet', 'nimi_se' => 'växthus'],
					'893'=> [ 'nimi_fi' => 'turkistarhat', 'nimi_se' => 'pälsdjursfarmer'],
					'899'=> [ 'nimi_fi' => 'muut maa-, metsä- ja kalatalouden rakennukset', 'nimi_se' => 'övriga byggnader för jordbruk, skogsbruk och fiske'],
					'931'=> [ 'nimi_fi' => 'saunarakennukset', 'nimi_se' => 'bastubyggnader'],
					'941'=> [ 'nimi_fi' => 'talousrakennukset', 'nimi_se' => 'ekonomibyggnader'],
					'999'=> [ 'nimi_fi' => 'muualla luokittelemattomat rakennukset', 'nimi_se' => 'byggnader som inte klassificerats annorstädes']
			],
			'lammitystapa' => [
					'1'=> [ 'nimi_fi' => 'vesikeskuslämmitys', 'nimi_se' => 'vattencetralvärme'],
					'2'=> [ 'nimi_fi' => 'ilmakeskuslämmitys', 'nimi_se' => 'luftcentralvärme'],
					'3'=> [ 'nimi_fi' => 'suora sähkölämmitys', 'nimi_se' => 'direkt eluppvärmning'],
					'4'=> [ 'nimi_fi' => 'uunilämmitys', 'nimi_se' => 'ugnseldning'],
					'5'=> [ 'nimi_fi' => 'ei kiinteää lämmityslaitetta', 'nimi_se' => 'ej fast värmeanordning']
			],
			'lammonlahde' => [
					'01'=> [ 'nimi_fi' => 'kauko- tai aluelämpö', 'nimi_se' => 'fjärr- eller blockvärme'],
					'02'=> [ 'nimi_fi' => 'kevyt polttoöljy', 'nimi_se' => 'lätt brännolja'],
					'03'=> [ 'nimi_fi' => 'raskas polttoöljy', 'nimi_se' => 'tung brännolja'],
					'04'=> [ 'nimi_fi' => 'sähkö', 'nimi_se' => 'el'],
					'05'=> [ 'nimi_fi' => 'kaasu', 'nimi_se' => 'gas'],
					'06'=> [ 'nimi_fi' => 'kivihiili, koksi, tms.', 'nimi_se' => 'stenkol, koks och dylika'],
					'07'=> [ 'nimi_fi' => 'puu', 'nimi_se' => 'trän'],
					'08'=> [ 'nimi_fi' => 'turve', 'nimi_se' => 'torv'],
					'09'=> [ 'nimi_fi' => 'maalämpö tms.', 'nimi_se' => 'jordvärme och dylika'],
					'10'=> [ 'nimi_fi' => 'muu', 'nimi_se' => 'annat']
			],
			'poikkeusluvansyy' => [
					'1'=> [ 'nimi_fi' => 'rakennuskielto', 'nimi_se' => 'byggnadsförbud'],
					'2'=> [ 'nimi_fi' => 'muu syy', 'nimi_se' => 'anna orsak']
			],
			'rakennusmateriaali' => [ //Rakennusaine in MML XSD
					'1'=> [ 'nimi_fi' => 'betoni', 'nimi_se' => 'betong'],
					'2'=> [ 'nimi_fi' => 'tiili', 'nimi_se' => 'tegel'],
					'3'=> [ 'nimi_fi' => 'teräs', 'nimi_se' => 'stål'],
					'4'=> [ 'nimi_fi' => 'puu', 'nimi_se' => 'trän'],
					'5'=> [ 'nimi_fi' => 'muu', 'nimi_se' => 'annat']
			],
			'rakennuspaikanHallintaperuste' => [
					'1'=> [ 'nimi_fi' => 'oma', 'nimi_se' => 'egen'],
					'2'=> [ 'nimi_fi' => 'vuokrattu', 'nimi_se' => 'hyrd']
			],
			'rakennustapa' => [
					'1'=> [ 'nimi_fi' => 'elementti', 'nimi_se' => 'element'],
					'2'=> [ 'nimi_fi' => 'paikalla tehty', 'nimi_se' => 'uppförd på platsen']
			],
			'sijaintiepavarmuus' => [
					'1'=> [ 'nimi_fi' => '1 m', 'nimi_se' => '1 m'],
					'2'=> [ 'nimi_fi' => '2 m', 'nimi_se' => '2 m'],
					'3'=> [ 'nimi_fi' => '5 m', 'nimi_se' => '5 m'],
					'4'=> [ 'nimi_fi' => '10 m', 'nimi_se' => '10 m'],
					'5'=> [ 'nimi_fi' => '20 m', 'nimi_se' => '20 m'],
					'6'=> [ 'nimi_fi' => '50 m', 'nimi_se' => '50 m'],
					'7'=> [ 'nimi_fi' => '100 m', 'nimi_se' => '100 m'],
					'8'=> [ 'nimi_fi' => '100 m', 'nimi_se' => '100 m'],
					'9'=> [ 'nimi_fi' => '100 m', 'nimi_se' => '100 m'],
					'A'=> [ 'nimi_fi' => 'osuu rakennukseen', 'nimi_se' => 'punkt på byggnad'],
					'B'=> [ 'nimi_fi' => 'osuu kiinteistön alueelle', 'nimi_se' => 'punkt på fastighet']
					
			],
			'kielikoodi' => [
					'fin'=> [ 'nimi_fi' => 'suomi', 'nimi_se' => 'finska'],
					'swe'=> [ 'nimi_fi' => 'ruotsi', 'nimi_se' => 'svenska']
			],
			'rakennustunnuksenVoimassaolo' => [//RakennustunnuksenAktiivisuus
					'1'=> [ 'nimi_fi' => 'aktiivi', 'nimi_se' => 'aktiv'],
					'2'=> [ 'nimi_fi' => 'passiivi', 'nimi_se' => 'passiv'],
					'3'=> [ 'nimi_fi' => 'virheellinen', 'nimi_se' => 'virheellinen'],
					'4'=> [ 'nimi_fi' => 'keinotekoinen', 'nimi_se' => 'keinotekoinen'],
					'5'=> [ 'nimi_fi' => 'vanhat tiedot', 'nimi_se' => 'vanhat tiedot']
			]
	];
	
	/*
	 * Map building $key $value pair to $OPTIONS.
	 * If no value found, return the original value
	 */
	public static function map($key, $value) {
		//$key = 'julkisivumateriaali', $value ='1'
		foreach(self::$OPTIONS as $k => $v) {		
			//$k = 'julkisivumateriaali', $v = '1'
			if($k == $key) {
				foreach($v as $nimi => $val) { 						
					if($nimi == $value) {
						$v[$nimi]['arvo'] = $value;
						return $v[$nimi];
					}
				}
			}
		}
		return $value;
	}
	
}