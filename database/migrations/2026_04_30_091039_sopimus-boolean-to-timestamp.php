<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SopimusBooleanToTimestamp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kayttaja', function (Blueprint $table) {
            $table->dropColumn('sopimus_hyvaksytty');
        });
        Schema::table('kayttaja', function (Blueprint $table) {
            $table->timestamp('sopimus_hyvaksytty')->nullable()->after('viim_kirjautuminen');
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
            $table->dropColumn('sopimus_hyvaksytty');
        });
        Schema::table('kayttaja', function (Blueprint $table) {
            $table->boolean('sopimus_hyvaksytty')->default(false)->after('viim_kirjautuminen');
        });
    }
}
