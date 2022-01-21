<?php

namespace App\Models;

use Lib\Database\Model;

/**
 * @Table('options')
 */
class Options extends Model {
	private static $cache = null;

	/**
	 * @DataType('string')
	 * @Key
	 */
	public $name;

	/**
	 * @Serialized
	 * @var mixed
	 */
	public $value;

	private static function loadOptions() {
		if (is_null(self::$cache)) {
			self::$cache = [];

			foreach (self::find() as $row) {
				self::$cache[$row->name] = $row;
			}
		}
	}

	public static function getOption($name) {
		self::loadOptions();

		if (isset(self::$cache[$name])) {
			return self::$cache[$name]->value;
		}

		return null;
	}

	public static function setOption($name, $value) {
		self::loadOptions();

		if (isset(self::$cache[$name])) {
			$record = self::$cache[$name];
		}
		else {
			$record = new self();
			$record->name = $name;

			self::$cache[$name] = $record;
		}

		$record->value = $value;
		$record->save();
	}
}