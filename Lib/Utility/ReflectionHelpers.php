<?php

namespace Lib\Utility;

use Closure;
use stdClass;
use Exception;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

class ReflectionHelpers {
	private static $undefined = null;

	private static function getUndefined() {
		if (is_null(self::$undefined)) {
			self::$undefined = new stdClass();
		}

		return self::$undefined;
	}

	/**
	 * Constructs a specified class, filling in the constructor parameters using the injection service
	 *
	 * @param string $class
	 * @param array $args
	 * @param mixed|callable $argProvider
	 * @return object
	 */
	public static function constructClass($class, $args = [], $argProvider = null) {
		return self::callMethod($class, '__construct', $args, $argProvider);
	}

	/**
	 * Calls a class method, filling in the method parameters using the injection service
	 *
	 * @param string|object $classOrInst
	 * @param string $method
	 * @param array $args
	 * @param mixed|callable $argProvider
	 * @return mixed
	 */
	public static function callMethod($classOrInst, $method = '__construct', $args = [], $argProvider = null) {
		if ($method === '__construct') {
			$refBase = new ReflectionClass($classOrInst);
			$refMethod = $refBase->getConstructor();
		}
		else {
			if (!method_exists($classOrInst, $method)) {
				if (!method_exists($classOrInst, '__call')) {
					throw new Exception("The method '{$method}' does not exist");
				}

				return $classOrInst->$method(...$args);
			}

			$refBase = new ReflectionClass($classOrInst);
			$refMethod = $refBase->getMethod($method);
		}

		if ($refMethod) {
			if (!$refMethod->isPublic()) {
				throw new Exception("The method '{$method}' is not public");
			}

			$params = self::gatherFunctionParams($refMethod, $args, $argProvider, "method '{$method}'");
		}
		else {
			$params = [];
		}

		if (is_string($classOrInst)) {
			if ($method === '__construct') {
				return new $classOrInst(...$params);
			}
			else {
				return $classOrInst::$method(...$params);
			}
		}
		
		return $classOrInst->$method(...$params);
	}

	/**
	 * Calls a function, filling in the parameters using the injection service
	 * 
	 * @param string|Closure $fn 
	 * @param array $args 
	 * @param mixed|callable $argProvider
	 * @return mixed
	 */
	public static function callFunction($fn, $args = [], $argProvider = null) {
		$refBase = new ReflectionFunction($fn);

		$params = self::gatherFunctionParams($refBase, $args, $argProvider, "function " . ($fn instanceof Closure ? 'Closure' : $fn));

		return $fn(...$params);
	}

	/**
	 * Collects an array of function parameters using the injection service
	 * 
	 * @param ReflectionFunctionAbstract $refFn 
	 * @param array $args 
	 * @param string $errLocation
	 * @param mixed|callable $argProvider
	 * @return array
	 */
	private static function gatherFunctionParams($refFn, $args, $argProvider, $errLocation) {
		$params = [];
		$ndx = 0;

		foreach ($refFn->getParameters() as $param) {
			/** @var ReflectionParameter $param */
			if (array_key_exists($ndx, $args)) {
				$value = $args[$ndx];
			}
			elseif (array_key_exists($param->getName(), $args)) {
				$value = $args[$param->getName()];
			}
			else {
				$value = self::getUndefined();

				if (!is_null($argProvider)) {
					/** @var ReflectionNamedType|ReflectionUnionType */
					$type = $param->getType();

					if (is_callable($argProvider)) {
						$value = $argProvider($ndx, $param->getName(), $type, self::getUndefined());
					}
					else {
						$value = $argProvider;
					}
				}

				if ($value === self::getUndefined() && $param->isDefaultValueAvailable()) {
					$value = $param->getDefaultValue();
				}
			}

			if ($value === self::getUndefined()) {
				throw new Exception("No value provided for argument {$ndx} called '{$param->getName()}' from the {$errLocation}");
			}

			$params[] = $value;
			$ndx++;
		}

		return $params;
	}
}