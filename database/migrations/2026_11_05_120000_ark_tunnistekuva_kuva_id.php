<?php

use App\Utils;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Tunnistekuva siirretään ark_kuva.tunnistekuva-lipusta entiteetin omaksi osoittimeksi.
 * ark_kuva.tunnistekuva jää vanhaksi dataksi (yksiköt ja raporttien ak.*).
 */
class ArkTunnistekuvaKuvaId extends Migration
{
    private $taulut = ['ark_kohde', 'ark_loyto', 'ark_nayte', 'ark_tutkimus'];

    // Välitaulut, joiden nykyisistä lipuista osoittimet täytetään. Tutkimus aloittaa tyhjänä.
    private $valitaulut = [
        'ark_kohde' => ['ark_kuva_kohde', 'ark_kohde_id'],
        'ark_loyto' => ['ark_kuva_loyto', 'ark_loyto_id'],
        'ark_nayte' => ['ark_kuva_nayte', 'ark_nayte_id'],
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Audit-triggerit vaativat käyttäjän
        Utils::setDBUserAsSystem();

        foreach($this->taulut as $taulu) {
            Schema::table($taulu, function (Blueprint $table) {
                $table->bigInteger('tunnistekuva_kuva_id')->nullable();
                $table->foreign('tunnistekuva_kuva_id')->references('id')->on('ark_kuva')->onDelete('set null');
            });
        }

        // Yksi kuva per entiteetti: löydöllä ensisijaisesti luetteloimaton, sitten pienin id. Muilla pienin id.
        foreach($this->valitaulut as $taulu => list($valitaulu, $sarake)) {
            $jarjestys = $taulu == 'ark_loyto' ? "coalesce(k.luettelointinumero, '') <> '', k.id" : "k.id";

            DB::statement("
                update $taulu e set tunnistekuva_kuva_id = v.kuva_id from (
                    select distinct on (x.$sarake) x.$sarake as entiteetti_id, k.id as kuva_id
                    from $valitaulu x
                    join ark_kuva k on k.id = x.ark_kuva_id
                    where k.tunnistekuva and k.poistettu is null and x.poistettu is null
                    order by x.$sarake, $jarjestys
                ) v
                where v.entiteetti_id = e.id
            ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Utils::setDBUserAsSystem();

        // Osoittimet takaisin lipuiksi. Lippuja vain lisätään, koska nollaus osuisi myös yksiköiden lippuihin.
        // Tutkimuksen osoittimia ei palauteta, koska vanha koodi tulkitsisi ne löytöjen lipuiksi.
        DB::statement("
            update ark_kuva set tunnistekuva = true
            where id in (
                select tunnistekuva_kuva_id from ark_kohde where tunnistekuva_kuva_id is not null
                union select tunnistekuva_kuva_id from ark_loyto where tunnistekuva_kuva_id is not null
                union select tunnistekuva_kuva_id from ark_nayte where tunnistekuva_kuva_id is not null
            )
            and tunnistekuva is not true
        ");

        foreach($this->taulut as $taulu) {
            Schema::table($taulu, function (Blueprint $table) {
                $table->dropForeign(['tunnistekuva_kuva_id']);
                $table->dropColumn('tunnistekuva_kuva_id');
            });
        }
    }
}
