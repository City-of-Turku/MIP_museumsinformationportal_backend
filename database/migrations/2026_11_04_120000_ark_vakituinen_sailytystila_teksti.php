<?php

use App\Utils;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 26948: Säilytystila vapaaksi tekstiksi.
 * Lisätään tekstisarake vakituinen_sailytystila ja täytetään se valintalistan nimellä.
 * Vanha vakituinen_sailytystila_id, sen FK:t ja ark_sailytystila jäävät ennalleen (poistetaan myöhemmin).
 */
class ArkVakituinenSailytystilaTeksti extends Migration
{
    private $taulut = ['ark_loyto', 'ark_nayte', 'ark_loyto_tapahtumat', 'ark_nayte_tapahtumat'];

    public function up()
    {
        try {
            DB::beginTransaction();
            // Audit-triggeri vaatii application.userid:n
            Utils::setDBUserAsSystem();

            foreach ($this->taulut as $taulu) {
                Schema::table($taulu, function (Blueprint $table) {
                    $table->text('vakituinen_sailytystila')->nullable();
                });

                DB::statement("
                    UPDATE $taulu t
                    SET vakituinen_sailytystila = btrim(s.nimi_fi)
                    FROM ark_sailytystila s
                    WHERE s.id = t.vakituinen_sailytystila_id
                      AND nullif(btrim(s.nimi_fi), '') IS NOT NULL
                ");
            }
        } catch (Exception $e) {
            DB::rollback();
            print "----------- Migration 'ark_vakituinen_sailytystila_teksti' failed.\n";
            throw $e;
        }

        DB::commit();
    }

    public function down()
    {
        foreach ($this->taulut as $taulu) {
            Schema::table($taulu, function (Blueprint $table) {
                $table->dropColumn('vakituinen_sailytystila');
            });
        }
    }
}
