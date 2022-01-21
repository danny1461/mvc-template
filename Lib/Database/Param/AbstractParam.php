<?php

namespace Lib\Database\Param;

abstract class AbstractParam {
	protected $value;

	public function getVariableValue() {
		if ($this->value instanceof AbstractParam) {
			return $this->value->getVariableValue();
		}

		return $this->value;
	}
}