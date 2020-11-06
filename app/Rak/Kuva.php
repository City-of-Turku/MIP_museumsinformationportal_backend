<?php

namespace App\Rak;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;

class Kuva extends Model {

	use SoftDeletes;

	protected $table = "kuva";
	protected $hidden = [
		"kuva_id",
		"kiinteisto_id",
		"rakennus_id",
		"nimi",
		"polku",
		"pivot"
	];
	protected $fillable = [
		"otsikko",
		"kuvaus",
		"kuvaaja",
		"julkinen",
		"pvm_kuvaus",
	    "tekijanoikeuslauseke"
	];
	public $timestamps = true;

	const CREATED_AT 		= 'luotu';
	const UPDATED_AT 		= 'muokattu';
	const DELETED_AT 		= "poistettu";

	const CREATED_BY		= 'luoja';
	const UPDATED_BY		= 'muokkaaja';
	const DELETED_BY		= 'poistaja';

	public function luoja() {
		return $this->belongsTo('App\Kayttaja', 'luoja');
	}

	public function muokkaaja() {
		return $this->belongsTo('App\Kayttaja', 'muokkaaja');
	}

	public function scopeWithLimit($query, $start_row, $row_count) {
		return $query->skip($start_row)->take($row_count);
	}

	public function scopeWithEstateID($query, $estateID) {
		return $query->join("kuva_kiinteisto", "kuva_kiinteisto.kuva_id", "=", "kuva.id")
			->where("kuva_kiinteisto.kiinteisto_id", "=", $estateID);
	}

	public function scopeWithBuildingID($query, $buildingID) {
		return $query->join("kuva_rakennus", "kuva_rakennus.kuva_id", "=", "kuva.id")
			->where("kuva_rakennus.rakennus_id", "=", $buildingID);
	}

	public function scopeWithAreaID($query, $buildingID) {
		return $query->join("kuva_alue", "kuva_alue.kuva_id", "=", "kuva.id")
			->where("kuva_alue.alue_id", "=", $buildingID);
	}

	public function scopeWithValueareaID($query, $valueareaID) {
		return $query->join("kuva_arvoalue", "kuva_arvoalue.kuva_id", "=", "kuva.id")
		->where("kuva_arvoalue.arvoalue_id", "=", $valueareaID);
	}

	public function scopeWithStaircaseID($query, $staircaseID) {
		return $query->join("kuva_porrashuone", "kuva_porrashuone.kuva_id", "=", "kuva.id")
		->where("kuva_porrashuone.porrashuone_id", "=", $staircaseID);
	}

	public function scopeWithVillageID($query, $villageID) {
		return $query->join("kuva_kyla", "kuva_kyla.kuva_id", "=", "kuva.id")
		->where("kuva_kyla.kyla_id", "=", $villageID);
	}

	public function scopeWithSuunnittelijaID($query, $suunnittelijaID) {
	    return $query->join("kuva_suunnittelija", "kuva_suunnittelija.kuva_id", "=", "kuva.id")
	    ->where("kuva_suunnittelija.suunnittelija_id", "=", $suunnittelijaID);
	}

	/**
	 * Method to get single entity from db with given ID
	 *
	 * @param int $id
	 * @author
	 * @version 1.0
	 * @since 1.0
	 */
	public static function getSingle($id) {
		return Kuva::select('kuva.*')->where('kuva.id', '=', $id);
	}

	/**
	 * Method to return the urls of the given image file and its thumbnails
	 *
	 * @param String $image_filename_path image filename AND path to ORIGINAL uploaded file (eg. "2016/06/08/filename.jpg")
	 * @return Object
	 * @author
	 * @version 1.0
	 * @since
	 */
	public static function getImageUrls($image_filename_path) {
		$images =  (object)array();

		// set the paths to check if thumbnails exists
		$base_url = config('app.image_server')."/".config('app.image_server_baseurl').dirname($image_filename_path)."/";
		$base_path = storage_path(config('app.image_upload_path'));
		$file_dirname	= pathinfo($image_filename_path, PATHINFO_DIRNAME)."/";
		$file_basename 	= pathinfo($image_filename_path, PATHINFO_FILENAME);
		$file_extension = pathinfo($image_filename_path, PATHINFO_EXTENSION);
		$thumb_base 	= $base_url.$file_basename;
		$thumb_extension = 'jpg';
		/*
		 * Check if image files exists on server disk
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

}
