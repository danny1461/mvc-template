<?php

namespace Lib\Database;

use Exception;
use Lib\DI;

// TODO: Build args at time of execution, not creation
class ModelQueryBuilder {
	public const SINGLE_RESULT = '__s-r_';

	private $_from = null;
	private $_joins = [];
	private $_columns = [];
	private $_modelExtractors = [];
	private $_where = '';
	private $_having = '';
	private $_group = [];
	private $_order = [];
	private $_limit = [null, 0];
	private $_locking = null;
	private $_args = [];

	public function isValid() {
		return !is_null($this->_from) && count($this->_columns) > 0;
	}

	public function from($table, $alias) {
		$this->_from = [$table, $alias];
		return $this;
	}

	public function getFrom() {
		return $this->_from;
	}

	/**
	 * 
	 * @param array<string, string> $columns An array of field names => field values
	 * @return $this 
	 */
	public function addColumns(array $columns) {
		$columns = array_merge($this->_columns, $columns);
		return $this->columns($columns);
	}

	public function columns(array $columns) {
		$this->_columns = $columns;
		$this->_modelExtractors = [];
		
		foreach ($columns as $prop => $val) {
			if (is_array($val)) {
				$this->_modelExtractors[$prop] = $val[0];
			}
			elseif ($val instanceof ModelQueryExpression) {
				$this->_columns[$prop] = $val->getSql();
				$this->_args = array_merge($this->_args, $val->getArgs());
			}
		}

		return $this;
	}

	public function addModelColumn($prop, $model, $fromTable) {
		$this->_columns[$prop] = [$model, $fromTable];
		$this->_modelExtractors[$prop] = $model;
		return $this;
	}

	public function getColumns() {
		return $this->_columns;
	}

	private function join($direction, $table, $alias, $conditional, $args = []) {
		if ($conditional instanceof ModelQueryExpression) {
			$args = array_merge($args, $conditional->getArgs());
			$conditional = $conditional->getSql();
		}

		$this->_joins[$alias] = [
			'type' => $direction,
			'table' => $table,
			'conditional' => $conditional
		];
		$this->_args = array_merge($this->_args, $args);

		return $this;
	}

	public function leftJoin($table, $alias, $conditional, $args = []) {
		return $this->join('left', $table, $alias, $conditional, $args);
	}

	public function rightJoin($table, $alias, $conditional, $args = []) {
		return $this->join('right', $table, $alias, $conditional, $args);
	}

	public function innerJoin($table, $alias, $conditional, $args = []) {
		return $this->join('inner', $table, $alias, $conditional, $args);
	}

	public function getJoins() {
		return $this->_joins;
	}

	public function where($conditional, $args = []) {
		if ($conditional instanceof ModelQueryExpression) {
			$args = array_merge($args, $conditional->getArgs());
			$conditional = $conditional->getSql();
		}

		$this->_where = "({$conditional})";
		$this->_args = array_merge($this->_args, $args);

		return $this;
	}

	public function orWhere($conditional, $args = []) {
		return $this->conditionalCombine('_where', ' OR ', $conditional, $args);
	}

	public function andWhere($conditional, $args = []) {
		return $this->conditionalCombine('_where', ' AND ', $conditional, $args);
	}

	public function getWhere() {
		return $this->_where;
	}

	public function having($conditional, $args = []) {
		if ($conditional instanceof ModelQueryExpression) {
			$args = array_merge($args, $conditional->getArgs());
			$conditional = $conditional->getSql();
		}

		$this->_having = "({$conditional})";
		$this->_args = array_merge($this->_args, $args);

		return $this;
	}

	public function orHaving($conditional, $args = []) {
		return $this->conditionalCombine('_having', ' OR ', $conditional, $args);
	}

	public function andHaving($conditional, $args = []) {
		return $this->conditionalCombine('_having', ' AND ', $conditional, $args);
	}

	public function getHaving() {
		return $this->_having;
	}

