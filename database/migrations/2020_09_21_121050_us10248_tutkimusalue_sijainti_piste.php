<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Us10248TutkimusalueSijaintiPiste extends Migration
{
    /**
     * Sijainti = alue (polygon geometry)
     * Sijainti_piste = piste (point geometry)
     *
     * @return void
     */
    public function up()
    {
        DB::statement("alter table ark_tutkimusalue add column sijainti_piste geometry(Geometry, 3067)");
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
