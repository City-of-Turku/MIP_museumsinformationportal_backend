<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ArkKuvaRontgenkuva extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create("ark_kuva_rontgenkuva", function(Blueprint $table){
            $table->integer("ark_kuva_id");
            $table->integer("ark_rontgenkuva_id");
            $table->integer("jarjestys");
            
            $table->integer("luoja");
            $table->timestamp("luotu")->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();
            
            $table->foreign("ark_kuva_id")->references("id")->on("ark_kuva");
            $table->foreign("ark_rontgenkuva_id")->references("id")->on("ark_rontgenkuva");
            
            $table->foreign("luoja")->references("id")->on("kayttaja");
            $table->foreign("muokkaaja")->references("id")->on("kayttaja");
            $table->foreign("poistaja")->references("id")->on("kayttaja");
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
