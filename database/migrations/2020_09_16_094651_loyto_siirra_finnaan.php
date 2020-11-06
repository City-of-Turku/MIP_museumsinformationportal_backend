<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Utils;
use Illuminate\Support\Facades\DB;

class LoytoSiirraFinnaan extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        try {
            // wrap the operations into a transaction
            DB::beginTransaction();
            Utils::setDBUserAsSystem();

            Schema::table('ark_loyto', function($table) {
                $table->boolean("siirtyy_finnaan")->nullable()->default(true);
            });

            DB::table('ark_loyto')->update(['siirtyy_finnaan' => true]);

            Schema::table('ark_loyto', function($table) {
                $table->boolean("siirtyy_finnaan")->nullable(false)->change();
            });

        } catch (Exception $e) {
            DB::rollback();
            print "----------- Migration 'loyto_siirra_finnaan' failed.\n";
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
