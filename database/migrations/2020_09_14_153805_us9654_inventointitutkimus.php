<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Us9654Inventointitutkimus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Inventointi-tutkimuksen kentät
        $tablename = 'ark_tutkimus';
        Schema::table($tablename, function($table) {
            
            $table->text('toimeksiantaja')->nullable();
            $table->text('kuvaus')->nullable();
        });
        
        $tablename = 'ark_tutkimus_kayttaja';
        Schema::table($tablename, function($table) {
            
            $table->text('organisaatio')->nullable();
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
