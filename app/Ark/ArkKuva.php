<?php

namespace App\Ark;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ArkKuva extends Model {

    use SoftDeletes;

    protected $table = "ark_kuva";

    protected $hidden = [
        "kuva_id",
        "loyto_id",
        "yksikko_id",
        "nayte_id",
        "alkup_tiedostonimi",
        "polku",
        "pivot"
    ];

    protected $fillable = [
        "kuvaaja",
    	"organisaatio",
        "kuvauspvm",
    	"kuvaus",
        "tekijanoikeuslauseke",
        "julkinen",
    	"polku",
    	"tiedostonimi",
    	"alkup_tiedostonimi",
        "ark_tutkimus_id",
        "kuvaussuunta",
        "otsikko",
        "konservointivaihe_id",
        "lisatiedot",
        "tunnistekuva"
    ];

    public $timestamps = false;


    const CREATED_AT 		= 'luotu';
    const UPDATED_AT 		= 'muokattu';
    const DELETED_AT 		= "poistettu";

    const CREATED_BY		= 'luoja';
    const UPDATED_BY		= 'muokkaaja';
    const DELETED_BY		= 'poistaja';


    public static function getAll() {
        //Distinct poistaa duplikaatit kuvat, joita voi tulla jos yksi kuva linkataan moneen yksikköön ja löytöön tutkimuksen sisällä
        return ArkKuva::select("ark_kuva.*")->leftjoin("ark_kuva_kohde", "ark_kuva_kohde.ark_kuva_id", "=", "ark_kuva.id")
        ->leftjoin("ark_kohde", "ark_kohde.id", "=", "ark_kuva_kohde.ark_kohde_id")->whereNull('ark_kuva.poistettu')->distinct();
    }


    public static function getImageUrls($image_filename_path) {
        $images =  (object)array();

        // set the paths to check if thumbnails exists
        $base_url = config('app.image_server')."/".config('app.image_server_baseurl').dirname($image_filename_path)."/";
        $base_path = storage_path(config('app.image_upload_path'));
        $file_dirname	= pathinfo($image_filename_path, PATHINFO_DIRNAME)."/";
        //Strip ark_photo from file_dirname wtf
        //$file_dirname = str_replace('ark_photo/', '', $file_dirname);
        $file_basename 	= pathinfo($image_filename_path, PATHINFO_FILENAME);
        $file_extension = pathinfo($image_filename_path, PATHINFO_EXTENSION);
        //Strip ark_photo from file_dirname wtf
        //$base_url = str_replace('/photo/', '/', $base_url);
        $thumb_base 	= $base_url.$file_basename;
        $thumb_extension = 'jpg';

        /*
         * Check if image files exists on server disk
         *
         */
        $url_og = "";$url_tiny = "";$url_small = "";$url_medium = "";$url_large = "";
        if(File::exists($base_path.$file_dirname.basename($image_filename_path))) {

            $url_og = $base_url.basename($image_filename_path);
        }

        if(File::exists($base_path.$file_dirname.$file_basename.config('app.old_image_thumb_tiny_name').".jpg")) {
            $url_tiny = $base_url.$file_basename.config('app.old_image_thumb_tiny_name').".jpg";
        } else if(File::exists($base_path.$file_dirname.$file_basename."_TINY.".$thumb_extension)) {
            $url_tiny = $thumb_base."_TINY.".$thumb_extension;
        }

        if(File::exists($base_path.$file_dirname.$file_basename.config('app.old_image_thumb_small_name').".jpg")) {
            $url_small = $base_url.$file_basename.config('app.old_image_thumb_small_name').".jpg";
        } else if(File::exists($base_path.$file_dirname.$file_basename."_SMALL.".$thumb_extension)) {
            $url_small = $thumb_base."_SMALL.".$thumb_extension;
        }

        if(File::exists($base_path.$file_dirname.$file_basename.config('app.old_image_thumb_medium_name').".jpg")) {
            $url_medium = $base_url.$file_basename.config('app.old_image_thumb_medium_name').".jpg";
        } else if(File::exists($base_path.$file_dirname.$file_basename."_MEDIUM.".$thumb_extension)) {
            $url_medium = $thumb_base."_MEDIUM.".$thumb_extension;
        }

        if(File::exists($base_path.$file_dirname.$file_basename.config('app.old_image_thumb_large_name').".jpg")) {
            $url_large = $base_url.$file_basename.config('app.old_image_thumb_large_name').".jpg";
        } else if(File::exists($base_path.$file_dirname.$file_basename."_LARGE.".$thumb_extension)) {
            $url_large = $thumb_base."_LARGE.".$thumb_extension;
        }

        $images->original = $url_og;
        $images->tiny = $url_tiny;
        $images->small = $url_small;
        $images->medium = $url_medium;
        $images->large = $url_large;


        return $images;
    }

