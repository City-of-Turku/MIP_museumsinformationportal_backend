<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MuistotAddForeignKeysCascade extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // When aihe is deleted, also muisto will get deleted
        Schema::table('muistot_muisto', function (Blueprint $table) {
          // Drop the existing foreign key constraint
          $table->dropForeign(['muistot_aihe_id']);

          // Recreate the foreign key with ON DELETE CASCADE
          $table->foreign('muistot_aihe_id')
                ->references('prikka_id')
                ->on('muistot_aihe')
                ->onDelete('cascade');
      });

        // When aihe is deleted, also kysymys will get deleted
        Schema::table('muistot_kysymys', function (Blueprint $table) {
          // Drop the existing foreign key constraint
          $table->dropForeign(['muistot_aihe_id']);

          // Recreate the foreign key with ON DELETE CASCADE
          $table->foreign('muistot_aihe_id')
                ->references('prikka_id')
                ->on('muistot_aihe')
                ->onDelete('cascade');
      });

        // When aihe is deleted, also it's kayttaja rows will get deleted
        Schema::table('muistot_aihe_kayttaja', function (Blueprint $table) {
          // Drop the existing foreign key constraint
          $table->dropForeign(['muistot_aihe_id']);
          $table->dropForeign(['kayttaja_id']);

          // Recreate the foreign key with ON DELETE CASCADE
          $table->foreign('muistot_aihe_id')
                ->references('prikka_id')
                ->on('muistot_aihe')
                ->onDelete('cascade');
          $table->foreign('kayttaja_id')
                ->references('id')
                ->on('kayttaja')
                ->onDelete('cascade');

      });

        // When muisto is deleted, also vastaus rows will get deleted
        Schema::table('muistot_vastaus', function (Blueprint $table) {
          // Drop the existing foreign key constraint
          $table->dropForeign(['muistot_muisto_id']);

          // Recreate the foreign key with ON DELETE CASCADE
          $table->foreign('muistot_muisto_id')
                ->references('prikka_id')
                ->on('muistot_muisto')
                ->onDelete('cascade');
      });

        // When muisto is deleted, also kuva rows will get deleted
        Schema::table('muistot_kuva', function (Blueprint $table) {
          // Drop the existing foreign key constraint
          $table->dropForeign(['muistot_muisto_id']);

          // Recreate the foreign key with ON DELETE CASCADE
          $table->foreign('muistot_muisto_id')
                ->references('prikka_id')
                ->on('muistot_muisto')
                ->onDelete('cascade');
      });

        // When muisto or kiinteisto is deleted, also muisto_kiinteisto rows will get deleted
        // Foreign keys are missing so adding them now
        Schema::table('muistot_muisto_kiinteisto', function (Blueprint $table) {

            // Create the foreign keys with ON DELETE CASCADE
            $table->foreign('muistot_muisto_id')
                ->references('prikka_id')
                ->on('muistot_muisto')  // replace with your parent table name
                ->onDelete('cascade');

            $table->foreign('kiinteisto_id')
                ->references('id')
                ->on('kiinteisto')  // replace with your parent table name
                ->onDelete('cascade');         
        });

      // Muistot_muisto_kunta is already fine with ON DELETE CASCADE


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
          // Muistot_muisto
          Schema::table('muistot_muisto', function (Blueprint $table) {
            // Drop the updated foreign key constraint with ON DELETE CASCADE
            $table->dropForeign(['muistot_aihe_id']);

            // Recreate the original foreign key without ON DELETE CASCADE
            $table->foreign('muistot_aihe_id')
                  ->references('prikka_id')
                  ->on('muistot_aihe');
        });

        // Muistot_kysymys
        Schema::table('muistot_kysymys', function (Blueprint $table) {
            // Drop the updated foreign key constraint with ON DELETE CASCADE
            $table->dropForeign(['muistot_aihe_id']);

            // Recreate the original foreign key without ON DELETE CASCADE
            $table->foreign('muistot_aihe_id')
                  ->references('prikka_id')
                  ->on('muistot_aihe');
        });

        // Muistot_aihe_kayttaja
        Schema::table('muistot_aihe_kayttaja', function (Blueprint $table) {

          $table->dropForeign(['muistot_aihe_id']);
          $table->dropForeign(['kayttaja_id']);

          $table->foreign('muistot_aihe_id')
                ->references('prikka_id')
                ->on('muistot_aihe');
          $table->foreign('kayttaja_id')
                ->references('id')
                ->on('kayttaja');
        });


        // Muistot_vastaus
        Schema::table('muistot_vastaus', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['muistot_muisto_id']); // replace 'parent_id' with your actual foreign key column

            // Recreate the foreign key with ON DELETE CASCADE
            $table->foreign('muistot_muisto_id')
                  ->references('prikka_id')
                  ->on('muistot_muisto');
        });

        // Muistot_kuva
        Schema::table('muistot_kuva', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['muistot_muisto_id']); // replace 'parent_id' with your actual foreign key column

            // Recreate the foreign key with ON DELETE CASCADE
            $table->foreign('muistot_muisto_id')
                  ->references('prikka_id')
                  ->on('muistot_muisto');  // replace with your parent table name
        });

        // Muistot_muisto_kiinteisto
        Schema::table('muistot_muisto_kiinteisto', function (Blueprint $table) {
            $table->dropForeign(['muistot_muisto_id']);
            $table->dropForeign(['kiinteisto_id']);
        });

    }
}
