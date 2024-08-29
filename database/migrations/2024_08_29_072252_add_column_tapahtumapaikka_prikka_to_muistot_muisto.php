<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnTapahtumapaikkaPrikkaToMuistotMuisto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('muistot_muisto', function (Blueprint $table) {
            $table->text('tapahtumapaikka_prikka')->nullable()->after('tapahtumapaikka'); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('muistot_muisto', function (Blueprint $table) {
            $table->dropColumn('tapahtumapaikka_prikka');
        });
    }
}
