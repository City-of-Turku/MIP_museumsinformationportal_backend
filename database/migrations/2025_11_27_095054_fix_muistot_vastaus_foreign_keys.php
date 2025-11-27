<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixMuistotVastausForeignKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      // When kysymys is deleted, also vastaus rows will get deleted
      Schema::table('muistot_vastaus', function (Blueprint $table) {
          // Drop the existing foreign key constraint
          $table->dropForeign(['muistot_kysymys_id']);

          // Recreate the foreign key with ON DELETE CASCADE
          $table->foreign('muistot_kysymys_id')
                ->references('prikka_id')
                ->on('muistot_kysymys')
                ->onDelete('cascade');
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
