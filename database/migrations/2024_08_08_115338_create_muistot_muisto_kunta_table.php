<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMuistotMuistoKuntaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('muistot_muisto_kunta', function (Blueprint $table) {
            $table->id();
            $table->integer('kunta_id')->unsigned();
            $table->integer('muisto_id');
            $table->string('kiinteistotunnus')->nullable();
            $table->integer("luoja")->nullable();
            $table->timestamp("luotu")->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();

            $table->foreign('kunta_id')->references('id')->on('kunta')->onDelete('cascade');
            $table->foreign('muisto_id')->references('prikka_id')->on('muistot_muisto')->onDelete('cascade');

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
        Schema::dropIfExists('muistot_muisto_kunta');
    }
}
