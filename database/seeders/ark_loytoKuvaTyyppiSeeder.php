<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Ark\Loyto;
use App\Utils;
use Illuminate\Support\Facades\App;
use Exception;

class ark_loytoKuvaTyyppiSeeder extends Seeder
{

    /**
     * Löydön kuvien läpikäynti, jos löytyy konservointivaihe_id merkitään konservoinnin kuvaksi = 2, muut löydön kuvat = 1, muut kuvat 0
     *
     * Suorituskomento: php artisan db:seed --class=ark_loytoKuvaTyyppiSeeder
     *
     *
     * @return void
     */
    public function run()
    {
        $konservoinnit = DB::table('ark_kuva_loyto')
        ->select('ark_kuva_loyto.ark_kuva_id')
        ->leftjoin('ark_kuva', 'ark_kuva_loyto.ark_kuva_id', '=', 'ark_kuva.id')
        ->whereNotNull('ark_kuva.konservointivaihe_id');

        $loydot = DB::table('ark_kuva_loyto')
        ->select('ark_kuva_loyto.ark_kuva_id')
        ->leftjoin('ark_kuva', 'ark_kuva_loyto.ark_kuva_id', '=', 'ark_kuva.id')
        ->whereNull('ark_kuva.konservointivaihe_id');

        Log::debug("Konservoinnin kuvia " . Utils::getCount($konservoinnit));
        Log::debug("Löydön kuvia " . Utils::getCount($loydot));

        DB::beginTransaction();
        Utils::setDBUserAsSystem();

        $konservointi_rows = DB::table('ark_kuva_loyto')
        ->leftjoin('ark_kuva', 'ark_kuva_loyto.ark_kuva_id', '=', 'ark_kuva.id')
        ->whereNotNull('ark_kuva.konservointivaihe_id')
        ->update(['ark_kuva_loyto.kuva_tyyppi' => 2]);

        $loyto_rows = DB::table('ark_kuva_loyto')
        ->leftjoin('ark_kuva', 'ark_kuva_loyto.ark_kuva_id', '=', 'ark_kuva.id')
        ->whereNull('ark_kuva.konservointivaihe_id')
        ->update(['ark_kuva_loyto.kuva_tyyppi' => 1]);

        DB::commit();

        Log::debug("Konservointi " . json_encode($konservointi_rows));
        Log::debug("Löytöjä " . json_encode($loyto_rows));
    }

}