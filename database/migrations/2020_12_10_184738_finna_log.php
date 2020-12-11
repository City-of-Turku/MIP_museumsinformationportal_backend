<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FinnaLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Luodaan taulu tutkimusraporteille
        Schema::create("finna_log", function (Blueprint $table) {
            $table->bigIncrements("id"); // PK
            $table->bigInteger('ark_loyto_id');
            $table->timestamp("siirto_pvm")->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign("ark_loyto_id")->references("id")->on("ark_loyto");
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
