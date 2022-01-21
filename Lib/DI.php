<?php

namespace Lib;

use Exception;
use Lib\Utility\DependencyInjector;

abstract class DI {
	private static $defaultInstance = null;

	/**
	 * Returns a static instance
	 *
	 * @return DependencyInjector
	 */
	public static function getDefault() {
		if (is_null(self::$defaultInstance)) {
			self::$defaultInstance = new DependencyInjector();
		}

		return self::$defaultInstance;
	}

	/**
	 * Registers a factory that must be called every time to generate the resource
	 *
	 * @param string $class
	 * @param mixed $factory
	 * @return void
	 */
	public static function addTransient($class, $factory) {
		self::getDefault()->addTransient($class, $factory);
	}

	/**
	 * Registers a factory that will be called at most once to generate the resource
	 *
	 * @param string $class
	 * @param mixed $factory
	 * @return void
	 */
	public static function addScoped($class, $factory) {
		self::getDefault()->addScoped($class, $factory);
	}

	/**
	 * Registers a resource that gets its value from another
	 * 
	 * @param string $class 
	 * @param string $targetClass 
	 * @return void 
	 */
	public static function addAlias($class, $targetClass) {
		self::getDefault()->addAlias($class, $targetClass);
	}

	/**
	 * Given a resource name, fetch or generate the service
	 *
	 * @param string $name
	 */
	public static function get($name) {
		return self::getDefault()->get($name);
	}

	/**
	 * Given a resource name, return whether there is a definition for it
	 *
	 * @param string $name
	 */
	public static function has($name) {
		return self::getDefault()->has($name);
	}

	public static function clearAll() {
		self::getDefault()->clearAll();
	}
}