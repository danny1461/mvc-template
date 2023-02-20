<?php

namespace Lib\Database;

use Lib\DI;

class ModelQueryExpression {
	private static $id = 0;

	private $sql;
	private $args;

	public function __construct($sql, $args = null) {
		$id = ++self::$id;
		foreach ($args as $key => $val) {
			$newKey = "expr{$id}_{$key}";

			$sql = str_replace(":{$key}:", ":{$newKey}:", $sql);
			$args[$newKey] = $val;
			unset($args[$key]);
		}

		$this->sql = $sql;
		$this->args = $args;
	}

	public function getSql($populated = false) {
		if ($populated) {
			$db = DI::get(IDatabaseAdapter::class);
			return $db->populateQuery($this->sql, $this->getArgs());
		}
		
		return $this->sql;
	}

	public function getArgs() {
		return $this->args ?? [];
	}

	public static function create($sql, $args = null) {
		return new static($sql, $args);
	}

	public static function fromBuilder(ModelQueryBuilder $builder) {
		list($sql, $args) = $builder->compileQuery();
		return new static($sql, $args);
	}
}