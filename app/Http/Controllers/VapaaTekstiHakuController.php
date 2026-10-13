<?php

namespace App\Http\Controllers;

use App\Kayttaja;
use App\Library\String\MipJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Exception;

class VapaaTekstiHakuController extends Controller {

    /*
     * Hakuindeksin (search_index) item_type => domain ja katseluoikeus.
     * Uusi entiteetti lisätään hakuun lisäämällä tähän yksi rivi.
     */
    private const ITEM_TYPES = [
        'kiinteisto' => ['domain' => 'RAK', 'permission' => 'rakennusinventointi.kiinteisto.katselu'],
        'ark_kohde'  => ['domain' => 'ARK', 'permission' => 'arkeologia.ark_kohde.katselu'],
    ];

    /*
     * Käyttöliittymän moduulit => search_index.domain
     */
    private const MODUULIT = [
        'rak' => 'RAK',
        'ark' => 'ARK',
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {

        try {
            $rivi = (isset($request->rivi) && is_numeric($request->rivi)) ? max(0, (int) $request->rivi) : 0;
            $riveja = (isset($request->rivit) && is_numeric($request->rivit)) ? max(0, (int) $request->rivit) : 100;

            $hakusana = $request->input("hakusana");
            $hakusana = is_string($hakusana) ? trim($hakusana) : "";

            $domains = $this->getDomains($request->input("moduulit"));
            $allowed_types = $this->getAllowedItemTypes($domains);

            $entities = [];
            $total_count = 0;

            if($hakusana !== "" && count($domains) > 0 && count($allowed_types) > 0) {
                $domain_placeholders = implode(",", array_fill(0, count($domains), "?"));
                $type_placeholders = implode(",", array_fill(0, count($allowed_types), "?"));

                $sql = "SELECT source_id AS id, item_type, domain, title, tunnus, kunta,
                               ts_rank(search_vector, q, 1) AS rank,
                               count(*) OVER () AS total_count
                        FROM search_index, plainto_tsquery('finnish', ?) q
                        WHERE search_vector @@ q
                          AND domain IN (" . $domain_placeholders . ")
                          AND item_type IN (" . $type_placeholders . ")
                        ORDER BY rank DESC, global_id
                        LIMIT ? OFFSET ?";

                $bindings = array_merge([$hakusana], $domains, $allowed_types, [$riveja, $rivi]);

                foreach(DB::select($sql, $bindings) as $row) {
                    $total_count = (int) $row->total_count;
                    $entities[] = [
                        'id'        => (int) $row->id,
                        'item_type' => $row->item_type,
                        'domain'    => $row->domain,
                        'title'     => $row->title,
                        'tunnus'    => $this->emptyToNull($row->tunnus),
                        'kunta'     => $this->emptyToNull($row->kunta),
                        'rank'      => (float) $row->rank,
                    ];
                }
            }

            MipJson::setGeoJsonFeature();
            MipJson::setData($entities, count($entities), $total_count);
            MipJson::addMessage(Lang::get('vapaatekstihaku.found_count', ["count" => $total_count]));
        } catch(Exception $e) {
            MipJson::setGeoJsonFeature();
            MipJson::setResponseStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
            MipJson::addMessage(Lang::get('vapaatekstihaku.search_failed'));
        }

        return MipJson::getJson();
    }

    /**
     * Muuttaa moduulit-parametrin (esim. "rak,ark") domain-listaksi.
     * Tuntemattomat arvot ohitetaan. Oletuksena kaikki moduulit.
     *
     * @return array
     */
    private function getDomains($moduulit) {
        if(!is_string($moduulit) || trim($moduulit) === "") {
            return array_values(self::MODUULIT);
        }

        $domains = [];
        foreach(explode(",", $moduulit) as $moduuli) {
            $moduuli = mb_strtolower(trim($moduuli));
            if(isset(self::MODUULIT[$moduuli]) && !in_array(self::MODUULIT[$moduuli], $domains)) {
                $domains[] = self::MODUULIT[$moduuli];
            }
        }

        return $domains;
    }

    /**
     * Palauttaa ne item_typet, joihin käyttäjällä on katseluoikeus annetuissa domaineissa.
     *
     * @return array
     */
    private function getAllowedItemTypes(array $domains) {
        $allowed = [];
        foreach(self::ITEM_TYPES as $item_type => $config) {
            if(in_array($config['domain'], $domains) && Kayttaja::hasPermission($config['permission'])) {
                $allowed[] = $item_type;
            }
        }

        return $allowed;
    }

    /**
     * Palauttaa null, jos arvo on tyhjä tai pelkkää välilyöntiä.
     *
     * @return string|null
     */
    private function emptyToNull($value) {
        return ($value === null || trim($value) === "") ? null : $value;
    }
}
