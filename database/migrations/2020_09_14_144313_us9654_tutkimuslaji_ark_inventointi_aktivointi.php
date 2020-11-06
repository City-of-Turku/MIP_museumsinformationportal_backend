<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Utils;

class US9654TutkimuslajiArkInventointiAktivointi extends Migration
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
            DB::statement("UPDATE ark_tutkimuslaji SET aktiivinen=true WHERE nimi_fi='Arkeologinen inventointi';");
        } catch (Exception $e) {
            DB::rollback();
            print "----------- Migration 'US9654TutkimuslajiArkInventointiAktivointi failed'.\n";
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
