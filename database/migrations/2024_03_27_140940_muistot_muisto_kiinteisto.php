<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MuistotMuistoKiinteisto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('muistot_muisto_kiinteisto', function (Blueprint $table) {
            $table->integer('muistot_muisto_id');
            $table->integer('kiinteisto_id');
            $table->unique(['muistot_muisto_id', 'kiinteisto_id']);
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
