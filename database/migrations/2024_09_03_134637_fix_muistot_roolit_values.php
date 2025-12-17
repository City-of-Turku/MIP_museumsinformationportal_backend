<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Utils;
use Illuminate\Support\Facades\DB;

class FixMuistotRoolitValues extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

      try {
        // wrap the operations into a transaction
        DB::beginTransaction();
        Utils::setDBUserAsSystem();

          // Muisto
          DB::table('jarjestelma_roolit')
          ->where('osio', 'muistot')
          ->where('entiteetti', 'muisto')
          ->where('rooli', 'pääkäyttäjä')
          ->update([
            'katselu' => true,
            'luonti' => false,
            'muokkaus' => true,
            'poisto' => false,
          ]);
          DB::table('jarjestelma_roolit')
          ->where('osio', 'muistot')
          ->where('entiteetti', 'muisto')
          ->where('rooli', 'tutkija')
          ->update([
            'katselu' => true,
            'luonti' => false,
            'muokkaus' => true,
            'poisto' => false,
          ]);
          DB::table('jarjestelma_roolit')
          ->where('osio', 'muistot')
          ->where('entiteetti', 'muisto')
          ->where('rooli', 'ulkopuolinen tutkija')
          ->update([
            'katselu' => true,
            'luonti' => false,
            'muokkaus' => false,
            'poisto' => false,
          ]);
          DB::table('jarjestelma_roolit')
          ->where('osio', 'muistot')
          ->where('entiteetti', 'muisto')
          ->where('rooli', 'katselija')
          ->update([
            'katselu' => true,
            'luonti' => false,
            'muokkaus' => false,
            'poisto' => false,
          ]);
          DB::table('jarjestelma_roolit')
          ->where('osio', 'muistot')
          ->where('entiteetti', 'muisto')
          ->where('rooli', 'inventoija')
          ->update([
            'katselu' => true,
            'luonti' => false,
            'muokkaus' => false,
            'poisto' => false,
          ]);

          // Yksityinen muisto
          DB::table('jarjestelma_roolit')
          ->where('osio', 'muistot')
          ->where('entiteetti', 'yksityinen_muisto')
          ->where('rooli', 'pääkäyttäjä')
          ->update([
            'katselu' => true,
            'luonti' => false,
            'muokkaus' => true,
            'poisto' => false,
          ]);
          DB::table('jarjestelma_roolit')
          ->where('osio', 'muistot')
          ->where('entiteetti', 'yksityinen_muisto')
          ->where('rooli', 'tutkija')
          ->update([
            'katselu' => true,
            'luonti' => false,
            'muokkaus' => true,
            'poisto' => false,
          ]);
          DB::table('jarjestelma_roolit')
          ->where('osio', 'muistot')
          ->where('entiteetti', 'yksityinen_muisto')
          ->where('rooli', 'ulkopuolinen tutkija')
          ->update([
            'katselu' => false,
            'luonti' => false,
            'muokkaus' => false,
            'poisto' => false,
          ]);
          DB::table('jarjestelma_roolit')
          ->where('osio', 'muistot')
          ->where('entiteetti', 'yksityinen_muisto')
          ->where('rooli', 'katselija')
          ->update([
            'katselu' => false,
            'luonti' => false,
            'muokkaus' => false,
            'poisto' => false,
          ]);
          DB::table('jarjestelma_roolit')
          ->where('osio', 'muistot')
          ->where('entiteetti', 'yksityinen_muisto')
          ->where('rooli', 'inventoija')
          ->update([
            'katselu' => false,
            'luonti' => false,
            'muokkaus' => false,
            'poisto' => false,
          ]);
        } catch (Exception $e) {
          DB::rollback();
          print "----------- Migration 'fix_muistot_roolit_values' failed.\n";
          throw $e;
      }

      DB::commit();

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
