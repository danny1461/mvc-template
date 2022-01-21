<?php

namespace Lib\Exception;

use Exception;
use Throwable;
use ReflectionClass;

/**
 * CallstackException
 * 
 * Can be used to place blame on the exception for something the developer
 * did in their code and not in the framework code
 */
class CallstackException extends Exception {
	private static $prop = null;

	public function __construct($message, $stackLevels, $code = 0, Throwable $previous = null) {
		parent::__construct($message, $code, $previous);

		if (is_null(self::$prop)) {
			$refClass = new ReflectionClass(parent::class);
			$prop = $refClass->getProperty('trace');
			$prop->setAccessible(true);
			self::$prop = $prop;
		}

		// 1 level up extra to compensate for this level
		$stack = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), $stackLevels + 1);
		self::$prop->setValue($this, $stack);

		$this->file = $stack[0]['file'];
		$this->line = $stack[0]['line'];
	}
}