<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MuistotAiheKayttaja extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('muistot_aihe_kayttaja', function(Blueprint $table){
            $table->bigIncrements("id"); // PK
            $table->integer("muistot_aihe_id");
            $table->bigInteger("kayttaja_id");
            $table->integer("luoja");
            $table->timestamp("luotu")->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();

            //$table->foreign("kori_id")->references("id")->on("kori");
            $table->foreign('muistot_aihe_id')->references('prikka_id')->on('muistot_aihe');
            $table->foreign('kayttaja_id')->references('id')->on('kayttaja');

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
        Schema::dropIfExists('muistot_aihe_kayttaja');
    }
}