    public static function createThumbnail($img, $size) {
        $width = $img->width();
        $height = $img->height();
        $newWidth = 0;
        $newHeight = 0;
        if($width >= $height) {
            $newWidth = $size;
            $newHeight = $height*$size/$width;
        } else {
            $newHeight = $size;
            $newWidth = $width*$size/$height;
        }

        $img->resize($newWidth, $newHeight, function($constraint) {
            $constraint->upsize();
        });

            return $img;
    }

	public function luoja() {
	    return $this->belongsTo('App\Kayttaja', 'luoja');
	}

	public function muokkaaja() {
	    return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}

	public function scopeWithLimit($query, $start_row, $row_count) {
	    return $query->skip($start_row)->take($row_count);
	}

	public function scopeWithLoytoId($query, $id) {
	    return $query->join("ark_kuva_loyto", "ark_kuva_loyto.ark_kuva_id", "=", "ark_kuva.id")
	    ->where("ark_kuva_loyto.ark_loyto_id", "=", $id);
	}

	public function scopeWithKohdeId($query, $id) {
	    return $query->where("ark_kuva_kohde.ark_kohde_id", "=", $id);
	}

	public function scopeWithYksikkoId($query, $id) {
	    return $query->join("ark_kuva_yksikko", "ark_kuva_yksikko.ark_kuva_id", "=", "ark_kuva.id")
	    ->where("ark_kuva_yksikko.ark_yksikko_id", "=", $id);
	}

	public function scopeWithYksikkoTunnus($query, $keyword) {
	    return $query->join("ark_kuva_yksikko", "ark_kuva_yksikko.ark_kuva_id", "=", "ark_kuva.id")
	    ->join('ark_tutkimusalue_yksikko', 'ark_kuva_yksikko.ark_yksikko_id', '=', 'ark_tutkimusalue_yksikko.id')
	    ->where("ark_tutkimusalue_yksikko.yksikkotunnus", "ilike", $keyword."%");
	}

	public function scopeWithNayteId($query, $id) {
	    return $query->join("ark_kuva_nayte", "ark_kuva_nayte.ark_kuva_id", "=", "ark_kuva.id")
	    ->where("ark_kuva_nayte.ark_nayte_id", "=", $id);
	}

	public function scopeWithKohdeMjTunnus($query, $keyword) {
	    return $query->where("ark_kohde.muinaisjaannostunnus", "=", $keyword);
	}

	// $ids is a comma delimited string of id values
	public function scopeWithKohdeLajit($query, $ids) {
	    return $query->whereIn('ark_kohde.ark_kohdelaji_id', explode(',', $ids));
	}

