<?php

namespace Lib\Database\Param;

class Raw extends AbstractParam {
	public function __construct($rawValue) {
		$this->value = $rawValue;
	}

	public function getVariableValue() {
		return $this;
	}

	public function __toString() {
		return $this->value;
	}

	public static function from($rawValue) {
		return new self($rawValue);
	}
}