<?php
// filepath: c:\Users\jkananen\workspaces\MIP\MIP_museumsinformationportal_backend\app\Integrations\MMLBuildingMapper.php

namespace App\Integrations;

class MMLBuildingMapper
{
    // Koodilistat
    public static $CODELISTS = [
        'main_purpose' => [
            '01' => 'Vapaa-ajan asuinrakennus',
            '02' => 'Toimisto-, tuotanto-, yhdyskuntatekniikan tai muut rakennukset',
            '03' => 'Talousrakennus',
            '04' => 'Saunarakennus',
            '05' => 'Pientalo',
            '06' => 'Kerrostalo',
            '07' => 'Julkinen rakennus',
        ],
        'usage_status' => [
            '01' => 'Käytetään vakinaiseen asumiseen',
            '02' => 'Toimitila- tai tuotantokäytössä',
            '03' => 'Käytetään loma-asumiseen',
            '04' => 'Käytetään muuhun tilapäiseen asumiseen',
            '05' => 'Tyhjillään',
            '06' => 'Purettu uudisrakentamisen vuoksi',
            '07' => 'Purettu muusta syystä',
            '08' => 'Tuhoutunut',
            '09' => 'Ränsistymisen vuoksi hylätty',
            '10' => 'Käytöstä ei ole tietoa',
            '11' => 'Muu (sauna, liiteri, kellotapuli, yms.)',
        ],
        'facade_material' => [
            '00' => 'Ei tiedossa',
            '01' => 'Betoni',
            '02' => 'Tiili',
            '03' => 'Metallilevy',
            '04' => 'Kivi',
            '05' => 'Puu',
            '06' => 'Lasi',
            '99' => 'Muu',
        ],
        'heating_method' => [
            '01' => 'Vesikeskuslämmitys',
            '02' => 'Ilmakeskuslämmitys',
            '03' => 'Sähkölämmitys',
            '04' => 'Uuni-takka-kamiinalämmitys',
            '05' => 'Aurinkolämmitys',
            '06' => 'Ilmalämpöpumppu',
            '07' => 'Ei kiinteää lämmityslaitetta',
            '99' => 'Muu',
        ],
        'heating_energy_source' => [
            '01' => 'Kauko- tai aluelämpö',
            '02' => 'Kevyt polttoöljy',
            '03' => 'Raskas polttoöljy',
            '04' => 'Sähkö',
            '05' => 'Kaasu',
            '06' => 'Kivihiili',
            '07' => 'Puu',
            '08' => 'Turve',
            '09' => 'Maalämpö tms.',
            '10' => 'Aurinkoenergia',
            '11' => 'Lämpöpumppu',
            '99' => 'Muu',
        ],
        'material_of_load_bearing_structures' => [
            '00' => 'Ei tiedossa',
            '01' => 'Betoni',
            '02' => 'Tiili',
            '03' => 'Teräs',
            '04' => 'Puu',
            '99' => 'Muu',
        ],
        'construction_method' => [
            '01' => 'Elementti',
            '02' => 'Paikalla tehty',
        ],
        // Lisää muita koodilistoja tarpeen mukaan
    ];

    // Palauttaa koodin nimen URI:sta
    public static function mapUri($key, $uri)
    {
        if (preg_match('/code\/([A-Za-z0-9]+)/', $uri, $matches)) {
            $code = $matches[1];
            if (isset(self::$CODELISTS[$key][$code])) {
                return self::$CODELISTS[$key][$code];
            }
            return $code;
        }
        return $uri;
    }

    // Mapper: Muunna open_building XML:n kentät selkokielisiksi
    public static function mapBuilding($fields)
    {
        // Muunna valmistunut dd.mm.yyyy-muotoon
        $valmistunut_raw = (string)($fields->completion_date ?? '');
        $valmistunut = '';
        if (!empty($valmistunut_raw)) {
            $dt = date_create($valmistunut_raw);
            if ($dt) {
                $valmistunut = $dt->format('d.m.Y');
            }
        }
        // Muunna muokattu dd.mm.yyyy-muotoon
        $muokattu_raw = (string)($fields->modified_timestamp_utc ?? '');
        $muokattu = '';
        if (!empty($muokattu_raw)) {
            $dt2 = date_create($muokattu_raw);
            if ($dt2) {
                $muokattu = $dt2->format('d.m.Y');
            }
        }
        return [
            'osoitteet' => [],
            'postinumero' => '',
            'rakennustunnus' => (string)($fields->permanent_building_identifier ?? ''),
            'kiinteistötunnus' => (string)($fields->property_identifier ?? ''),
            'valmistunut' => $valmistunut,
            'käyttötarkoitus' => self::mapUri('main_purpose', (string)($fields->main_purpose ?? '')),
            'käytössäolotilanne' => self::mapUri('usage_status', (string)($fields->usage_status ?? '')),
            'julkisivumateriaali' => self::mapUri('facade_material', (string)($fields->facade_material ?? '')),
            'lämmönlähde' => self::mapUri('heating_energy_source', (string)($fields->heating_energy_source ?? '')),
            'lämmitystapa' => self::mapUri('heating_method', (string)($fields->heating_method ?? '')),
            'kantava rakenne' => self::mapUri('material_of_load_bearing_structures', (string)($fields->material_of_load_bearing_structures ?? '')),
            'rakennustapa' => self::mapUri('construction_method', (string)($fields->construction_method ?? '')),
            'tilavuus' => (string)($fields->volume ?? ''),
            'kerrosluku' => (string)($fields->number_of_storeys ?? ''),
            'kerrosala' => (string)($fields->gross_floor_area ?? ''),
            'kokonaisala' => (string)($fields->total_area ?? ''),
            'huoneistoala' => (string)($fields->floor_area ?? ''),
            'huoneistomäärä' => (string)($fields->apartment_count ?? ''),
            //'is_accessible' => isset($fields->is_accessible) ? (string)$fields->is_accessible : null,
            //'point_location_srid' => (string)($fields->point_location_srid ?? ''),
            'muokattu' => $muokattu,
            'äänestysalue' => (string)($fields->voting_district_number ?? ''),
            'suojelutapa' => isset($fields->protection_method) ? (string)$fields->protection_method : null,
            'kulttuurihistoriallinen arvo' => isset($fields->culture_historical_significance) ? (string)$fields->culture_historical_significance : null,
            'sijainti' => isset($fields->point_location_geometry_data->Point->pos)
                ? (string)$fields->point_location_geometry_data->Point->pos : '',
            'rakennusavain' => (string)($fields->building_key ?? ''),
            // Lisää muita kenttiä tarpeen mukaan
        ];
    }
}