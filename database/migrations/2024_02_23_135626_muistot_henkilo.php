<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MuistotHenkilo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create("muistot_henkilo", function(Blueprint $table) {
            $table->integer("prikka_id")->primary();
            $table->integer('syntymavuosi')->nullable();
            $table->text('nimimerkki')->nullable();
            $table->text('etunimi')->nullable();
            $table->text('sukunimi')->nullable();
            $table->text('sahkoposti')->nullable();

            $table->integer("luoja")->nullable();
            $table->timestamp("luotu")->default( DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();
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
