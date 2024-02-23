<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MuistotKysymys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create("muistot_kysymys", function(Blueprint $table) {
            $table->integer("prikka_id")->primary();
            $table->integer('muistot_aihe_id');
            $table->text('kysymysteksti_fi')->nullable();
            $table->text('kysymysteksti_sv')->nullable();
            $table->text('kysymysteksti_en')->nullable();

            $table->integer("luoja")->nullable();
            $table->timestamp("luotu")->default( DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();

            $table->foreign('muistot_aihe_id')->references('prikka_id')->on('muistot_aihe');
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
