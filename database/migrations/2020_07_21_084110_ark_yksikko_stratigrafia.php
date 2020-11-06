<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ArkYksikkoStratigrafia extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
		Schema::create("ark_yksikko_stratigrafia", function(Blueprint $table){
            
            $table->bigIncrements("id"); // PK
			$table->integer("yksikko1");
			$table->integer("yksikko2");
			$table->text("suhde");
			$table->integer("luoja");
            $table->timestamp("luotu")->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();
            $table->foreign('yksikko1')->references('id')->on('ark_tutkimusalue_yksikko');
            $table->foreign('yksikko2')->references('id')->on('ark_tutkimusalue_yksikko');
            $table->foreign('luoja')->references('id')->on('kayttaja');
            $table->foreign('muokkaaja')->references('id')->on('kayttaja');
            $table->foreign('poistaja')->references('id')->on('kayttaja');
		});
		
		Schema::table('ark_tutkimusalue_yksikko', function($table) {
            $table->text("yksikko_kuuluu")->nullable();
            $table->text("yksikko_leikkaa")->nullable();
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
		Schema::dropIfExists("ark_yksikko_stratigrafia");
    }
}
