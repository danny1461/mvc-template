<?php

namespace Lib\Database;

use mysqli;
use Exception;
use mysqli_result;
use Lib\Database\Param\Raw;
use Lib\Database\Param\IDbParam;

class MysqlAdapter implements IDatabaseAdapter {
	private $conn;
	private $transactionStack = [];
	private $lastQuery = '';

	public function __construct($host, $user, $pass, $dbname) {
		$this->conn = new mysqli($host, $user, $pass, $dbname);

		if ($this->conn->connect_errno) {
			throw new Exception('MySQL connect failed: ' . $this->conn->connect_errno);
		}
		else {
			$this->conn->autocommit(true);
		}
	}

	public function __destruct() {
		$this->conn->close();
	}

	/**
	 * Executes a MySQL query returning the result
	 *
	 * @param string $sql
	 * @param mixed ...$args
	 * @return mixed
	 */
	public function query($sql, ...$args) {
		$sql = $this->populateQuery($sql, ...$args);

		$this->lastQuery = $sql;
		$result = $this->conn->query($sql);

		if ($result === true) {
			if (stripos($sql, 'INSERT INTO') === 0) {
				return $this->conn->insert_id;
			}

			return true;
		}
		elseif ($result instanceof mysqli_result) {
			return $this->emitRows($result);
		}
		else {
			return false;
		}
	}

	public function populateQuery($sql, ...$args) {
		if (count($args) == 1 && is_array($args[0])) {
			$args = $args[0];
		}

		foreach ($args as $key => $val) {
			list($val) = $this->prepareSqlParam($val);
			$args[$key] = $val;
		}
		
		$sql = trim($sql);
		return self::insertSqlParams($sql, $args);
	}

	private function emitRows($result) {
		while ($row = $result->fetch_assoc()) {
			yield $row;
		}
	}

	/**
	 * Gets the last error from the MySQL connection
	 *
	 * @return string
	 */
	public function getLastError() {
		return $this->conn->error;
	}

	/**
	 * Gets the last query that was executed
	 *
	 * @return string
	 */
	public function getLastQuery() {
		return $this->lastQuery;
	}

	/**
	 * Returns whether a transaction is active
	 * @return bool 
	 */
	public function inTransaction() {
		return !!$this->transactionStack;
	}

	private function &getCurrentTransaction() {
		return $this->transactionStack[count($this->transactionStack) - 1];
	}
	
	/**
	 * Begins a transaction on the MySQL connection. If a transaction is already running, attempts to create a savepoint.
	 *
	 * @return void
	 */
	public function startTransaction() {
		if (!$this->inTransaction()) {
			$this->conn->autocommit(false);
			$this->transactionStack[] = [
				'models' => []
			];
		}
		else {
			$savepointName = 'POINT_' . count($this->transactionStack);
			$this->conn->query("SAVEPOINT {$savepointName};");
			$this->transactionStack[] = [
				'id' => $savepointName,
				'models' => []
			];
		}
	}

	/**
	 * Aborts the transaction and rollsback the database changes
	 *
	 * @return void
	 */
	public function abortTransaction() {
		$state = array_pop($this->transactionStack);

		if ($state) {
			if (isset($state['id'])) {
				$this->conn->query("ROLLBACK TO SAVEPOINT {$state['id']};");
			}
			else {
				$this->conn->rollback();
				$this->conn->autocommit(true);
			}

			$type = (string)TrackTypeEnum::ABORTED();
			if (isset($state['models'][$type])) {
				foreach ($state['models'][$type] as $cb) {
					$cb();
				}
			}
		}
	}

	/**
	 * Commits all the database changes to the MySQL server
	 *
	 * @return void
	 */
	public function commitTransaction() {
		$state = array_pop($this->transactionStack);

		if ($state) {
			if (isset($state['id'])) {
				$this->conn->query("RELEASE SAVEPOINT {$state['id']};");
				// Migrate tracked models back to previous state handler
				$previousState = &$this->getCurrentTransaction();
				foreach ($state['models'] as $type => $cbs) {
					if (!isset($previousState['models'][$type])) {
						$previousState['models'][$type] = [];
					}

					$previousState['models'][$type] = array_merge($previousState['models'][$type], $cbs);
				}
			}
			else {
				$this->conn->commit();
				$this->conn->autocommit(true);

				$type = (string)TrackTypeEnum::COMMITTED();
				if (isset($state['models'][$type])) {
					foreach ($state['models'][$type] as $cb) {
						$cb();
					}
				}
			}
		}
	}

	/**
	 * Wraps the callable in a transaction that aborts on error and commits on success
	 * 
	 * @param callable $fn 
	 * @return void 
	 */
	public function withTransaction(callable $fn) {
		$this->startTransaction();

		try {
			$fn();
			$this->commitTransaction();
		}
		catch (Exception $e) {
			$this->abortTransaction();
			throw $e;
		}
	}

