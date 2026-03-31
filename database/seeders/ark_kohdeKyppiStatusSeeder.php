<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Integrations\KyppiService;
use App\Ark\Kohde;
use Exception;

class ark_kohdeKyppiStatusSeeder extends Seeder
{
    /**
     * Päivittää kaikki kohteet, joiden kyppi_status = 2 Kyppi-järjestelmästä.
     * Suorituskomento: php artisan db:seed --class=ark_kohdeKyppiStatusSeeder
     *
     * @return void
     */
    public function run()
    {
        $paivitettyja = 0;
        $kyppiService = new KyppiService();

        // Haetaan kaikki kohteet, joiden kyppi_status = 2
        $kohteet = Kohde::where('kyppi_status', 2)->limit(200)->get();

        foreach ($kohteet as $kohde) {
            try {
                // Haetaan uusin tieto Kyppi-palvelusta
                $muinaisjaannosXml = $kyppiService->haeMuinaisjaannosSoap($kohde->muinaisjaannostunnus, false);
                $muinaisjaannosDom = new \DOMDocument('1.0', 'utf-8');
                $muinaisjaannosDom->loadXml($muinaisjaannosXml);

                // Päivitetään kohde
                $kyppiService->lisaaTaiPaivitaKohde($muinaisjaannosDom, $kohde, true);
                $paivitettyja++;

                Log::channel('kyppi')->info('ark_kohdeKyppiStatusSeeder paivitettiin kohde: ' . $kohde->muinaisjaannostunnus);

                // Sleep to avoid overloading Kyppi
                sleep(1);
            } catch (Exception $e) {
                Log::channel('kyppi')->error('ark_kohdeKyppiStatusSeeder exception: ' . $e);
            }
        }

        Log::channel('kyppi')->info('ark_kohdeKyppiStatusSeeder paivitettyja kohteita: ' . $paivitettyja);
    }
}
