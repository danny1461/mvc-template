<?php

namespace Lib\Render;

class ViewModel {
	public function __construct($values = []) {
		foreach ($values as $key => $val) {
			if (property_exists($this, $key)) {
				$this->$key = $val;
			}
		}
	}
}