<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MuistotKuva extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create("muistot_kuva", function(Blueprint $table) {
            $table->integer("prikka_id")->primary();
            $table->integer('muistot_muisto_id');
            $table->boolean('ccby');
            $table->boolean('ehdot');
            $table->text('polku');
            $table->text('nimi');
            $table->text('kuvaus');
            $table->text('ottohetki');
            $table->text('ottopaikka');
            $table->text('valokuvaaja');

            $table->integer("luoja")->nullable();
            $table->timestamp("luotu")->default( DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();

            $table->foreign('muistot_muisto_id')->references('prikka_id')->on('muistot_muisto');
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
    }
}
