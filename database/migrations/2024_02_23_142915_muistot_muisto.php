<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MuistotMuisto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create("muistot_muisto", function(Blueprint $table) {
            $table->integer("prikka_id")->primary();
            $table->integer('muistot_aihe_id');
            $table->integer('muistot_henkilo_id');
            $table->timestamp('luotu')->nullable();
            $table->timestamp('paivitetty')->nullable();
            $table->text('kuvaus')->nullable();
            $table->timestamp('alkaa')->nullable();
            $table->timestamp('loppuu')->nullable();
            $table->boolean('poistettu');
            $table->boolean('ilmiannettu');
            $table->boolean('julkinen');
            $table->text('kieli');
            $table->boolean('paikka_summittainen');


            $table->integer("luoja")->nullable();
            $table->timestamp("luotu_mip")->default( DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu_mip")->nullable();

            $table->foreign('muistot_aihe_id')->references('prikka_id')->on('muistot_aihe');
            $table->foreign('muistot_henkilo_id')->references('prikka_id')->on('muistot_henkilo');
        });

        DB::statement('ALTER TABLE muistot_muisto ADD COLUMN tapahtumapaikka public.geometry(Point,3067) NULL;');
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
