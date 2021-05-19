<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TutkimuslajiKokoelmalajit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Liitetään ark_tutkimuslajiin kokoelmalajit jotka ovat valittavissa ko. tutkimustyypille
        Schema::create("ark_tutkimuslaji_kokoelmalajit", function(Blueprint $table) {
            $table->bigIncrements("id"); // PK
            $table->integer('ark_tutkimuslaji_id');
            $table->integer('ark_kokoelmalaji_id');

            $table->integer("luoja");
            $table->timestamp("luotu")->default( DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();

            $table->foreign('ark_tutkimuslaji_id')->references('id')->on('ark_tutkimuslaji');
            $table->foreign('ark_kokoelmalaji_id')->references('id')->on('ark_kokoelmalaji');


            $table->foreign('luoja')->references('id')->on('kayttaja');
            $table->foreign('muokkaaja')->references('id')->on('kayttaja');
            $table->foreign('poistaja')->references('id')->on('kayttaja');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists("ark_tutkimuslaji_kokoelmalajit");
    }
}
