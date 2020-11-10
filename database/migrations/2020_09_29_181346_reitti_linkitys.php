<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReittiLinkitys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Luodaan taulu käyttäjien tallentamia reittejä varten
        Schema::create("reitti", function(Blueprint $table) {
            $table->bigIncrements("id"); // PK
            $table->text('kuvaus')->nullable(); // Kuvaus/nimi
            // Alla olevat kentät voivat olla tyhjiä, jos halutaan rakentaa käyttäjän "reittikirjasto", jossa linkitys tehdään myöhemmin
            $table->text('entiteetti_tyyppi')->nullable(); // Tyyppi johon linkitetään, Kiinteisto, Kohde, Inventointitutkimus, Tarkastustutkimus
            $table->integer('entiteetti_id')->nullable(); // ID johon linkitetään

            $table->integer("luoja");
            $table->timestamp("luotu")->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();

            $table->foreign('luoja')->references('id')->on('kayttaja');
            $table->foreign('muokkaaja')->references('id')->on('kayttaja');
            $table->foreign('poistaja')->references('id')->on('kayttaja');
        });

        // no geometry types in laravel
        DB::statement("alter table reitti add column reitti geometry(Geometry, 3067)"); // Sisältää kaikki pisteet, ei ainostaan yhtä
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists("reitti");
    }
}
