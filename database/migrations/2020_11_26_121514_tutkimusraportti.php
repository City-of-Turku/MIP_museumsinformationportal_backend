<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Tutkimusraportti extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Luodaan taulu tutkimusraporteille
        Schema::create("ark_tutkimusraportti", function (Blueprint $table) {
            $table->bigIncrements("id"); // PK
            $table->bigInteger('ark_tutkimus_id');

            $table->text("arkisto_ja_rekisteritiedot")->nullable();
            $table->text("tiivistelma")->nullable();
            $table->text("johdanto")->nullable();
            $table->text("tutkimus_ja_dokumentointimenetelmat")->nullable();
            $table->text("havainnot")->nullable();
            $table->text("yhteenveto")->nullable();
            $table->text("lahdeluettelo")->nullable();
            $table->text("liitteet")->nullable();

            $table->integer("luoja");
            $table->timestamp("luotu")->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();

            $table->foreign("ark_tutkimus_id")->references("id")->on("ark_tutkimus");
            $table->foreign('luoja')->references('id')->on('kayttaja');
            $table->foreign('muokkaaja')->references('id')->on('kayttaja');
            $table->foreign('poistaja')->references('id')->on('kayttaja');
        });

        // Luodaan taulu tutkimusraportin kuville
        Schema::create("ark_tutkimusraportti_kuva", function (Blueprint $table) {
            $table->bigIncrements("id"); // PK
            $table->bigInteger('ark_tutkimusraportti_id');
            $table->bigInteger('ark_kuva_id'); // Linkitys olemassaoleviin kuviin
            $table->text('kappale')->nullable(); // Mihin raportin kappaleeseen kuva liittyy
            $table->integer('jarjestys')->nullable(); // jarjestys

            $table->integer("luoja");
            $table->timestamp("luotu")->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer("muokkaaja")->nullable();
            $table->timestamp("muokattu")->nullable();
            $table->integer("poistaja")->nullable();
            $table->timestamp("poistettu")->nullable();

            $table->foreign("ark_tutkimusraportti_id")->references("id")->on("ark_tutkimusraportti");
            $table->foreign("ark_kuva_id")->references("id")->on("ark_kuva");
            $table->foreign('luoja')->references('id')->on('kayttaja');
            $table->foreign('muokkaaja')->references('id')->on('kayttaja');
            $table->foreign('poistaja')->references('id')->on('kayttaja');
        });

        // Lisätään tutkimusalueelle yhteenveto kenttä
        Schema::table('ark_tutkimusalue', function($table) {
            $table->text("yhteenveto")->nullable();
        });

        // Lisätään tutkimukselle uudet kentat
        Schema::table('ark_tutkimus', function($table) {
            $table->text("km_paanumerot_ja_diaarnum")->nullable();
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
