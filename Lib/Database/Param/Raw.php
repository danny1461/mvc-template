<?php

namespace Lib\Database\Param;

class Raw {
	private $value;

	public function __construct($rawValue) {
		$this->value = $rawValue;
	}

	public function getRawString() {
		return $this->value;
	}

	public static function from($rawValue) {
		return new self($rawValue);
	}
}