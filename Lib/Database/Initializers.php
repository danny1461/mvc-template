<?php

namespace Lib\Database;

class Initializers {
	private static $time;
	public static function init() {
		self::$time = time();
	}
	public static function getInitTime() {
		return self::$time;
	}

	public static function Date() {
		return date(IDatabaseAdapter::DATE_FORMAT, self::$time);
	}

	public static function DateTime() {
		return date(IDatabaseAdapter::DATETIME_FORMAT, self::$time);
	}
}