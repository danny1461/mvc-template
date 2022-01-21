<?php

namespace Lib\Exception;

use Throwable;

class DIResolutionException extends CallstackException {
	public function __construct($message, $stackLevels, $code = 0, Throwable $previous = null) {
		// One more level to compensate for this level
		parent::__construct($message, $stackLevels + 1, $code, $previous);
	}
}