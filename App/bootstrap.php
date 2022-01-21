<?php

namespace App;

use Lib\DI;
use Lib\EnvLoader;
use Lib\Database\Adapter;

if (ob_get_level()) {
	ob_end_clean();
}
ob_start();

session_start();
EnvLoader::load();

DI::addScoped(Adapter::class, function() {
	return new Adapter($_ENV['dbhost'], $_ENV['dbuser'], $_ENV['dbpass'], $_ENV['dbname']);
});