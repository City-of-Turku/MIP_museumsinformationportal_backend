<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class KoriKayttaja extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kori_kayttajat', function(Blueprint $table){
            $table->bigIncrements("id"); // PK
            $table->bigInteger("kori_id");
            $table->jsonb("kayttaja_id_lista");
            $table->boolean("museon_kori");

            $table->foreign("kori_id")->references("id")->on("kori");
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kori_kayttajat');
    }
}
