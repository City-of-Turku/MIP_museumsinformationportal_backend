<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Kayttaja;
use App\Utils;

// HUOM: TÄMÄ MIGRAATIO ON TEHTY JA AJETTU KEHITYKSEEN, MUTTA TÄTÄ EI TARVITA KOSKA FINNA KÄYTTÄJÄ TEHDÄÄN LENNOSTA FINNAUTILS
// LUOKASSA.
class FinnaIntegrationUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        // wrap the operations into a transaction
        DB::beginTransaction();
        Utils::setDBUserAsSystem();


        try {
            // Luodaan kayttaja tauluun FINNA-integraatiolla oma katselija-roolilla oleva systeemikäyttäjä.
            $u = new Kayttaja();
            $u->id = -2;
            $u->sahkoposti = '';
            $u->salasana = '';
            $u->rooli = 'katselija';
            $u->ark_rooli = 'katselija';

            $u->etunimi = 'FINNA';
            $u->sukunimi = 'INTEGRAATIO';
            $u->save();
        } catch (Exception $e) {
            DB::rollback();
            print "----------- Migration 'finna_integration_user' failed.\n";
            throw $e;
        }

        DB::commit();
        print "Created Finna user:\n";
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

