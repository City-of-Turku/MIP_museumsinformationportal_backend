<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ArkTutkimuslajiUusi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('ark_tutkimuslaji')->insert([
            'id' => 13,
            'nimi_fi' => 'Etätarkastus',
            'luoja' => -1,
            'mjr_nimi_fi' => 'Etätarkastus'
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
