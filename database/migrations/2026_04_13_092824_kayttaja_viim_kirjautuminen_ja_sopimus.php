<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class KayttajaViimKirjautuminenJaSopimus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kayttaja', function (Blueprint $table) {
            $table->addColumn('timestamp', 'viim_kirjautuminen')->nullable()->after('tekijanoikeuslauseke');
            $table->addColumn('boolean', 'sopimus_hyvaksytty')->default(false)->after('viim_kirjautuminen');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('kayttaja', function (Blueprint $table) {
            $table->dropColumn('viim_kirjautuminen');
            $table->dropColumn('sopimus_hyvaksytty');
        });
    }
}
