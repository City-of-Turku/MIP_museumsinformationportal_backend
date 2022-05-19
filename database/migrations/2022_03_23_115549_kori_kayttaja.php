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
        Schema::create('kori_kayttaja', function(Blueprint $table){
            $table->bigIncrements("id"); // PK
            $table->bigInteger("kori_id");
            $table->integer("kayttaja_id");
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kori_kayttaja');
    }
}
