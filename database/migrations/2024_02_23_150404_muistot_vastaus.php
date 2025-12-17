<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MuistotVastaus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create("muistot_vastaus", function(Blueprint $table) {
            $table->integer('muistot_muisto_id');
            $table->integer('muistot_kysymys_id');
            $table->text('vastaus');

            $table->integer("luoja")->nullable();
            $table->timestamp("luotu")->default( DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();

            $table->foreign('muistot_muisto_id')->references('prikka_id')->on('muistot_muisto');
            $table->foreign('muistot_kysymys_id')->references('prikka_id')->on('muistot_kysymys');
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
