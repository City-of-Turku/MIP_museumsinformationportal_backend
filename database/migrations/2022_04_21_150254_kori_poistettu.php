<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class KoriPoistettu extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kori', function($table) {
            $table->integer('poistaja')->nullable();
            $table->timestamp('poistettu')->nullable();
        });
        //
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('kori', function($table) {
            $table->dropColumn('poistaja');
            $table->dropColumn('poistettu');
        });//
    }
}
