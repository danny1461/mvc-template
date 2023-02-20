<?php

namespace Lib\Database;

use Lib\DI;

abstract class CachedModel extends Model {
	protected static $_cache = [];

	public static function getByKey(...$keys)
	{
		$cache = & static::$_cache;
		foreach ($keys as $key) {
			if (!isset($cache[$key])) {
				$cache[$key] = [];
			}

			$cache = & $cache[$key];
		}

		if (count($cache)) {
			return static::fromArray($cache, true);
		}

		$model = parent::getByKey(...$keys);

		return $model;
	}

	public function save() {
		if (parent::save()) {
			$db = DI::get(IDatabaseAdapter::class);
			$db->trackModel(TrackTypeEnum::COMMITTED(), function() { static::_cacheModel($this); });

			return true;
		}

		return false;
	}

	public function delete() {
		if (parent::delete()) {
			$db = DI::get(IDatabaseAdapter::class);
			$db->trackModel(TrackTypeEnum::COMMITTED(), function() { static::_cacheModel($this); });

			return true;
		}

		return false;
	}

	public static function query($query, ...$args) {
		$models = parent::query($query, ...$args);
		if ($models) {
			foreach ($models as $model) {
				static::_cacheModel($model);
			}
		}

		return $models;
	}

	protected static function _cacheModel(CachedModel $model) {
		$cache = & static::$_cache;
		foreach ($model->getKeys() as $key) {
			if (!isset($cache[$key])) {
				$cache[$key] = [];
			}

			$cache = & $cache[$key];
		}

		if ($model->doesExist()) {
			$cache = $model->getFieldArray();
		}
		else {
			$cache = [];
		}
	}
}