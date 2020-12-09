<?php

namespace App\Library\Geometry;

use App\Library\Gis\MipGis;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Exception;

class GeomFileReader {
    /**
     * Tiedoston parsiminen - eli palautetaan json muodossa kaikki polygonit (ja pisteet?) mitä tiedostosta saadaan irti.
     * Kaikki koordinaatit jotka tulevat sisään on oltava projektiossa 3067 (ETRS-TM35).
     *
     * @param  $type
     * @param  $file
     */
    public static function readGeometriesFromFile($type, $file) {

        //Geojson tyyppinen kollektio joka palautetaan UI:lle asti.
        $features = array();
        /*
         * Käsitellään tiedosto tiedostotyypin mukaan
         */
        switch ($type) {
            case 'gpx':
            	$features = GeomFileReader::readGPX($file, $features);
                break;
            case 'shp':
            	$features = GeomFileReader::readShapeFile($file, $features);
                break;
            case 'csv':
            	$features = GeomFileReader::readCSV($file, $features);
                break;
            case 'mif':
            	$features = GeomFileReader::readMIF($file, $features);
                break;
            case 'dxf':
            	$features = GeomFileReader::readDXF($file, $features);
                break;
            case 'coordinate_dxf':
                $features = GeomFileReader::readCoordinateDXF($file, $features);
                break;
            case 'coordinate_csv':
                $features = GeomFileReader::readCoordinateCSV($file, $features);
                break;
            /*case 'CoordinateSHP': //Tämä ei toimi, viimeinen rivi jää lukematta.
                $features = GeomFileReader::readCoordinateShapefile($file, $features);
            break;*/
            default:
                throw new Exception(Lang::get('tiedosto.invalid_file_type'), 1);
            break;
        }

        return json_encode($features);
    }

    private static function readGPX($file, $features) {
        try {
            //Avataan tiedosto
            $myFile = fopen($file, 'r');

            // Käsitellään kuten XML
            $xml = simplexml_load_file ( $file );

            foreach ( $xml->trk as $trk ) {
                //Array joka sisältää yhden polygonin pisteet
                $geometry = 'POLYGON((';

                $seg = $trk->trkseg;
                //Lisätään kaikki pisteet $points -arrayhyn
                foreach ( $seg->trkpt as $point ) {
                    //$coord = array((float)$point['lon'], (float)$point['lat']);
                    $geometry .= (float)$point['lon'] . " " . (float)$point['lat'] . ',';

                    //array_push( $points, $coord);
                }

                $geometry = rtrim($geometry, ',');
                $geometry.= '))';
                try {
	                //Konvertoidaan koordinaatit UI:n käyttämään projektioon (4326)
	                $convertedCoords = MipGis::transformSSRID(3067, 4326, $geometry);
	                //Muutetaan geojsoniksi
	                $asGeoJson = MipGis::asGeoJson($convertedCoords);
	                //Dekoodataan, jotta tässä vaiheessa ei käsitellä pelkkää stringiä
	                $geom = json_decode($asGeoJson);
                } catch (Exception $e) {
                	if($e->getCode() == 'XX000') {
                	    throw new Exception(Lang::get('tiedosto.input_file_geometry_contains_non_closed_rings') . " - $geometry", 7);
                	} else {
                		throw $e;
                	}
                }

                //Tehdään feature
                $feature = array ('type' => 'Feature', 'properties' => array('name' => (string)$trk->name[0]),
                        'geometry' =>  $geom);
                //Lisätään feature features -arrayhyn
                array_push($features, $feature);
            }
        } catch(Exception $e) {
        	throw $e;
        }
        return $features;
    }