	/**
	 * Allows the database to update a model when a transaction is committed or aborted
	 *
	 * @param DbTrackTypeEnum $type
	 * @param callable $cb
	 * @return void
	 */
	public function trackModel(TrackTypeEnum $type, callable $cb) {
		$type = (string)$type;

		if ($this->inTransaction()) {
			$state = &$this->getCurrentTransaction();
			if (!isset($state['models'][$type])) {
				$state['models'][$type] = [];
			}

			$state['models'][$type][] = $cb;
		}
		elseif ($type == TrackTypeEnum::COMMITTED()) {
			return $cb();
		}
	}

	private function prepareSqlParam($val) {
		if ($val instanceof Raw) {
			return [$val->getRawString(), 'raw'];
		}
		elseif ($val instanceof IDbParam) {
			$val = $val->toDbParam();
		}

		switch ($type = gettype($val)) {
			case 'int':
			case 'integer':
			case 'double':
			case 'NULL':
				return [$val, $type];	// These are fine as is
			case 'boolean':
				return [$val ? 1 : 0, $type];
			case 'array':
				$ret = '';
				$subType = null;
				foreach ($val as $v) {
					if ($ret) {
						$ret .= ',';
					}

					list($v, $vType) = $this->prepareSqlParam($v);
					if ($vType == 'NULL') {
						continue;
					}
					elseif ($vType == 'array') {
						throw new Exception('Array params must not contain other arrays');
					}

					if (is_null($subType)) {
						$subType = $vType;
					}
					elseif ($subType != $vType) {
						throw new Exception('Array params must contain like-typed entries');
					}

					$ret .= $v;
				}

				return ["({$ret})", $type];
			case 'object':
				if (method_exists($val, '__toString')) {
					$val = $val->__toString();
				}
				else {
					throw new Exception('Object cannot be used as a parameter in a query');
				}
				// Passthru intended
			case 'string':
				return ["'" . $this->conn->real_escape_string($val) . "'", 'string'];
			default:
				throw new Exception(gettype($val) . ' cannot be used as a parameter in a query');
		}
	}

	/**
	 * Takes a query with placeholders and inserts any variables, subtly adjusting query where needed
	 * 
	 * @param mixed $sql 
	 * @param mixed $params 
	 * @return string
	 * @throws Exception 
	 */
	private static function insertSqlParams($sql, $params) {
		// Scan query for any set keywords
		$lastNdx = 0;
		$strLen = strlen($sql);
		$result = '';
		$currentToken = '';
		$inSet = false;
		
		for ($i = 0; $i < $strLen; $i++) {
			if (ctype_space($sql[$i])) {
				if (strcasecmp($currentToken, 'SET') == 0) {
					$result .= self::insertSqlParamsHelper(substr($sql, $lastNdx, $i - $lastNdx), $params, false);
					$lastNdx = $i;
					$inSet = true;
				}
				elseif (strcasecmp($currentToken, 'WHERE') == 0) {
					$result .= self::insertSqlParamsHelper(substr($sql, $lastNdx, $i - $lastNdx), $params, $inSet);
					$lastNdx = $i;
					$inSet = false;
				}

				$currentToken = '';
			}
			elseif ($sql[$i] == '"' || $sql[$i] == "'") {
				// Read past the string
				$quoteChar = $sql[$i];
				$i++;
				$escaping = false;
				while ($sql[$i] != $quoteChar || $escaping) {
					$char = $sql[$i];
					$i++;
					if ($i == $strLen) {
						throw new Exception('Bad SQL query');
					}

					if ($escaping) {
						$escaping = false;
					}
					else {
						if ($char == '\\') {
							$escaping = true;
						}
					}
				}
				$i++;
			}
			else {
				$currentToken .= $sql[$i];
			}
		}

		// Grab last piece
		$result .= self::insertSqlParamsHelper(substr($sql, $lastNdx), $params, $inSet);
		return $result;
	}

	/**
	 * Takes a query with placeholders and inserts any variables
	 * 
	 * @param mixed $sql 
	 * @param mixed $params 
	 * @param mixed $inSet 
	 * @return string[]|string|null 
	 */
	private static function insertSqlParamsHelper($sql, $params, $inSet) {
		return preg_replace_callback('/(?:(!?=|<>)\\s*)?([\'"]?):([a-z0-9_-]+):\\2/i', function($matches) use ($params, $inSet) {
			if (isset($params[$matches[3]])) {
				return ltrim($matches[1] . ' ') . $params[$matches[3]];
			}

			if ($inSet) {
				return ltrim($matches[1] . ' ') . 'NULL';
			}

			return empty($matches[1])
				? 'NULL'
				: ($matches[1] == '='
					? 'IS NULL'
					: 'IS NOT NULL');
		}, $sql);
	}
}