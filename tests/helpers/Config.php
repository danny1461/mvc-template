<?php

function getConfig($key = null) {
	static $config = null;
	if (is_null($config)) {
		$config = parse_ini_file(__DIR__ . '/../../dev.env', true, INI_SCANNER_TYPED);
	}

	if ($key) {
		return $config[$key];
	}

	return $config;
}