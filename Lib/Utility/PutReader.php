<?php

namespace Lib\Utility;

class PutReader {
	private static $data = null;

	public static function getPutData() {
		if (is_null(self::$data)) {
			self::$data = file_get_contents('php://input');
		}

		return self::$data;
	}
}