    private static function readShapefile($file, $features) {
        try {
            // Open shapefile
            $ShapeFile = new ShapeFile($file);
            // Sets default return format
            $ShapeFile->setDefaultGeometryFormat(ShapeFile::GEOMETRY_WKT);

            $totalRecords = $ShapeFile->getTotRecords();
            for($i = 1; $i<= $totalRecords; ++$i) {
                $record = $ShapeFile->getRecord();
                //Skipataan deletoidut recordit (mitä ikinä tarkoittaakaan)...
                 if ($record['dbf']['_deleted']) continue;

                /*
                 * Geometria-osuus
                 */
                //Luetaan koordinaatit
                $geometry = $record['shp'];
                //Vaihdetaan päittäin, koska lon ja lat ovat eripäin kuin PostGis vaatii
                $flippedGeometry = MipGis::flipCoords($geometry);
                //Konvertoidaan koordinaatit UI:n käyttämään projektioon (4326)
                $convertedCoords = MipGis::transformSSRID(3067, 4326, $flippedGeometry);
                //Muutetaan geojsoniksi
                $asGeoJson = MipGis::asGeoJson($convertedCoords);
                //Dekoodataan, jotta tässä vaiheessa ei käsitellä pelkkää stringiä
                $geometry = json_decode($asGeoJson);

                /*
                 * Properties-osuus - tulee suoraan objektina
                 */
                $properties = $record['dbf'];

                /*
                 * Tehdään tiedoista feature
                 */
                $feature = array ('type' => 'Feature', 'properties' => $properties,
                        'geometry' =>  $geometry
                );

                //Lisätään feature palautettavaan arrayhyn
                array_push($features, $feature);
            }
        } catch (ShapeFileException $e) {
        	if($e->getCode() == 32) {//Polygon not valid
        		//Jos vielä on recordeja jäljellä, siirrytään seuraavaan
        		//if($i < $totalRecords) {
        		//    $ShapeFile->setCurrentRecord($ShapeFile->getCurrentRecord() + 1);
        		//}
        	    throw new Exception(Lang::get('tiedosto.polygon_not_valid') . " - $geometry", 8);
        	} else {
        		Log::debug($e->getMessage() . " " . $e->getCode());
        		$errorMessage = $e->getMessage();
        		$errorCode = $e->getCode();
        		throw new Exception($errorMessage, $errorCode);
        	}
        }
        //Palautetaan json-enkoodattuna featuret
        return $features;
    }

