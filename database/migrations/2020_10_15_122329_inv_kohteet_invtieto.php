<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InvKohteetInvtieto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Lisätään inventoija ja inventointipaiva kentät
        Schema::table('ark_tutkimus_inv_kohteet', function($table) {
            $table->integer("inventoija_id")->nullable();
            $table->date("inventointipaiva")->nullable();

            $table->foreign("inventoija_id")->references("id")->on("kayttaja");
            $table->foreign("luoja")->references("id")->on("kayttaja"); // Puuttui aiemmasta.
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
