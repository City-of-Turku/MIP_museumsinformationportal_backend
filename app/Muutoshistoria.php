<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class Muutoshistoria extends Model
{

	protected $casts = [
			'row_data' => 'array',
			'changed_fields' => 'array'
	];

	protected $table = "logged_actions";

	protected static $app_schema_name = "public";

	protected static function getHistoryById( $entiteetti_taulu, $id) {
		return self::getHistoryByKeyAndId($entiteetti_taulu, 'id', $id);
	}

	protected static function getHistoryByKeyAndId($entiteetti_taulu, $key, $id) {
		return self::getHistoryByKeyAndIdAndAction($entiteetti_taulu, $key, $id, null);
	}

	protected static function getHistoryByKeyAndIdAndAction( $entiteetti_taulu, $key, $id, $action) {

		$qry = self::select(
				"event_id", "table_name", "application_user_id", "kayttaja.etunimi", "kayttaja.sukunimi", "action_tstamp_tx", "transaction_id", "action",
				DB::raw("hstore_to_json(row_data) as row_data"), DB::raw("hstore_to_json(changed_fields) as changed_fields")
				);
		$qry->where('schema_name', '=', self::$app_schema_name);
		$qry->where('table_name', '=', $entiteetti_taulu);
		if (!is_null($action)) {
			$qry->where('action', '=', $action);
		}
		$qry->whereRaw("((row_data->'$key') = '$id')");
		$qry->leftjoin('kayttaja', 'kayttaja.id', '=', "logged_actions.application_user_id");

		$history = $qry->orderBy("action_tstamp_tx")->get();

		foreach ($history as $hi) {
			$hi["children"] = self::getHistoryByTransactionId($hi->transaction_id, $hi->event_id);
		}

		return $history;
	}

	protected static function getDeletionHistory($entiteetti_taulu, $parent_id_column, $parent_id, $deleted_by_column) {
		$qry = self::select(
				"event_id", "table_name", "application_user_id", "kayttaja.etunimi", "kayttaja.sukunimi", "action_tstamp_tx", "transaction_id", "action",
				DB::raw("hstore_to_json(row_data) as row_data"), DB::raw("hstore_to_json(changed_fields) as changed_fields")
				);
		$qry->where('schema_name', '=', self::$app_schema_name);
		$qry->where('table_name', '=', $entiteetti_taulu);
		$qry->where('action', '=', 'U');
		$qry->whereRaw("((changed_fields->'$deleted_by_column') is not null)");
		$qry->whereRaw("row_data->'$parent_id_column' = '$parent_id'");
		$qry->leftjoin('kayttaja', 'kayttaja.id', '=', "logged_actions.application_user_id");

		$history = $qry->orderBy("action_tstamp_tx")->get();
		// change it to delete action
		if ($history) {
			foreach($history as $h) {
				$h->action='D';
			}
		}
		return $history;
	}

	private static function getHistoryByTransactionId($transaction_id, $exclude_event_id) {

		$qry = self::select(
				"event_id", "table_name", "application_user_id", "kayttaja.etunimi", "kayttaja.sukunimi", "action_tstamp_tx", "transaction_id", "action",
				DB::raw("hstore_to_json(row_data) as row_data"), DB::raw("hstore_to_json(changed_fields) as changed_fields")
				);
		$qry->where('schema_name', '=', self::$app_schema_name);
		$qry->where('transaction_id', '=', $transaction_id);
		if (!is_null($exclude_event_id)) {
			$qry->where('event_id', '<>', $exclude_event_id);
		}
		$qry->leftjoin('kayttaja', 'kayttaja.id', '=', "logged_actions.application_user_id");

		return $qry->orderBy("action_tstamp_tx")->get();
	}

	protected static function getMovedRakennusEntries($key, $id, $direction) {

		$qry = self::select(
				"event_id", "table_name", "application_user_id", "kayttaja.etunimi", "kayttaja.sukunimi", "action_tstamp_tx", "transaction_id", "action",
				DB::raw("hstore_to_json(row_data) as row_data"), DB::raw("hstore_to_json(changed_fields) as changed_fields")
				);
		$qry->where('schema_name', '=', self::$app_schema_name);
		$qry->where('table_name', '=', 'rakennus');

		$qry->where('action', '=', 'U');

		if($direction == 'from') {
			$qry->whereRaw("((row_data->'$key') = '$id')");
		} else {
			$qry->whereRaw("((changed_fields->'$key') = '$id')");
		}
		$qry->leftjoin('kayttaja', 'kayttaja.id', '=', "logged_actions.application_user_id");

		$history = $qry->orderBy("action_tstamp_tx")->get();

		foreach ($history as $hi) {
			$hi["children"] = self::getHistoryByTransactionId($hi->transaction_id, $hi->event_id);
		}

		return $history;
	}

}
