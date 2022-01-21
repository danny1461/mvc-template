<?php

namespace Lib;

abstract class Environment {
	private static $environment = false;
	private static $appDir;
	private static $baseUrl;
	private static $requestUri;

	public static function setEnvironment(string $env) {
		self::$environment = $env;

		$filePath = __DIR__ . '/../' . $env . '.env';

		if (!file_exists($filePath)) {
			file_put_contents($filePath, '');
		}
		
		$data = parse_ini_file($filePath, true, INI_SCANNER_TYPED);
		$_ENV = [];
		if ($data) {
			foreach ($data as $key => $val) {
				$_ENV[$key] = $val;
			}
		}
	}

	public static function getEnvironment() {
		return self::$environment;
	}

	public static function detectUrls() {
		self::$appDir = substr(APP_ROOT, strlen($_SERVER['DOCUMENT_ROOT'])) . '/';
		self::$appDir = str_replace('\\', '/', self::$appDir);

		self::$baseUrl = 'http' . ($_SERVER['SERVER_PORT'] === '443' ? 's' : '') . '://';
		self::$baseUrl .= $_SERVER['SERVER_NAME'];
		if (!in_array($_SERVER['SERVER_PORT'], array('80', '443')))
			self::$baseUrl .= ':' . $_SERVER['SERVER_PORT'];
		self::$baseUrl .= self::$appDir;

		list(self::$requestUri) = explode('?', $_SERVER['REQUEST_URI'], 2);
		self::$requestUri = substr(self::$requestUri, strlen(self::$appDir));
		self::$requestUri = rtrim(self::$requestUri, '/');
		self::$requestUri = urldecode(self::$requestUri);
	}

	public static function getAppDir() {
		return self::$appDir;
	}

	public static function getBaseUrl() {
		return self::$baseUrl;
	}

	public static function getRequestUrl() {
		return self::$requestUri;
	}
}

Environment::detectUrls();