<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Utils;
use Exception;

class MuistotRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // wrap the operations into a transaction
        DB::beginTransaction();
        Utils::setDBUserAsSystem();

        try {

            DB:Statement("")

        } catch (Exception $e) {
            DB::rollback();
            print "----------- Running seed 'system_kayttaja_seeder' failed.\n";
            throw $e;
        }

        DB::commit();
        print "----------- Seed 'system_kayttaja_seeder' finished.\n";

    }
}
