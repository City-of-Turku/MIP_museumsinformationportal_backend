<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MuistotAihe extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create("muistot_aihe", function(Blueprint $table) {
            $table->integer("prikka_id")->primary();
            $table->timestamp('aukeaa')->nullable();
            $table->timestamp('sulkeutuu')->nullable();
            $table->text('aihe_fi')->nullable();
            $table->text('aihe_sv')->nullable();
            $table->text('aihe_en')->nullable();
            $table->text('esittely_fi')->nullable();
            $table->text('esittely_en')->nullable();
            $table->text('esittely_sv')->nullable();

            $table->integer("luoja")->nullable();
            $table->timestamp("luotu")->default( DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();

            $table->text('aiheen_vari')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }
}
