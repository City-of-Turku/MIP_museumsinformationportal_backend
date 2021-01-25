<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Integrations\KyppiService;
use App\Ark\Kohde;
use App\Utils;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;

class ark_kohdeNightlySeeder extends Seeder
{

    /**
     * Uusien ja päivitettyjen muinaisjäännösten haku Kyppi-järjestelmästä.
     * Tiedot tallennetaan ja merkitään uudeksi tai päivitetyksi, jonka jälkeen UI:sta voidaan hakea uudet ja muuttuneet.
     * Ajastetaan cron:lla ajettavaksi joka yö.
     * Annetun muutospäivän jälkeen muutetut tai uudet tiedot palautetaan Kypistä.
     * Jotta saadaan eilisen päivän muutokset, käytetään ajopvm - 2 päivää hakupäivänä.
     * Ajo tallentaa seuraavan hakupäivän tauluun ark_kohde_nightly.
     *
     * Suorituskomento: php artisan db:seed --class=ark_kohdeNightlySeeder
     *
     * Kyppi testilinkki:
     * https://paikkatieto.nba.fi/mvrpptesti/MjrekiRajapinta.asmx?op=HaeMuinaisjaannokset
     *
     * @return void
     */
    public function run()
    {
        // Laskurit
        global $uusia;
        global $paivitettyja;
        $uusia = 0;
        $paivitettyja = 0;

        $kyppiService = new KyppiService();

        // Haetaan viimeisin lisätty hakupäivä
        $haku = DB::table('ark_kohde_nightly')->orderBy('seuraava_hakupvm', 'desc')->first();

        if(empty($haku)){
            // echo 'Tallennettua hakupaivaa ei loytynyt, kaytetaan kuluvaa paivaa.' .PHP_EOL;

            Log::info('Tallennettua hakupaivaa ei loytynyt, kaytetaan kuluvaa paivaa.');

            $hakupaiva = date("Y-m-d");
        }else{
            $hakupaiva = $haku->seuraava_hakupvm;
        }

        // Testi: kovakoodattu pvm
        // $hakupaiva = '2020-11-19';

        // echo 'KyppiService: HaeMuinaisjaannoksetSoap(' .$hakupaiva .')' .PHP_EOL;

        // Aikaa alkaa nyt
        $time_start = microtime(true);

        try {

            $maakunnat = config('app.kyppi_haku_maakunnat');
            $maakunnat = array_filter(explode(',', $maakunnat)); // array_filter koska muuten lista on [""] (koko 1)

            for($i = 0; $i < sizeof($maakunnat); $i++) {
                // Kyppi kutsu Maakuntien alueelle
                $xml = $kyppiService->haeMuinaisjaannoksetSoap($hakupaiva, null, trim($maakunnat[$i]));

                // Luodaan dom
                $dom = $this->luoDom();
                $dom->loadXML($xml);

                // Varsinais-suomen kohteiden läpikäynti
                $this->muodostaKohde($kyppiService, $dom);
            }

            $kunnat = config('app.kyppi_haku_kunnat');
            $kunnat = array_filter(explode(',', $kunnat)); // array_filter koska muuten lista on [""] (koko 1)

            for($i = 0; $i<sizeof($kunnat); $i++) {
                // Kyppi kutsu kunnalla
                $xmlKunta = $kyppiService->haeMuinaisjaannoksetSoap($hakupaiva, trim($kunnat[$i]), null);

                // Luodaan dom
                $domKunta = $this->luoDom();
                $domKunta->loadXML($xmlKunta);

                // Kunnan kohteiden läpikäynti
                $this->muodostaKohde($kyppiService, $domKunta);
            }

            /*
             * Päivitetään yöllisen ajon lopuksi seuraavan ajon pvm valmiiksi. Kuluva päivä - 2 päivää.
             */
            $seuraavaPvm =  strtotime ( '-2 day' , strtotime ( date("Y-m-d") ) ) ;
            $seuraavaPvm = date('Y-m-d', $seuraavaPvm);

            try {
                DB::beginTransaction();
                Utils::setDBUserAsSystem();

                DB::table('ark_kohde_nightly')->insert(
                    ['seuraava_hakupvm' => $seuraavaPvm]
                    );

                DB::commit();
            } catch (Exception $e) {
                Log::error('ark_kohdeNightlySeeder exception: ' .$e);
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('ark_kohdeNightlySeeder exception: ' .$e);

            try {
                $message_content = "Kohde nightly seeder fail. \n" . $e;

                $mail_sent = Mail::raw($message_content, function($message) {
                $message->from('mip@mip.fi', 'MIP');
                $message->to(config('app.kyppi_admin_email'))->subject("MIP / " . App::environment());
                });
            } catch (Swift_TransportException $e) {
                Log::error('Sending mail failed: ' . $e);
            }
        }

        $time_end = microtime(true);
        $execution_time = ($time_end - $time_start);

        Log::info('ark_kohdeNightlySeeder suoritusaika: ' .round($execution_time, 2));
        Log::info('ark_kohdeNightlySeeder uusia kohteita: ' .$uusia);
        Log::info('ark_kohdeNightlySeeder paivitettyja kohteita: ' .$paivitettyja);

        // echo 'Suoritusaika: '.round($execution_time, 2) .' sec' .PHP_EOL;
        // echo 'Uusia kohteita: ' .$uusia .PHP_EOL;
        // echo 'Paivitettyja kohteita: ' .$paivitettyja .PHP_EOL;

    }

    /**
     * Uusi DomDocument object
     */
    private function luoDom(){

        $dom = new \DOMDocument( '1.0', 'utf-8' );
        $dom->preserveWhiteSpace = false;

        return $dom;
    }

    /**
     * Muodostetaan uusi kohde tai päivitetään olemassa olevaa.
     */
    private function muodostaKohde($kyppiService, $dom){

        // Kaikki muinaisjäännös elementit
        $muinaisjaannokset = $dom->getElementsByTagName( 'muinaisjaannos' );

        foreach ($muinaisjaannokset as $muinaisjaannos) {

            $muinaisjaannostunnus = $muinaisjaannos->getElementsByTagName('muinaisjaannostunnus')->item(0)->nodeValue;

            // Luodaan oma dom
            $muinaisjaannosDom = new \DOMDocument( '1.0', 'utf-8' );

            // Kyppi kutsu, vastaus xml muodossa yhdelle muinaisjäännökselle
            $muinaisjaannosXml = $kyppiService->haeMuinaisjaannosSoap($muinaisjaannostunnus, false);
            $muinaisjaannosDom->loadXml($muinaisjaannosXml);

            // Kohteen haku MIP:stä
            $kohde = Kohde::where('muinaisjaannostunnus', $muinaisjaannostunnus)->first();

            if( empty($kohde) ){

                Log::info('ark_kohdeNightlySeeder tuodaan uusi kohde: ' .$muinaisjaannostunnus);

                // echo 'Tuodaan uusi kohde: ' .$muinaisjaannostunnus .PHP_EOL;

                // Lisätään
                $kyppiService->lisaaTaiPaivitaKohde($muinaisjaannosDom, null, false);

                // laskuri
                global $uusia;
                $uusia++;

            }else{
                // echo 'Kohde on jo MIP:ssa, paivitetaan kohteen status: ' .$muinaisjaannostunnus .PHP_EOL;
                Log::info('ark_kohdeNightlySeeder paivitetaan kohteen status: ' .$muinaisjaannostunnus);

                // Päivitetään vain status
                $kyppiService->paivitaKohteenStatus($kohde);

                // laskuri
                global $paivitettyja;
                $paivitettyja++;
            }
        }
    }
}