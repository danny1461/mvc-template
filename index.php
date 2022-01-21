<?php

use Lib\Autoloader;
use Lib\Application;

/**
 * Minimal MVC Framework
 *   By Daniel Flynn 9/16/2020
 */

define('APP_ROOT', __DIR__);
define('IS_LOCAL', in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']));

require __DIR__ . '/lib/Autoloader.php';

Autoloader::init();

/* Application Start */

(new Application())
	->hook(function($app) {
		require_once APP_ROOT . '/app/bootstrap.php';
	})
	->start();