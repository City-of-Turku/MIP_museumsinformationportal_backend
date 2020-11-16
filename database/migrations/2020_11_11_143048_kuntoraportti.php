<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Kuntoraportti extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Luodaan taulu kuntoraportin tiedoille
        Schema::create("ark_kuntoraportti", function(Blueprint $table){
            $table->bigIncrements("id");

            $table->integer("ark_loyto_id");

            $table->text("kohteen_lisakuvaus")->nullable();
            $table->text("loydon_kunto")->nullable();
            $table->text("kasittelyohjeet")->nullable();
            $table->text("olosuhdesuositukset")->nullable();
            $table->text("pakkausohjeet")->nullable();

            $table->integer("luoja");
            $table->timestamp("luotu")->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();

            $table->foreign("ark_loyto_id")->references("id")->on("ark_loyto");

            $table->foreign("luoja")->references("id")->on("kayttaja");
            $table->foreign("muokkaaja")->references("id")->on("kayttaja");
            $table->foreign("poistaja")->references("id")->on("kayttaja");
        });

        // Lisätään löydölle näyttelyn nimi ja näyttelypaikka.
        Schema::table('ark_loyto', function($table) {
            $table->text("nayttelyn_nimi")->nullable();
            $table->text("nayttelypaikka")->nullable();
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
