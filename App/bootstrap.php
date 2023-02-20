<?php

namespace App;

use Lib\DI;
use Lib\EnvLoader;
use Lib\Database\IDatabaseAdapter;
use Lib\Database\MysqlAdapter;

if (ob_get_level()) {
	ob_end_clean();
}
ob_start();

session_start();
EnvLoader::load();

DI::addScoped(IDatabaseAdapter::class, function() {
	return new MysqlAdapter($_ENV['dbhost'], $_ENV['dbuser'], $_ENV['dbpass'], $_ENV['dbname']);
});