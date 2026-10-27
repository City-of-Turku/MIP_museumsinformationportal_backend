<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnNimimerkkiKuvaajanaToMuistotKuva extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('muistot_kuva', function (Blueprint $table) {
            $table->boolean('nimimerkki_kuvaajana')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('muistot_kuva', function (Blueprint $table) {
            $table->dropColumn('nimimerkki_kuvaajana');
        });
    }
}
