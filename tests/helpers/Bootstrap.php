<?php

use Lib\Autoloader;

define('APP_ROOT', realpath(__DIR__ . '/../../'));
define('IS_LOCAL', in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']));

require APP_ROOT . '/lib/Autoloader.php';

Autoloader::init([
	APP_ROOT . DIRECTORY_SEPARATOR . 'lib',
	APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers',
	APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'models',
	APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'lib'
]);

require_once APP_ROOT . '/tests/helpers/Config.php';