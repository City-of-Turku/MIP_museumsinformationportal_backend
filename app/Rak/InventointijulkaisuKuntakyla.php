<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InventointijulkaisuKuntakyla extends Model {

    protected $table = "inventointijulkaisu_kuntakyla";
    protected $fillable = array('inventointijulkaisu_id', 'kunta_id', 'kyla_id');
    public $timestamps = false;


    public static function paivita_inventointijulkaisu_kunnatkylat($inventointijulkaisu_id, $kunnatkylat) {
        if (!is_null($kunnatkylat)) {
            // remove all selections and add new ones
            DB::table('inventointijulkaisu_kuntakyla')->where('inventointijulkaisu_id', $inventointijulkaisu_id)->delete();

            foreach($kunnatkylat as $kk) {
                $inventointijulkaisuKuntaKyla = new InventointijulkaisuKuntakyla();
                $inventointijulkaisuKuntaKyla->inventointijulkaisu_id = $inventointijulkaisu_id;
                $inventointijulkaisuKuntaKyla->kunta_id = $kk['kunta']['id'];
                array_key_exists('kyla', $kk) ? $inventointijulkaisuKuntaKyla->kyla_id = $kk['kyla']['id'] : null;
                $inventointijulkaisuKuntaKyla->luoja = Auth::user()->id;
                $inventointijulkaisuKuntaKyla->save();
            }
        }
    }


    public function kunta() {
        return $this->hasOne('App\Kunta', 'id', 'kunta_id');
    }

    public function kyla() {
        return $this->hasOne('App\Kyla', 'id', 'kyla_id');
    }
}
