<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ArvotustyyppiJarjestys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Lisätään järjestysnumero kenttä, jonka mukaan järjestetään kentät
        Schema::table('arvotustyyppi', function($table) {
            $table->integer("jarjestys")->nullable();
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
