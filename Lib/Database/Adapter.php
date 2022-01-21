<?php

namespace Lib\Database;

use mysqli;
use Exception;
use mysqli_result;
use Lib\Database\Param\Raw;
use Lib\Database\Param\AbstractParam;

class Adapter {
	private $conn;
	private $trackingModels = false;
	private $trackedModelCallbacks = [];
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
		if (count($args) == 1 && is_array($args[0])) {
			$args = $args[0];
		}

		foreach ($args as $key => $val) {
			list($val) = $this->prepareSqlParam($val);
			$args[$key] = $val;
		}
		
		$sql = trim($sql);
		$sql = self::insertSqlParams($sql, $args);

		$this->lastQuery = $sql;
		$result = $this->conn->query($sql);

        if ($result === true) {
			if (stripos($sql, 'INSERT INTO') === 0) {
				return $this->conn->insert_id;
			}

			return true;
        }
        elseif ($result instanceof mysqli_result) {
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            return $rows;
        }
        else {
            return false;
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
	 * Begins a transaction on the MySQL connection
	 *
	 * @return void
	 */
	public function startTransaction() {
		$this->conn->autocommit(false);
		$this->trackingModels = true;
	}

	/**
	 * Aborts the transaction and rollsback the database changes
	 *
	 * @return void
	 */
	public function abortTransaction() {
		$this->conn->rollback();
		$this->conn->autocommit(true);
		$this->trackingModels = false;

		$type = (string)TrackTypeEnum::ABORTED();
		if (isset($this->trackedModelCallbacks[$type])) {
			foreach ($this->trackedModelCallbacks[$type] as $cb) {
				$cb();
			}
		}
		
		$this->trackedModelCallbacks = [];
	}

	/**
	 * Commits all the database changes to the MySQL server
	 *
	 * @return void
	 */
	public function commitTransaction() {
		$this->conn->commit();
		$this->conn->autocommit(true);
		$this->trackingModels = false;

		$type = (string)TrackTypeEnum::COMMITTED();
		if (isset($this->trackedModelCallbacks[$type])) {
			foreach ($this->trackedModelCallbacks[$type] as $cb) {
				$cb();
			}
		}

		$this->trackedModelCallbacks = [];
	}

	/**
	 * Returns whether or not the database is in transaction mode
	 *
	 * @return boolean
	 */
	public function isTrackingModels() {
		return $this->trackingModels;
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

		if ($this->trackingModels) {
			if (!isset($this->trackedModelCallbacks[$type])) {
				$this->trackedModelCallbacks[$type] = [];
			}

			$this->trackedModelCallbacks[$type][] = $cb;
		}
		elseif ($type == TrackTypeEnum::COMMITTED()) {
			return $cb();
		}
	}

	private function prepareSqlParam($val) {
		if ($val instanceof AbstractParam) {
			$val = $val->getVariableValue();
		}

		if ($val instanceof Raw) {
			return [$val->__toString(), 'raw'];
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