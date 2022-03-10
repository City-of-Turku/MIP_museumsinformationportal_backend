<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Utils;
use Exception;

class system_kayttaja_seeder extends Seeder
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

            DB::table('kayttaja')->insert([
            	'id' => -1,
            	'aktiivinen' => true,
            	'etunimi' => 'System',
            	'sukunimi' => 'System',
            	'sahkoposti' => '',
            	'salasana' => ''
            ]);

            DB::table('kayttaja')->insert([
                'id' => 1,
                'aktiivinen' => true,
                'rooli' => 'pääkäyttäjä',
                'ark_rooli' => 'pääkäyttäjä',
                'etunimi' => 'paakayttaja',
                'sukunimi' => 'paakayttaja',
                'sahkoposti' => 'paakayttaja@example.fi',
                'salasana' => bcrypt('1ChangeMeAfterCreation!')
            ]);

        } catch (Exception $e) {
            DB::rollback();
            print "----------- Running seed 'system_kayttaja_seeder' failed.\n";
            throw $e;
        }

        DB::commit();
        print "----------- Seed 'system_kayttaja_seeder' finished.\n";

    }
}
