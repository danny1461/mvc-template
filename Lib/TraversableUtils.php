<?php

namespace Lib;

use Traversable;

class TraversableUtils {
	public static function map(Traversable $iter, callable $fn) {
		foreach ($iter as $key => $val) {
			yield $fn($val, $key);
		}
	}
}