	private function conditionalCombine($field, $op, $conditional, $args = []) {
		if ($conditional instanceof ModelQueryExpression) {
			$args = array_merge($args, $conditional->getArgs());
			$conditional = $conditional->getSql();
		}

		$terms = ["({$conditional})"];
		if ($this->$field) {
			array_unshift($terms, $this->$field);
		}

		$conditional = implode($op, $terms);
		if (count($terms) > 1) {
			$conditional = "({$conditional})";
		}

		$this->$field = $conditional;
		$this->_args = array_merge($this->_args, $args);

		return $this;
	}

	/**
	 * 
	 * @param array<string> $fields 
	 * @return static
	 */
	public function groupBy(array $fields) {
		$this->_group = array_map(function($field) {
			if ($field instanceof ModelQueryExpression) {
				$this->_args = array_merge($this->_args, $field->getArgs());
				$field = $field->getSql();
			}

			return $field;
		}, $fields);

		return $this;
	}

	/**
	 * 
	 * @return array
	 */
	public function getGroupBy() {
		return $this->_group;
	}

	/**
	 * @param string|array<string> $selectorWithDirection 
	 * @return $this 
	 */
	public function orderBy($selectorWithDirection) {
		if (!is_array($selectorWithDirection)) {
			$selectorWithDirection = [$selectorWithDirection];
		}

		$this->_order = array_map(function($fieldWithDirection) {
			if ($fieldWithDirection instanceof ModelQueryExpression) {
				$this->_args = array_merge($this->_args, $fieldWithDirection->getArgs());
				$fieldWithDirection = $fieldWithDirection->getSql();
			}

			return $fieldWithDirection;
		}, $selectorWithDirection);

		return $this;
	}

	/**
	 * @param string|array<string> $selectorWithDirection 
	 * @return $this 
	 */
	public function thenBy($selectorWithDirection) {
		if (!is_array($selectorWithDirection)) {
			$selectorWithDirection = [$selectorWithDirection];
		}

		$selectorWithDirection = array_map(function($fieldWithDirection) {
			if ($fieldWithDirection instanceof ModelQueryExpression) {
				$this->_args = array_merge($this->_args, $fieldWithDirection->getArgs());
				$fieldWithDirection = $fieldWithDirection->getSql();
			}

			return $fieldWithDirection;
		}, $selectorWithDirection);

		$this->_order = array_merge($this->_order, $selectorWithDirection);

		return $this;
	}

	public function getOrderBy() {
		return $this->_order;
	}

	public function limit($limit, $offset = 0) {
		$this->_limit = [$offset, $limit];
		return $this;
	}

	public function getLimit() {
		return $this->_limit;
	}

	/**
	 * @param ModelLockTypeEnum|null $lockType 
	 * @return static 
	 */
	public function lockRows($lockType) {
		$this->_locking = $lockType;
		return $this;
	}

