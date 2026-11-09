<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Kuvien indeksit kuvagalleriaa ja hakulistojen kuva-suodatinta varten.
 * Rak: välitaulujen (entiteetin id, jarjestys).
 * Ark: ark_kuva.ark_tutkimus_id ja välitaulujen ark_kuva_id.
 */
class KuvaIndexes extends Migration
{
    private $indeksit = [
        'kuva_kiinteisto_kiinteisto_id_jarjestys_index' => 'kuva_kiinteisto (kiinteisto_id, jarjestys)',
        'kuva_rakennus_rakennus_id_jarjestys_index' => 'kuva_rakennus (rakennus_id, jarjestys)',
        'kuva_porrashuone_porrashuone_id_jarjestys_index' => 'kuva_porrashuone (porrashuone_id, jarjestys)',
        'kuva_alue_alue_id_jarjestys_index' => 'kuva_alue (alue_id, jarjestys)',
        'kuva_arvoalue_arvoalue_id_jarjestys_index' => 'kuva_arvoalue (arvoalue_id, jarjestys)',
        'kuva_kyla_kyla_id_jarjestys_index' => 'kuva_kyla (kyla_id, jarjestys)',
        'kuva_suunnittelija_suunnittelija_id_jarjestys_index' => 'kuva_suunnittelija (suunnittelija_id, jarjestys)',
        'ark_kuva_ark_tutkimus_id_index' => 'ark_kuva (ark_tutkimus_id)',
        'ark_kuva_kohde_ark_kuva_id_index' => 'ark_kuva_kohde (ark_kuva_id)',
        'ark_kuva_loyto_ark_kuva_id_index' => 'ark_kuva_loyto (ark_kuva_id)',
        'ark_kuva_nayte_ark_kuva_id_index' => 'ark_kuva_nayte (ark_kuva_id)',
        'ark_kuva_yksikko_ark_kuva_id_index' => 'ark_kuva_yksikko (ark_kuva_id)',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach($this->indeksit as $nimi => $kohde) {
            DB::statement("create index if not exists $nimi on $kohde");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach($this->indeksit as $nimi => $kohde) {
            DB::statement("drop index if exists $nimi");
        }
    }
}