    /**
     * Luetaan aluetiedot csv-muotoisesta tiedostosta.
     * @param $file - csv-tiedosto. dwg-muotoinen tiedosto joka on Trimblessä muutettu csv:ksi
     * @param $features - array johon tiedostosta luetut featuret tallennetaan
     */
    private static function readCSV($file, $features) {
        if (($handle = fopen($file, "r")) !== FALSE) {
            $lines = array();

            /*
             * Luetaan csv sisältö rivi kerrallaan arrayhyn.
             */
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $data = array_map("utf8_encode", $data); //UTF8 encode - äöå aiheuttaa muuten ongelmia
                array_push($lines, $data);
            }

            $result = array();

            /*
             * Niputetaan array nimen perusteella.
             * Nimi on 4. sarakkeessa.
             */
            foreach($lines as $k => $v) {
                $result[$v[4]][$k] = $v;
            }

            /*
             * Käydään jokainen avain (=alueen nimi) läpi ja kerätään kaikki sen alla olevien rivien koordinaatit
             * yhteen ja tehdään feature.
             */
            foreach($result as $k => $v) {
                $points = array();
                foreach($v as $kk => $vv) {
                    $pair = array($vv[2], $vv[1]);
                    array_push($points, $pair);
                }

                //Tehdään koordinaateista WKT, jotta voidaan hyödyntää PostGissiä muokkauksessa.
                $wkt = 'POLYGON((';
                for($i = 0; $i < sizeof($points); $i++) {
                    $wkt .= $points[$i][0]. " " . $points[$i][1] . ",";
                }
                $wkt = rtrim($wkt, ',');
                $wkt .=  '))';

                try {
                    //Konvertoidaan koordinaatit UI:n käyttämään projektioon (4326)
                    $convertedCoords = MipGis::transformSSRID(3067, 4326, $wkt);
                    //Muutetaan geojsoniksi
                    $asGeoJson = MipGis::asGeoJson($convertedCoords);
                    //Dekoodataan, jotta tässä vaiheessa ei käsitellä pelkkää stringiä
                    $geom = json_decode($asGeoJson);
                } catch (Exception $e) {
                	if($e->getCode() == 'XX000') {
                		/* Palautetaan virhe. Tämä voi aiheutua myös ainestossa olevista saman nimisistä kentistä, jotka eivät ole peräkkäin.
                		 */
                		throw new Exception(Lang::get('tiedosto.input_file_geometry_contains_non_closed_rings') . " - $k", 7);
                	} else {
                		throw $e;
                	}
                }

                $feature = array('geometry' => $geom,
                                 'properties' =>  array(
                                    'name' => $k
                                 ), 'type' => 'Feature'
                           );

                //Lisätään luotu feature palautettavaan arrayhin
                array_push($features, $feature);
            }

            fclose($handle);
        }
        return $features;
    }

    /**
     * Luetaan aluetiedot mif-muotoisesta tiedostosta.
     * @param $file - csv-tiedosto. dwg-muotoinen tiedosto joka on Trimblessä muutettu csv:ksi
     * @param $features - array johon tiedostosta luetut featuret tallennetaan
     */
    private static function readMIF($file, $features) {
        if (($handle = fopen($file, "r")) !== FALSE) {

            /*
             * Arrayt jotka sisältävät sanat joilla halutut alueet aloitetaan ja lopetetaan.
             * Case insensitive, vertailussa käytetään lowercasea.
             */
            $startStrings = array('PLINE');
            $endStrings = array('PEN');

            /*
             * Muuttuja $doWrite asetetaan TRUEksi kun alueen aloittava merkki löydetään
             */
            $doWrite = false;

            /*
             * WKT-muotoinen string johon kerätään yhden lohkon koordinaatit
             */
            $geometry = '';

            /*
             * Luetaan csv sisältö rivi kerrallaan
             * Aina kun vastaan tulee sana "PLINE", pushataan tuo arrayhyn.
             * Kaikki sen jälkeen vastaan tulevat rivit menevät tuon alle koordinaattehin,
             * kunnes vastaan tulee sana " PEN" ja homma aloitetaan alusta.
             */
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                //UTF8 encode - äöå aiheuttaa muuten ongelmia
                $data = array_map("utf8_encode", $data);
                //Arrayn ainoa elementti stringiksi
                $data = $data[0];

                //Jos rivi sisältää halutun aloitusmerkin, aloitetaan kirjoittaminen
                if(GeomFileReader::lineContainsKeyword($data, $startStrings)) {
                    $doWrite = true;
                    $geometry = "POLYGON(("; //WKT-muotoinen geometria alkaa tällä
                    //Otetaan talteen ja käytetään nimenä. $name sisältämää lukua voitaisiin käyttää verifiointiin;
                    //ilmeisesti sisältää yhtä monta koordinaattiparia kuin tämän luku.
                    $name = $data;
                } else if(GeomFileReader::lineContainsKeyword($data, $endStrings)) {
                    //Rivi sisälsi lopetusmerkin, lopetetaan tallentaminen.
                    $doWrite = false;
                    $geometry =  rtrim($geometry ,','); //Trimmataan viimeinen , pois viimeisen koordinaattiparin perästä WKT-stringistä.
                    $geometry .= '))'; //Lisätään WKT:n sulkevat sulkeet

                    /*
                     * Yksi koordinaattipari loppuu tähän. Yhden polygonin pitäisi nyt olla valmis.
                     * Tehdään hieman käsittelyä polygonille ja lisätään palautettavaan $features arrayhyn
                     *
                     * TODO: Voitaisiin tehdä vertailu että geometria sisältää oikeasti yhtä monta paria kuin $name muuttajassa on määritetty.
                     */
                    if(substr($geometry, -2) == '))') {
                    	try {
	                        //Konvertoidaan koordinaatit UI:n käyttämään projektioon (4326)
	                        $convertedCoords = MipGis::transformSSRID(3067, 4326, $geometry);
	                        //Muutetaan geojsoniksi
	                        $asGeoJson = MipGis::asGeoJson($convertedCoords);
	                        //Dekoodataan, jotta tässä vaiheessa ei käsitellä pelkkää stringiä
	                        $geom = json_decode($asGeoJson);
                    	} catch (Exception $e) {

                    		if($e->getCode() == 'XX000') {
                    		    // Näytetään koko polygon käyttäjälle
                    		    throw new Exception(Lang::get('tiedosto.input_file_geometry_contains_non_closed_rings') . " - $geometry", 7);
                    		} else {
                    			throw $e;
                    		}
                    	}

                        /*
                         * Tehdään tiedoista feature
                         */
                        $feature = array ('type' => 'Feature', 'properties' => array('name' => $name),
                                'geometry' =>  $geom
                        );

                        array_push($features, $feature);
                    }
                } else {
                    /*
                     * Rivi ei sisällä aloitus- tai lopetusmerkkiä.
                     * Jos aloitusmerkin jälkeen ollaan edetty ja vielä ei ole tullut vastaan lopetusmerkkiä ($doWrite on tällöin true),
                     * otetaan rivit (sisältävät koordinaatteja) talteen.
                     */
                    if($doWrite) {
                        $geometry .= $data . ",";

                    }
                }
            }

            fclose($handle);
        }

        return $features;
    }

    /**
     * Luetaan aluetiedot dxf-muotoisesta tiedostosta.
     * @param $file - dxf-tiedosto. dxf-muotoinen tiedosto
     * @param $features - array johon tiedostosta luetut featuret tallennetaan
     */
    private static function readDXF($file, $features) {
        if (($handle = fopen($file, "r")) !== FALSE) {
            /*
             * Arrayt jotka sisältävät sanat joilla halutut alueet aloitetaan ja lopetetaan.
             * Case insensitive, vertailussa käytetään lowercasea.
             * Eri tiedostoista löydetty alla olevat termit tähän mennessä.
             */
            $startStrings = array('POLYLINE', 'LWPOLYLINE');
            $endStrings = array('SEQEND', 'ENDSEC');

            /*
             * Merkit joilla tietyt tietueet erotetaan.
             * http://images.autodesk.com/adsk/files/autocad_2012_pdf_dxf-reference_enu.pdf
             */
            $latIndicator = '10';
            $lonIndicator = '20';
            $elevationIndicator = '30';
            $nameIndicator = '8';

            /*
             * Edellisen rivin sisältö. Otetaan talteen, jos edellinen rivi on sisältänyt
             * esim $latIndicator tiedetään, että nykyinen rivi sisältää silloin koordinaatin
             */
            $previousLine = '';

            /*
             * Muuttuja $doWrite asetetaan TRUEksi kun lohkon aloittava merkki löydetään.
             * Asetetaan FALSEksi kun lohkon lopetusmerkki tulee vastaan.
             */
            $doWrite = false;

            /*
             * WKT-muotoinen string johon kerätään yhden lohkon koordinaatit
             */
            $geometry = '';
            $name = '';
            /*
             * Luetaan csv sisältö rivi kerrallaan
             * Aina kun vastaan tulee sana "PLINE", pushataan tuo arrayhyn.
             * Kaikki sen jälkeen vastaan tulevat rivit menevät tuon alle koordinaattehin,
             * kunnes vastaan tulee sana " PEN" ja homma aloitetaan alusta.
             */
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                //UTF8 encode - äöå aiheuttaa muuten ongelmia
                $data = array_map("utf8_encode", $data);
                //Arrayn ainoa elementti stringiksi
                $data = $data[0];

                //Jos rivi sisältää halutun aloitusmerkin, aloitetaan kirjoittaminen
                if(!$doWrite && GeomFileReader::lineContainsKeyword($data, $startStrings)) {
                    $doWrite = true;
                    $geometry = "POLYGON(("; //WKT-muotoinen geometria alkaa tällä
                    //Otetaan talteen ja käytetään nimenä.
                    //$name = $data;
                    $previousLine = $data;
                } else if($doWrite && GeomFileReader::lineContainsKeyword($data, $endStrings)) {
                    $previousLine = $data;
                    //Rivi sisälsi lopetusmerkin, lopetetaan tallentaminen.
                    $doWrite = false;
                    $geometry =  rtrim($geometry ,','); //Trimmataan viimeinen , pois viimeisen koordinaattiparin perästä WKT-stringistä.
                    $geometry .= '))'; //Lisätään WKT:n sulkevat sulkeet

                    /*
                     * Yksi koordinaattipari loppuu tähän. Yhden polygonin pitäisi nyt olla valmis.
                     * Tehdään hieman käsittelyä polygonille ja lisätään palautettavaan $features arrayhyn
                     */
                    if(substr($geometry, -2) == '))') {
                        try {
                            //Konvertoidaan koordinaatit UI:n käyttämään projektioon (4326)
                            $convertedCoords = MipGis::transformSSRID(3067, 4326, $geometry);
                            //Muutetaan geojsoniksi
                            $asGeoJson = MipGis::asGeoJson($convertedCoords);
                            //Dekoodataan, jotta tässä vaiheessa ei käsitellä pelkkää stringiä
                            $geom = json_decode($asGeoJson);
                        } catch (Exception $e) {
                            if($e->getCode() == 'XX000') {
                                throw new Exception(Lang::get('tiedosto.input_file_geometry_contains_non_closed_rings') . " - $geometry", 7);
                            } else {
                                throw $e;
                            }
                        }

                        /*
                         * Tehdään tiedoista feature
                         */
                        $feature = array ('type' => 'Feature', 'properties' => array('name' => $name),
                                'geometry' =>  $geom
                        );

                        array_push($features, $feature);
                    }
                } else {
                    /*
                     * Rivi ei sisällä aloitus- tai lopetusmerkkiä.
                     * Jos aloitusmerkin jälkeen ollaan edetty ja vielä ei ole tullut vastaan lopetusmerkkiä ($doWrite on tällöin true),
                     * otetaan rivit (sisältävät koordinaatteja) talteen.
                     */
                    if($doWrite) {
                        if($previousLine == $lonIndicator) {
                            if($data != '0.000000') { //TODO: Fiksattava, ei voi olla kovakoodattu
                                $geometry .= $data . ",";
                                $previousLine = $data;
                            }
                            $previousLine = $data;
                        } else if($previousLine == $latIndicator) {
                        	if($data != '0.000000') {//TODO: Fiksattava, ei voi olla kovakoodattu
                                $geometry .= $data . " ";
                            }
                            $previousLine = $data;
                        } else if($previousLine == $nameIndicator) {
                        	$name = $data;
                        	$previousLine = $data;
                        } else {
                            $previousLine = $data;
                        }
                    }
                }
            }
            fclose($handle);
        }

        return $features;
    }

    public static function readCoordinateDXF($file, $features) {
        if (($handle = fopen($file, "r")) !== FALSE) {
            /*
             * Arrayt jotka sisältävät sanat joilla halutut alueet aloitetaan ja lopetetaan.
             * Case insensitive, vertailussa käytetään lowercasea.
             * Eri tiedostoista löydetty alla olevat termit tähän mennessä.
             */
            $startStrings = array('POLYLINE', 'POINT');
            $endStrings = array('SEQEND', 'ENDSEC');

            /*
             * Merkit joilla tietyt tietueet erotetaan.
             * http://images.autodesk.com/adsk/files/autocad_2012_pdf_dxf-reference_enu.pdf
             */
            $latIndicator = '10';
            $lonIndicator = '20';
            $elevationIndicator = '30';
            $nameIndicator = '1';

            /*
             * Edellisen rivin sisältö. Otetaan talteen, jos edellinen rivi on sisältänyt
             * esim $latIndicator tiedetään, että nykyinen rivi sisältää silloin koordinaatin
             */
            $previousLine = '';

            /*
             * Muuttuja $doWrite asetetaan TRUEksi kun lohkon aloittava merkki löydetään.
             * Asetetaan FALSEksi kun lohkon lopetusmerkki tulee vastaan.
             */
            $doWrite = false;

            /*
             * WKT-muotoinen string johon kerätään yhden lohkon koordinaatit
             */
            $name = '';
            /*
             * Luetaan csv sisältö rivi kerrallaan
             * Aina kun vastaan tulee sana "PLINE", pushataan tuo arrayhyn.
             * Kaikki sen jälkeen vastaan tulevat rivit menevät tuon alle koordinaattehin,
             * kunnes vastaan tulee sana " PEN" ja homma aloitetaan alusta.
             */
            $geometries = [];
            $geo = json_decode('{}');
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                //UTF8 encode - äöå aiheuttaa muuten ongelmia
                $data = array_map("utf8_encode", $data);
                //Arrayn ainoa elementti stringiksi
                $data = $data[0];
                //Jos rivi sisältää halutun aloitusmerkin, aloitetaan kirjoittaminen
                if(!$doWrite && GeomFileReader::lineContainsKeyword($data, $startStrings)) {
                    $doWrite = true;
                    //Otetaan talteen ja käytetään nimenä.
                    //$name = $data;
                    $previousLine = $data;
                    $geo = json_decode('{}');
                } else if($doWrite && GeomFileReader::lineContainsKeyword($data, $endStrings)) {
                    $previousLine = $data;
                    //Rivi sisälsi lopetusmerkin, lopetetaan tallentaminen.
                    $doWrite = false;
                    $feature = array ('type' => 'Feature', 'properties' => array('name' => $name),
                        'geometry' =>  $geometries
                    );
                    array_push($features, $feature);

                } else {
                    /*
                     * Rivi ei sisällä aloitus- tai lopetusmerkkiä.
                     * Jos aloitusmerkin jälkeen ollaan edetty ja vielä ei ole tullut vastaan lopetusmerkkiä ($doWrite on tällöin true),
                     * otetaan rivit (sisältävät koordinaatteja) talteen.
                     */
                    if($doWrite) {
                        if($previousLine == $elevationIndicator) {
                            if($data != '0.000000') { //TODO: Fiksattava, ei voi olla kovakoodattu
                                $previousLine = $data;
                                $geo->ele = trim($data);
                            }
                            $previousLine = $data;
                        } else if($previousLine == $latIndicator) {
                            if($data != '0.000000') {//TODO: Fiksattava, ei voi olla kovakoodattu
                                $geo->lat = trim($data);
                            }
                            $previousLine = $data;
                        } else if($previousLine == $lonIndicator) {
                            if($data != '0.000000') {//TODO: Fiksattava, ei voi olla kovakoodattu
                                $geo->lon = trim($data);
                            }
                            $previousLine = $data;
                        } else if($previousLine == $nameIndicator) {
                            $name = $data;
                            $previousLine = $data;
                            $geo->name = trim($data);
                            array_push($geometries, $geo);
                            $geo = json_decode('{}');
                        } else {
                            $previousLine = $data;
                        }

                    }
                }
            }
            fclose($handle);
        }

        return $features;
    }

    private static function readCoordinateCSV($file, $features) {
        if (($handle = fopen($file, "r")) !== FALSE) {
            $geometries = array();

            $latIndicator = '0';
            $lonIndicator = '1';
            $elevationIndicator = '2';
            $minRowCount = 4; //X, Y, Z ja Text sarakkeet pitää vähintään olla
            
            $data = fgetcsv($handle, 1000, ",");
            if (count($data) < $minRowCount){
                throw new Exception(Lang::get('tiedosto.invalid_csv_file'));
            }            
            $nameIndicator = count($data) - 1;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $data = array_map("utf8_encode", $data); //UTF8 encode - äöå aiheuttaa muuten ongelmia
                $geo = json_decode('{}');

                if ($data[$nameIndicator] == "" || $data[$nameIndicator] == "Text"){
                    continue;
                }

                $geo->lat = trim($data[$latIndicator]);
                $geo->lon = trim($data[$lonIndicator]);
                $geo->ele = trim($data[$elevationIndicator]);
                $geo->name = trim($data[$nameIndicator]);
                array_push($geometries, $geo);
            }
            $feature = array ('type' => 'Feature', 'geometry' =>  $geometries);
            array_push($features, $feature);
            fclose($handle);
        }
        return $features;
    }

    private static function readCoordinateShapefile($file, $features) {
        try {
            // Open shapefile
            $ShapeFile = new ShapeFile($file);
            // Sets default return format
            $ShapeFile->setDefaultGeometryFormat(ShapeFile::GEOMETRY_WKT);
            $totalRecords = $ShapeFile->getTotRecords();
            for($i = 1; $i<= $totalRecords; ++$i) {
                $record = $ShapeFile->getRecord();
                //Skipataan deletoidut recordit (mitä ikinä tarkoittaakaan)...
                if ($record['dbf']['_deleted']) continue;

                /*
                 * Geometria-osuus
                 */
                //Luetaan koordinaatit
                $geometry = $record['shp'];
                //Vaihdetaan päittäin, koska lon ja lat ovat eripäin kuin PostGis vaatii
                $flippedGeometry = MipGis::flipCoords($geometry);
                //Muutetaan geojsoniksi
                $asGeoJson = MipGis::asGeoJson($flippedGeometry);
                //Dekoodataan, jotta tässä vaiheessa ei käsitellä pelkkää stringiä
                $geometry = json_decode($asGeoJson);

                /*
                 * Properties-osuus - tulee suoraan objektina
                 */
                $properties = $record['dbf'];
                /*
                 * Tehdään tiedoista feature
                 */
                $feature = array ('type' => 'Feature', 'properties' => $properties,
                    'geometry' =>  $geometry
                );
                //Lisätään feature palautettavaan arrayhyn
                array_push($features, $feature);
            }
        } catch (ShapeFileException $e) {
            if($e->getCode() == 32) {//Polygon not valid
                //Jos vielä on recordeja jäljellä, siirrytään seuraavaan
                //if($i < $totalRecords) {
                //    $ShapeFile->setCurrentRecord($ShapeFile->getCurrentRecord() + 1);
                //}
                throw new Exception(Lang::get('tiedosto.polygon_not_valid') . " - $geometry", 8);
            } else {
                $errorMessage = $e->getMessage();
                $errorCode = $e->getCode();
                throw new Exception($errorMessage, $errorCode);
            }
        }
        //Palautetaan json-enkoodattuna featuret
        return $features;
    }

    /**
     * Check if line contains a specific keyword. Case insensitive.
     * @param string $line - Line that will be checked
     * @param array $keywords - Array of keywords that the line is matched against.
     * @return boolean - True if the keyword exists in the line, false otherwise.
     */
    private static function lineContainsKeyword($line, $keywords) {
        foreach($keywords as $word) {
            if(strpos(strtolower($line), strtolower($word)) !== false) {
                $exists = true;
                return true;
            }
        }
        return false;
    }
}