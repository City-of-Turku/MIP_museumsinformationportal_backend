<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Utils;

class VanhatTutkimuksetUpdate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Päivitetään vanhat tutkimukset valmiiksi ja julkisiksi
        print "----------- Migration 'vanhat_tutkimukset_update' start.\n";

        //Write log file
        $selectSql  = 'select t.id, t.nimi, t.valmis, t.julkinen, t.ark_tutkimuslaji_id, t.luoja, t.luotu ';
        $selectSql .= 'from ark_tutkimus t ';
        $selectSql .= 'where t.luoja = -1 ';
        $selectSql .= 'and t.valmis = false ';
        $selectSql .= 'and t.julkinen = false ';
        $selectSql .= 'and t.poistettu is null ';
        $selectSql .= 'and t.ark_tutkimuslaji_id = 6';

        $rows = DB::select($selectSql);
        Utils::logToFile("vanhat_tutkimukset_update.txt", $rows);

        // wrap the operations into a transaction
        DB::beginTransaction();
        Utils::setDBUserAsSystem();

        try {
            $sql  = 'update ark_tutkimus ';
            $sql .= 'set valmis = true, julkinen = true ';
            $sql .= 'where id in ( ';
            $sql .= '  select t.id ';
            $sql .= '  from ark_tutkimus t ';
            $sql .= '  where t.luoja = -1 ';
            $sql .= '  and t.valmis = false ';
            $sql .= '  and t.julkinen = false ';
            $sql .= '  and t.poistettu is null ';
            $sql .= '  and t.ark_tutkimuslaji_id = 6 ';
            $sql .= ');';


            DB::statement($sql);
        } catch (Exception $e) {
            DB::rollback();
            print "----------- Migration 'vanhat_tutkimukset_update' failed.\n";
            throw $e;
        }

        DB::commit();
        print "----------- Migration 'vanhat_tutkimukset_update' finished.\n";
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
