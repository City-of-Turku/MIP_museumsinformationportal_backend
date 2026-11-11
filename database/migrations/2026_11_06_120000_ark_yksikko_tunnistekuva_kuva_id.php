<?php

use App\Utils;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Yksikön tunnistekuva (tutkimusraportin yksikkökuva) ark_kuva.tunnistekuva-lipusta yksikön omaksi osoittimeksi,
 * kuten kohteella, löydöllä, näytteellä ja tutkimuksella (2026_11_05_120000_ark_tunnistekuva_kuva_id).
 */
class ArkYksikkoTunnistekuvaKuvaId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Audit-triggerit vaativat käyttäjän
        Utils::setDBUserAsSystem();

        Schema::table('ark_tutkimusalue_yksikko', function (Blueprint $table) {
            $table->bigInteger('tunnistekuva_kuva_id')->nullable();
            $table->foreign('tunnistekuva_kuva_id')->references('id')->on('ark_kuva')->onDelete('set null');
        });

        // Yksi kuva per yksikkö: pienin id
        DB::statement("
            update ark_tutkimusalue_yksikko e set tunnistekuva_kuva_id = v.kuva_id from (
                select distinct on (x.ark_yksikko_id) x.ark_yksikko_id as entiteetti_id, k.id as kuva_id
                from ark_kuva_yksikko x
                join ark_kuva k on k.id = x.ark_kuva_id
                where k.tunnistekuva and k.poistettu is null and x.poistettu is null
                order by x.ark_yksikko_id, k.id
            ) v
            where v.entiteetti_id = e.id
        ");

        // Kuvan poisto nollaa osoittimet tunnistekuva_kuva_id:n perusteella
        DB::statement("create index ark_tutkimusalue_yksikko_tunnistekuva_kuva_id_index on ark_tutkimusalue_yksikko (tunnistekuva_kuva_id) where tunnistekuva_kuva_id is not null");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Utils::setDBUserAsSystem();

        // Osoittimet takaisin lipuiksi. Lippuja vain lisätään, kuten edellisessä migraatiossa.
        DB::statement("
            update ark_kuva set tunnistekuva = true
            where id in (select tunnistekuva_kuva_id from ark_tutkimusalue_yksikko where tunnistekuva_kuva_id is not null)
            and tunnistekuva is not true
        ");

        DB::statement("drop index if exists ark_tutkimusalue_yksikko_tunnistekuva_kuva_id_index");
        Schema::table('ark_tutkimusalue_yksikko', function (Blueprint $table) {
            $table->dropForeign(['tunnistekuva_kuva_id']);
            $table->dropColumn('tunnistekuva_kuva_id');
        });
    }
}
