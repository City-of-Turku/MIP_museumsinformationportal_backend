<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ArkKohdelajiUudet extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('ark_kohdelaji')->insert([
            [
                'id' => 10,
                'nimi_fi' => 'Havaintokohde',
                'luoja' => -1
            ],
            [
                'id' => 11,
                'nimi_fi' => 'Talousvyöhykkeen kohde',
                'luoja' => -1
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
