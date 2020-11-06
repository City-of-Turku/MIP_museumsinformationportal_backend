<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Us7688InvTutkimusKohteet extends Migration
{
    /**
     * Inventointi-tutkimuksen kohteiden välitaulu.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ark_tutkimus_inv_kohteet', function($table) {
            $table->bigIncrements("id");
            
            $table->bigInteger('ark_tutkimus_id');
            $table->bigInteger('ark_kohde_id');
            
            $table->timestamp("luotu")->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("luoja");
            
            $table->foreign("ark_tutkimus_id")->references("id")->on("ark_tutkimus");
            $table->foreign("ark_kohde_id")->references("id")->on("ark_kohde");
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
