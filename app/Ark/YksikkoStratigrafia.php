<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class YksikkoStratigrafia extends Model
{
    use SoftDeletes;

    protected $table = "ark_yksikko_stratigrafia";

    protected $fillable = array(
        'yksikko1', 'yksikko2', 'suhde'
    );

    public $timestamps = true;

    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';

    public static function getAll() {
        return self::select('ark_yksikko_stratigrafia.*');
    }

    public static function haeSuhteet($id) {
        return DB::table('ark_yksikko_stratigrafia AS ays')
            ->select('ays.yksikko1', 'ays.yksikko2', 'ays.suhde', 'aty.yksikkotunnus AS yt1', 'aty2.yksikkotunnus AS yt2') //ark_tutkimusalue_yksikko.yksikkotunnus
            ->leftJoin('ark_tutkimusalue_yksikko AS aty', 'ays.yksikko1', '=', 'aty.id')
            ->leftJoin('ark_tutkimusalue_yksikko AS aty2', 'ays.yksikko2', '=', 'aty2.id')
            ->whereNull('aty.poistettu')
            ->whereNull('aty2.poistettu')
            ->where(function($query) use ($id)
            {
                $query->where('ays.yksikko1', '=', $id)
                      ->orWhere('ays.yksikko2', '=', $id);
            });
    }

    public static function tallennaStratigrafia($id, $stratigrafia){
        DB::table('ark_yksikko_stratigrafia')->where('yksikko1', $id)->delete();
        DB::table('ark_yksikko_stratigrafia')->where('yksikko2', $id)->delete();

        foreach($stratigrafia as $yksikko) {

           $ys = new YksikkoStratigrafia();

           $ys->yksikko1 = $yksikko['yksikko1'];
           $ys->yksikko2 = $yksikko['yksikko2'];
           $ys->suhde = $yksikko['suhde'];

           $ys->luoja = Auth::user()->id;

           $ys->save();
        }

    }
}
