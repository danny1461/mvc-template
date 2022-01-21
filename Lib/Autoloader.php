<?php

namespace Lib;

class Autoloader
{
	public static function init() {
		spl_autoload_register(array('Lib\\Autoloader', 'autoload'));
	}

	public static function autoload($class) {
		$filePath = APP_ROOT . '/' . $class . '.php';
		if (is_file($filePath)) {
			include $filePath;
		}
	}
}

Autoloader::init();