	//Kaikilla paitsi kohteella pitää löytyä ark_tutkimus_id
	public function scopeWithTutkimusId($query, $id, $tutkimusView = false) {
		if($tutkimusView == true) {
			return $query->where("ark_tutkimus_id", "=", $id)->whereRaw(
				'(ark_kuva.id not in (
						select ak.id
						from ark_kuva ak
						where ak.konservointivaihe_id is not null and ak.tunnistekuva is false
				union
						select akk2.ark_kuva_id
						from ark_kuva_kuntoraportti akk2
				union
						select akr.ark_kuva_id
						from ark_kuva_rontgenkuva akr
				))'
			);
		} else {
			return $query->where("ark_tutkimus_id", "=", $id);
		}
	}

	// Tarkastustutkimuksen tutkimusalueelle voidaan luetteloida kuvia
	public function scopeWithTutkimusalueId($query, $id) {
	    $query
	    ->leftJoin("ark_kuva_tutkimusalue", "ark_kuva_tutkimusalue.ark_kuva_id", "=", "ark_kuva.id")
	    ->where("ark_kuva_tutkimusalue.ark_tutkimusalue_id", "=", $id);

	    return $query;
	}

	public function scopeWithTutkimusLyhenne($query, $keyword) {
	    return $query
	    ->leftJoin("ark_kuva_loyto", "ark_kuva_loyto.ark_kuva_id", "=", "ark_kuva.id")
	    ->leftJoin("ark_kuva_nayte", "ark_kuva_nayte.ark_kuva_id", "=", "ark_kuva.id")
	    ->leftJoin("ark_kuva_yksikko", "ark_kuva_yksikko.ark_kuva_id", "=", "ark_kuva.id")
	    ->leftJoin("ark_loyto", "ark_loyto.id", "=", "ark_kuva_loyto.ark_loyto_id")
	    ->leftJoin("ark_nayte", "ark_nayte.id", "=", "ark_kuva_nayte.ark_nayte_id")
	    ->leftJoin("ark_tutkimusalue_yksikko", function($join) {
	        $join->on("ark_tutkimusalue_yksikko.id", "=", "ark_kuva_yksikko.ark_yksikko_id")
	        ->orOn("ark_tutkimusalue_yksikko.id", "=", "ark_loyto.ark_tutkimusalue_yksikko_id")
	        ->orOn("ark_tutkimusalue_yksikko.id", "=", "ark_nayte.ark_tutkimusalue_yksikko_id");
	    })
	    ->leftJoin("ark_tutkimusalue", function($join) {
	        $join->on("ark_tutkimusalue.id", "=", "ark_loyto.ark_tutkimusalue_id")
	        ->orOn("ark_tutkimusalue.id", "=", "ark_nayte.ark_tutkimusalue_id")
	        ->orOn("ark_tutkimusalue.id", "=", "ark_tutkimusalue_yksikko.ark_tutkimusalue_id");
	    })
	    ->leftJoin("ark_tutkimus", "ark_tutkimus.id", "=", "ark_tutkimusalue.ark_tutkimus_id")
	    ->where("ark_tutkimus.tutkimuksen_lyhenne", "ILIKE", $keyword."%");
	}

	public function scopeWithAsiasanat($query, $keywords) {
	    //$keywords = explode(" ", $keywords);

	    return $query->leftJoin("ark_kuva_asiasana", "ark_kuva_asiasana.ark_kuva_id", "=", "ark_kuva.id")
	    ->where("asiasana", 'ILIKE',  $keywords."%");
	}

	public function scopeWithLuettelointinumero($query, $keyword) {
	    return $query->where('ark_kuva.luettelointinumero', 'ilike', $keyword."%");
	}

	public function scopeWithOtsikko($query, $keyword) {
	    return $query->where('ark_kuva.otsikko', 'ilike', "%".$keyword."%");
	}

	public function scopeWithRontgenKuva($query, $id) {
	    return $query->join("ark_kuva_rontgenkuva", "ark_kuva_rontgenkuva.ark_kuva_id", "=", "ark_kuva.id")
	    ->where("ark_kuva_rontgenkuva.ark_rontgenkuva_id", "=", $id);
	}

	public function scopeWithKuntoraportti($query, $id) {
		return $query->join("ark_kuva_kuntoraportti", "ark_kuva_kuntoraportti.ark_kuva_id", "=", "ark_kuva.id")
		->where("ark_kuva_kuntoraportti.ark_kuntoraportti_id", "=", $id);
}

	public static function getSingle($id) {
	    return ArkKuva::select('ark_kuva.*')->where('ark_kuva.id', '=', $id);
	}

	public function asiasanat() {
	    return $this->hasMany('App\Ark\ArkKuvaAsiasana');
	}

	public function kuvaloydot() {
	    return $this->hasMany('App\Ark\ArkKuvaLoyto');
	}
	public function kuvayksikot() {
	    return $this->hasMany('App\Ark\ArkKuvaYksikko');
	}
	public function kuvanaytteet() {
	    return $this->hasMany('App\Ark\ArkKuvaNayte');
	}

	public function kuvakohteet() {
	    return $this->hasMany('App\Ark\ArkKuvaKohde');
	}

	public function konservointivaihe() {
	    return $this->belongsTo('App\Ark\Konservointivaihe', 'konservointivaihe_id');
	}

	public function tutkimukset() {
	    return $this->belongsTo('App\Ark\Tutkimus', 'ark_tutkimus_id');
	}

	public function kuvatyyppi() {
	    return $this->hasOne('App\Ark\ArkKuvaLoyto');
	}

	//Poistetaan vanhat linkatut löydöt ja lisätään uudet jotka tulee $loydot-listassa
	public static function linkita_loydot($kuva_id, $loydot, $kuvaTyyppi = null) {
	    if(!is_null($loydot)) {
			if ($kuvaTyyppi == null){
				$kuvaTyyppi = DB::table('ark_kuva_loyto')->where('ark_kuva_id', $kuva_id)->pluck('kuva_tyyppi')->first();
			}
	        DB::table('ark_kuva_loyto')->where('ark_kuva_id', $kuva_id)->delete();

	        foreach($loydot as $loyto) {
	            $maxJarjestys = DB::table('ark_kuva_loyto')->where('ark_loyto_id', '=', $loyto["id"])->max('jarjestys')+1;

	            $kl = new ArkKuvaLoyto();
	            $kl->ark_kuva_id = $kuva_id;
	            $kl->ark_loyto_id = $loyto["id"];
	            $kl->jarjestys = $maxJarjestys;
				$kl->kuva_tyyppi = $kuvaTyyppi;
	            $kl->luoja = Auth::user()->id;

	            $kl->save();
	        }
	    }
	}

	//Poistetaan vanhat linkatut naytteet ja lisätään uudet jotka tulee $loydot-listassa
	public static function linkita_naytteet($kuva_id, $naytteet) {
	    if(!is_null($naytteet)) {
	        DB::table('ark_kuva_nayte')->where('ark_kuva_id', $kuva_id)->delete();

	        foreach($naytteet as $nayte) {
	            $maxJarjestys = DB::table('ark_kuva_nayte')->where('ark_nayte_id', '=', $nayte["id"])->max('jarjestys')+1;

	            $kn = new ArkKuvaNayte();
	            $kn->ark_kuva_id = $kuva_id;
	            $kn->ark_nayte_id = $nayte["id"];
	            $kn->jarjestys = $maxJarjestys;
	            $kn->luoja = Auth::user()->id;

	            $kn->save();
	        }
	    }
	}

	//Poistetaan vanhat linkatut löydöt ja lisätään uudet jotka tulee $yksikot-listassa
	public static function linkita_yksikot($kuva_id, $yksikot) {
	    if(!is_null($yksikot)) {
	        DB::table('ark_kuva_yksikko')->where('ark_kuva_id', $kuva_id)->delete();

	        foreach($yksikot as $yksikko) {
	            $maxJarjestys = DB::table('ark_kuva_yksikko')->where('ark_yksikko_id', '=', $yksikko["id"])->max('jarjestys')+1;

	            $ky = new ArkKuvaYksikko();
	            $ky->ark_kuva_id = $kuva_id;
	            $ky->ark_yksikko_id = $yksikko["id"];
	            $ky->jarjestys = $maxJarjestys;
	            $ky->luoja = Auth::user()->id;

	            $ky->save();
	        }
	    }
	}
	//Poistetaan vanhat linkatut kohteet ja lisätään uudet jotka tulee $kohteet-listassa
	public static function linkita_kohteet($kuva_id, $kohteet) {
		if(!is_null($kohteet)) {
				DB::table('ark_kuva_kohde')->where('ark_kuva_id', $kuva_id)->delete();

				foreach($kohteet as $kohde) {
						$maxJarjestys = DB::table('ark_kuva_kohde')->where('ark_kohde_id', '=', $kohde["id"])->max('jarjestys')+1;

						$ky = new ArkKuvaKohde();
						$ky->ark_kuva_id = $kuva_id;
						$ky->ark_kohde_id = $kohde["id"];
						$ky->jarjestys = $maxJarjestys;
						$ky->luoja = Auth::user()->id;

						$ky->save();
				}
		}
}

	public static function isLuettelointinumeroUnique($ln, $nykyisenKuvanId) {
	    $q = DB::select(DB::raw('select count(k.id)
                                from ark_kuva k
                                where k.luettelointinumero ilike :ln
                                and k.poistettu is null
                                and k.id != :nykyId;'), array('ln' => $ln, 'nykyId' => $nykyisenKuvanId));

	    if(sizeof($q) > 0) {
	        if($q[0]->count ==  0) {
	            return true;
	        }
	        return false;
	    }
	    return false;
	}

	public static function updateLoytoTunnistekuva($loytoId, $kuvaId) {
	    DB::select(DB::raw('update ark_kuva set tunnistekuva = false
				where ark_kuva.id in(
					select ark_kuva.id
					from ark_kuva
					left join ark_kuva_loyto on ark_kuva.id = ark_kuva_loyto.ark_kuva_id
					left join ark_loyto on ark_loyto.id = ark_kuva_loyto.ark_loyto_id
					where ark_loyto.id = :lId
					and ark_kuva.luettelointinumero is null
					and ark_kuva.id != :kId
				);'), array('lId' => $loytoId, 'kId' => $kuvaId));
	}

	public static function updateYksikkoTunnistekuva($yksikkoId, $kuvaId) {
		DB::select(DB::raw('update ark_kuva set tunnistekuva = false
			where ark_kuva.id in(
				select ark_kuva.id
				from ark_kuva
				left join ark_kuva_yksikko on ark_kuva.id = ark_kuva_yksikko.ark_kuva_id
				left join ark_tutkimusalue_yksikko on ark_tutkimusalue_yksikko.id = ark_kuva_yksikko.ark_yksikko_id
				where ark_tutkimusalue_yksikko.id = :yId
				and ark_kuva.id != :kId
			);'), array('yId' => $yksikkoId, 'kId' => $kuvaId));
}

	public static function tutkimus($kuvaId) {
	    return Tutkimus::on()->fromQuery(DB::raw('select t.*
				from ark_kuva k
				left join ark_kuva_loyto kl on kl.ark_kuva_id = k.id
				left join ark_kuva_nayte kn on kn.ark_kuva_id = k.id
				left join ark_kuva_yksikko ky on ky.ark_kuva_id = k.id
				left join ark_loyto l on l.id = kl.ark_loyto_id
				left join ark_nayte n on n.id = kn.ark_nayte_id
				left join ark_tutkimusalue_yksikko ty on ty.id = l.ark_tutkimusalue_yksikko_id
				left join ark_tutkimusalue ta on ta.id = ty.ark_tutkimusalue_id
				left join ark_tutkimus t on (t.id = ta.ark_tutkimus_id or t.id = k.ark_tutkimus_id)
				where k.id = :kuvaId
				and k.poistettu is null;'), array('kuvaId' => $kuvaId));
	}

	public static function loytoTunnistekuva($loytoId) {
	    return ArkKuva::select('ark_kuva.*')
	    ->leftJoin('ark_kuva_loyto', 'ark_kuva_loyto.ark_kuva_id', '=', 'ark_kuva.id')
	    ->leftJoin('ark_loyto', 'ark_loyto.id', '=', 'ark_kuva_loyto.ark_loyto_id')
	    ->where('ark_loyto.id', '=', $loytoId)
	    ->where('ark_kuva.tunnistekuva', '=', true)
	    ->where('ark_kuva.poistettu', '=', null);
	}
}