	public function compileQuery() {
		if (!$this->isValid()) {
			throw new Exception('Query missing "from" and "columns"');
		}

		$args = $this->_args;
		$sql = 'SELECT';

		#region COLUMNS
		$columns = array_map(static function($value, $name) {
			if (is_array($value)) {
				list($model, $fromTable) = $value;
				$tableMeta = $model::getTableMeta();

				if (is_numeric($name)) {
					return "{$tableMeta['name']}.*";
				}

				$columns = array_map(static function($column) use ($name, $fromTable) {
					return "`{$fromTable}`.`{$column}` AS `{$name}_{$column}`";
				}, array_keys($tableMeta['props']));

				return implode(', ', $columns);
			}
			
			return "{$value} AS `{$name}`";
		}, $this->_columns, array_keys($this->_columns));
		$columns = implode(', ', $columns);

		$sql .= " {$columns}";
		#endregion

		#region FROM
		list($from, $fromAlias) = $this->_from;
		if ($from instanceof ModelQueryBuilder) {
			$from = ModelQueryExpression::fromBuilder($from);
		}

		if ($from instanceof ModelQueryExpression) {
			$args = array_merge($args, $from->getArgs());
			$from = '(' . $from->getSql() . ')';
		}
		elseif (is_subclass_of($from, Model::class)) {
			$from = $from::getTableMeta();
			$from = "`{$from['name']}`";
		}

		$sql .= " FROM {$from} AS `{$fromAlias}`";
		#endregion

		#region JOINS
		$joins = array_map(static function($joinProps, $alias) use (&$args) {
			$table = $joinProps['table'];

			if ($table instanceof ModelQueryBuilder) {
				$table = ModelQueryExpression::fromBuilder($table);
			}

			if ($table instanceof ModelQueryExpression) {
				$args = array_merge($args, $table->getArgs());
				$table = '(' . $table->getSql() . ')';
			}
			elseif (is_subclass_of($table, Model::class)) {
				$table = $table::getTableMeta();
				$table = "`{$table['name']}`";
			}

			$type = strtoupper($joinProps['type']);
			$conditional = $joinProps['conditional'];

			return "{$type} JOIN {$table} AS `{$alias}` ON {$conditional}";
		}, $this->_joins, array_keys($this->_joins));

		if ($joins) {
			$sql .= ' ' . implode(' ', $joins);
		}
		#endregion

		#region WHERE
		if ($this->_where) {
			$sql .= " WHERE {$this->_where}";
		}
		#endregion

		#region GROUP BY
		if ($this->_group) {
			$sql .= ' GROUP BY ' . implode(', ', $this->_group);
		}
		#endregion

		#region HAVING
		if ($this->_having) {
			$sql .= " HAVING {$this->_having}";
		}
		#endregion

		#region ORDER BY
		if ($this->_order) {
			$sql .= ' ORDER BY ' . implode(', ', $this->_order);
		}
		#endregion

		#region LIMIT/OFFSET
		if (!is_null($this->_limit[0])) {
			$sql .= ' LIMIT ' . implode(', ', $this->_limit);
		}
		#endregion

		#region ROW LOCKING
		if (!is_null($this->_locking)) {
			$sql .= $this->_locking == ModelLockTypeEnum::ALLOW_READS()
				? ' LOCK IN SHARE MODE'
				: ' FOR UPDATE';
		}
		#endregion

		return [$sql, $this->_args];
	}

	public function getQuery() {
		list($sql, $args) = $this->compileQuery();

		$db = DI::get(IDatabaseAdapter::class);
		return $db->populateQuery($sql, $args);
	}

	public function execute() {
		list($sql, $args) = $this->compileQuery();

		$db = DI::get(IDatabaseAdapter::class);
		if ($this->_locking && !$db->inTransaction()) {
			throw new Exception('Row locking is only valid with a transaction');
		}

		$result = $db->query($sql, $args);

		if ($this->_modelExtractors) {
			$result = $this->emitResults($result);
		}

		return $this->emitSingleRows($result);
	}

	private function emitResults($rows) {
		foreach ($rows as $row) {
			foreach ($this->_modelExtractors as $prop => $model) {
				/** @var Model $model */
				$prefix = $prop . '_';
				$prefixLen = strlen($prefix);
				$modelProps = [];

				foreach ($row as $key => $val) {
					if (strpos($key, $prefix) === 0) {
						$modelProps[substr($key, $prefixLen)] = $val;
						unset($row[$key]);
					}
				}

				$row[$prop] = $model::fromArray($modelProps, true);
			}

			yield $row;
		}
	}

	private function emitSingleRows($rows) {
		foreach ($rows as $row) {
			if (isset($row[self::SINGLE_RESULT])) {
				yield $row[self::SINGLE_RESULT];
			}
			else {
				yield $row;
			}
		}
	}

	public function clone() {
		$result = new ModelQueryBuilder();
		$result->_from = $this->_from;
		$result->_joins = $this->_joins;
		$result->_columns = $this->_columns;
		$result->_where = $this->_where;
		$result->_having = $this->_having;
		$result->_group = $this->_group;
		$result->_limit = $this->_limit;
		$result->_args = $this->_args;

		return $result;
	}
}