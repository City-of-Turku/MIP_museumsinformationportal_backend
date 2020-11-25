<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Us10297LoytoSisaisetLisatiedot extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Lisätään löydölle sisäiset lisätiedot kenttä
        Schema::table('ark_loyto', function($table) {
            $table->text("sisaiset_lisatiedot")->nullable();
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
