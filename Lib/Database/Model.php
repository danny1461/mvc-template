<?php

namespace Lib\Database;

use Lib\DI;
use DateTime;
use Exception;
use Lib\Utility\AnnotationParser;

// Naive ORM
// Works well for simple cases

// /**
//  * @Table('users')
//  *\
// class User extends Model {
// 	/**
// 	 * @Key('Id')
// 	 * @AutoIncrement
// 	 *\
// 	public $id;
//
// 	/**
// 	 * @Column('created_at')
// 	 *\
// 	public $createdAt;
// }

/**
 * A base class to be used for modeling database tables
 */
abstract class Model {
	private static $rowLocking = [];
	protected static $tableMeta = [];
	protected $_exists;

	/**
	 * Returns a single record by providing values for it's keys
	 *
	 * @param mixed ...$keys
	 * @return static
	 */
	public static function getByKey(...$keys) {
		$tableMeta = static::getTableMeta();
		if (count($keys) != count($tableMeta['keys'])) {
			throw new Exception('Number of keys passed do not match the number of keys for the table');
		}

		$query = [];
		$queryArgs = [];
		$key = reset($keys);
		foreach ($tableMeta['keys'] as $keyProp) {
			$query[] = "{$keyProp} = :" . count($queryArgs) . ":";
			$queryArgs[] = $key;
			$key = next($keys);
		}

		return static::findOne(implode(' AND ', $query), $queryArgs);
	}

	/**
	 * Returns an array of matching records
	 *
	 * @param string $query
	 * @param mixed ...$args
	 * @return static[]
	 */
	public static function find($query = '', ...$args) {
		$query = self::transformStringQuery($query);
		$query = trim($query);
		if (!$query) {
			$query = '1=1';
		}

		$tableMeta = static::getTableMeta();
		$sql = "SELECT * FROM `{$tableMeta['name']}` WHERE {$query}";

		if (!empty(self::$rowLocking[static::class])) {
			$postfix = self::$rowLocking[static::class] == ModelLockTypeEnum::ALLOW_READS()
				? 'LOCK IN SHARE MODE'
				: 'FOR UPDATE';
			$sql .= " {$postfix}";
		}
		
		return static::query($sql, ...$args);
	}

	/**
	 * Fetches a single record from the provided query
	 *
	 * @param string $query
	 * @param mixed ...$args
	 * @return static
	 */
	public static function findOne($query = '', ...$args) {
		$query = trim($query);
		if (!$query) {
			$query = '1=1';
		}

		$result = static::find("{$query} LIMIT 1", ...$args);

		if ($result === false) {
			return $result;
		}

		foreach ($result as $row) {
			return $row;
		}

		return null;
	}

	/**
	 * Returns the count of records that match constraints
	 *
	 * @param string $query
	 * @param mixed ...$args
	 * @return int
	 */
	public static function count($query = '', ...$args) {
		$query = self::transformStringQuery($query);

		$tableMeta = static::getTableMeta();
		$db = DI::get(IDatabaseAdapter::class);
		$result = $db->query("SELECT COUNT(*) as row_count FROM {$tableMeta['name']} WHERE 1=1" . ($query ? " AND {$query}" : ''), ...$args);

		if ($result === false) {
			return false;
		}

		return intval($result[0]['row_count']);
	}

	/**
	 * Performs a complete MySQL query and casts the results to this model type
	 *
	 * @param string $query
	 * @param mixed ...$args
	 * @return static[]
	 */
	public static function query($query, ...$args) {
		$db = DI::get(IDatabaseAdapter::class);

		$result = $db->query($query, ...$args);

		if ($result === false) {
			return false;
		}

		foreach ($result as $recordArr) {
			yield static::fromArray($recordArr, true);
		}
	}

	private static function transformStringQuery($query) {
		if (!$query) {
			return $query;
		}

		$tableMeta = static::getTableMeta();

		$patterns = [];
		foreach ($tableMeta['columns'] as $prop => $columnMeta) {
			$patterns[] = $prop;
		}
		$patterns = '/\\b(' . implode('|', $patterns) . ')\\b/';

		return preg_replace_callback($patterns, function($matches) use ($tableMeta) {
			return $tableMeta['columns'][$matches[1]]['name'];
		}, $query);
	}

	/**
	 * Saves the current model properties to the database
	 *
	 * @return boolean
	 */
	public function save() {
		$tableMeta = static::getTableMeta();
		$queryArgs = [];
		
		// Update
		if ($this->_exists) {
			$update = [];
			$where = [];
			foreach ($tableMeta['columns'] as $prop => $propInfo) {
				if (isset($propInfo['key'])) {
					$where[] = "t.`{$propInfo['name']}` = :{$prop}:";
				}
				else {
					$update[] = "t.`{$propInfo['name']}` = :{$prop}:";
				}

				$queryArgs[$prop] = $this->propToDb($prop);
			}

			$update = implode(', ', $update);
			$where = implode(' AND ', $where);

			$db = DI::get(IDatabaseAdapter::class);
			return $db->query("UPDATE `{$tableMeta['name']}` as t SET {$update} WHERE {$where} LIMIT 1", $queryArgs);
		}

		// Insert
		else {
			$columns = [];
			$values = [];
			$initInitializer = false;

			foreach ($tableMeta['columns'] as $prop => $propInfo) {
				if (isset($propInfo['key']) && isset($propInfo['autoIncrement']) && !($this->$prop > 0)) {
					continue;
				}

				$propValue = $this->propToDb($prop);
				if (is_null($propValue) && isset($propInfo['initializer'])) {
					if (!$initInitializer) {
						Initializers::init();
						$initInitializer = true;
					}
					
					list($initializer, $args) = $propInfo['initializer'];
					$this->$prop = $initializer($this, $args);
					$propValue = $this->propToDb($prop);
				}

				$columns[] = "`{$propInfo['name']}`";
				$values[] = ":{$prop}:";
				$queryArgs[$prop] = $propValue;
			}

			$columns = implode(', ', $columns);
			$values = implode(', ', $values);

			$db = DI::get(IDatabaseAdapter::class);
			$result = $db->query("INSERT INTO `{$tableMeta['name']}` ({$columns}) VALUES ({$values})", $queryArgs);
			
			if ($result !== false) {
				if (!is_bool($result)) {
					if ($tableMeta['autoIncrement']) {
						$autoProp = $tableMeta['autoIncrement'];
						$this->$autoProp = $result;
					}
				}

				$this->_exists = true;
				$db->trackModel(TrackTypeEnum::ABORTED(), function() { $this->_exists = false; });

				return true;
			}

			return false;
		}
	}

	/**
	 * Deletes the model from the database
	 *
	 * @return boolean
	 */
	public function delete() {
		if ($this->_exists) {
			$query = [];
			$queryArgs = [];
			$tableMeta = static::getTableMeta();
			foreach ($tableMeta['keys'] as $keyProp) {
				$query[] = "{$tableMeta['columns'][$keyProp]['name']} = :{$keyProp}:";
				$queryArgs[$keyProp] = $this->propToDb($keyProp);
			}
			$query = implode(' AND ', $query);

			$db = DI::get(IDatabaseAdapter::class);
			$result = $db->query("DELETE FROM {$tableMeta['name']} WHERE {$query} LIMIT 1", $queryArgs);
			if ($result === true) {
				$this->_exists = false;
				$db->trackModel(TrackTypeEnum::ABORTED(), function() { $this->_exists = true; });
			}

			return $result === true;
		}
	}

	/**
	 * Returns the database ready value
	 *
	 * @param string $prop
	 * @return mixed
	 */
	protected function propToDb($prop) {
		$meta = static::getTableMeta();

		if (is_null($this->$prop) && isset($meta['columns'][$prop]['nullable'])) {
			return $this->$prop;
		}

		if (isset($meta['columns'][$prop]['serialization'])) {
			switch($meta['columns'][$prop]['serialization']) {
				case 'serialize':
					return serialize($this->$prop);
				case 'json':
					return json_encode($this->$prop);
			}
		}

		if (isset($meta['columns'][$prop]['dataType'])) {
			switch ($meta['columns'][$prop]['dataType']) {
				case 'bool':
					return $this->$prop
						? 1
						: 0;

				case 'int':
					if (isset($meta['columns'][$prop]['dateFormat'])) {
						$dt = DateTime::createFromFormat($meta['columns'][$prop]['dateFormat'], $this->$prop);
						return $dt->getTimestamp();
					}
			}
		}

		return $this->$prop;
	}

	/**
	 * Sets the prop after loading from the db
	 *
	 * @param string $prop
	 * @param string $value
	 * @return void
	 */
	protected function dbToProp($prop, $value) {
		$meta = static::getTableMeta();

		if (isset($meta['columns'][$prop]['serialization'])) {
			try {
				switch($meta['columns'][$prop]['serialization']) {
					case 'serialize':
						$this->$prop = unserialize($value);
						return;
					case 'json':
						$this->$prop = json_decode($value, true);
						return;
				}
			}
			catch (Exception $e) {
				$this->$prop = $value;
			}
		}

		if (isset($meta['columns'][$prop]['dataType'])) {
			switch ($meta['columns'][$prop]['dataType']) {
				case 'bool':
					$this->$prop = !!$value;
					return;
				case 'int':
					$this->$prop = intval($value);
					if (isset($meta['columns'][$prop]['dateFormat'])) {
						$this->$prop = date($meta['columns'][$prop]['dateFormat'], $this->$prop);
					}
					return;
				case 'float':
					$fn = "{$meta['columns'][$prop]['dataType']}val";
					$this->$prop = $fn($value);
					return;
			}
		}
		
		$this->$prop = $value;
	}

	/**
	 * Returns whether or not this model is mirrored in the database
	 *
	 * @return boolean
	 */
	public function doesExist() {
		return $this->_exists;
	}

	/**
	 * Returns all the data used to create this model instance
	 * 
	 * @return array
	 * @throws Exception 
	 */
	public function getFieldArray() {
		$tableMeta = static::getTableMeta();
		$result = [];

		foreach ($tableMeta['columns'] as $prop => $columnMeta) {
			$result[$prop] = $this->propToDb($prop);
		}

		return $result;
	}

	/**
	 * Generates an array of keys for this model instance
	 * 
	 * @return array
	 */
	public function getKeys() {
		$tableMeta = static::getTableMeta();
		return array_map([$this, 'propToDb'], $tableMeta['keys']);
	}

	protected static function Table(&$tableMeta, $tableName) {
		$tableMeta['name'] = $tableName;
	}

	protected static function Key(&$columnMeta, $order = 0) {
		if (!isset($columnMeta['dataType'])) {
			$columnMeta['dataType'] = 'int';
		}

		$columnMeta['key'] = $order;
	}

	protected static function Nullable(&$columnMeta) {
		$columnMeta['nullable'] = true;
	}

	protected static function AutoIncrement(&$columnMeta) {
		$columnMeta['autoIncrement'] = true;
	}

	protected static function Column(&$columnMeta, $name) {
		$columnMeta['name'] = $name;
	}

	protected static function Required(&$columnMeta) {
		$columnMeta['required'] = true;
	}

	protected static function MinLength(&$columnMeta, $length) {
		$columnMeta['minLength'] = $length;
	}

	protected static function MaxLength(&$columnMeta, $length) {
		$columnMeta['maxLength'] = $length;
	}

	protected static function NotMapped(&$columnMeta) {
		$columnMeta['notMapped'] = true;
	}

	protected static function Serialized(&$columnMeta) {
		$columnMeta['serialization'] = 'serialize';
	}

	protected static function JSON(&$columnMeta) {
		$columnMeta['serialization'] = 'json';
	}

	protected static function DataType(&$columnMeta, $type) {
		$type = strtolower($type);
		switch ($type) {
			case 'bool':
			case 'boolean':
				$type = 'bool';
			case 'int':
			case 'float':
			case 'string':
				$columnMeta['dataType'] = $type;
				break;

			default:
				break;
		}
	}

	protected static function DateFormat(&$columnMeta, $format = IDatabaseAdapter::DATETIME_FORMAT) {
		if (!isset($columnMeta['dataType'])) {
			$columnMeta['dataType'] = 'int';
		}

		$columnMeta['dateFormat'] = $format;
	}

	protected static function DatabaseGenerated(&$columnMeta) {
		$columnMeta['databaseGenerated'] = true;
	}

	protected static function Initializer(&$columnMeta, $method, ...$args) {
		if (!is_callable($method)) {
			throw new Exception('Initializer requires a callable');
		}

		$columnMeta['initializer'] = [$method, $args];
	}

	/**
	 * Returns the observed properties about the model's table structure
	 *
	 * @return array
	 */
	public static function getTableMeta() {
		if (!isset(self::$tableMeta[static::class])) {
			$meta = [
				'name' => static::class,
				'columns' => [],
				'props' => [],
				'keys' => [],
				'autoIncrement' => false
			];

			$annotations = AnnotationParser::parseClass(static::class);

			foreach ($annotations['properties'] as $prop => $propInfo) {
				// Ignore non-public properties
				if (!in_array('public', $propInfo['modifiers'])) {
					continue;
				}

				$propMeta = [
					'name' => $prop
				];

				foreach ($propInfo['calls'] as $call) {
					$fn = $call['func'];
					static::$fn($propMeta, ...$call['args']);
				}

				if (isset($propMeta['notMapped']) && $propMeta['notMapped']) {
					continue;
				}

				if (isset($propMeta['key']) && isset($propMeta['autoIncrement'])) {
					$meta['autoIncrement'] = $prop;
				}

				if (!isset($propMeta['dataType'])) {
					$propMeta['dataType'] = 'string';
				}

				$meta['columns'][$prop] = $propMeta;
			}

			foreach ($meta['columns'] as $prop => $columnInfo) {
				$meta['props'][$columnInfo['name']] = $prop;

				if (isset($columnInfo['key'])) {
					$order = $columnInfo['key'];
					while (isset($meta['keys'][$order])) {
						$order++;
					}

					$meta['keys'][$order] = $prop;
				}
			}
			ksort($meta['keys']);

			foreach ($annotations['class']['calls'] as $call) {
				$fn = $call['func'];
				static::$fn($meta, ...$call['args']);
			}

			self::$tableMeta[static::class] = $meta;
		}

		return self::$tableMeta[static::class];
	}

	/**
	 * Creates a new instance of the model and fills it's properties from the given data
	 *
	 * @param array $data
	 * @param boolean $exists
	 * @return static
	 */
	public static function fromArray(array $data, $exists = false) {
		$tableMeta = static::getTableMeta();
		if ($exists) {
			foreach ($tableMeta['keys'] as $keyProp) {
				if (!isset($data[$keyProp])) {
					$keyProp = $tableMeta['columns'][$keyProp]['name'];
					if (!isset($data[$keyProp])) {
						throw new Exception("Required key {$keyProp} missing on existing record");
					}
				}
			}
		}

		$record = new static();

		foreach ($data as $key => $val) {
			$prop = $key;
			if (isset($tableMeta['props'][$prop])) {
				$prop = $tableMeta['props'][$prop];
			}

			if (property_exists($record, $prop)) {
				$record->dbToProp($prop, $val);
			}
		}

		$record->_exists = $exists;

		if ($record instanceof IModelOnInit) {
			$record->onInit();
		}

		return $record;
	}

	/**
	 * Returns if row locking mode is enabled for this model
	 * 
	 * @return bool 
	 */
	protected static function isRowLockingEnabled() {
		return !empty(self::$rowLocking[static::class]);
	}

	/**
	 * Begin row locking where any models fetched during will prevent writes and maybe reads for the duration. Must be pai
	 * 
	 * @param ModelLockTypeEnum|null $lockType 
	 * @return void 
	 * @throws Exception 
	 */
	public static function startRowLocking(ModelLockTypeEnum $lockType = null) {
		$db = DI::get(IDatabaseAdapter::class);

		if (!$db->inTransaction()) {
			throw new Exception('Row tracking is only valid with a transaction');
		}

		self::$rowLocking[static::class] = (string)($lockType ?? ModelLockTypeEnum::ALLOW_READS());
	}

	/**
	 * End row locking on this model
	 * 
	 * @return void 
	 */
	public static function stopRowLocking() {
		unset(self::$rowLocking[static::class]);
	}

	/**
	 * Makes all fetch operations within the callable on this Model be row locked for the duration
	 * 
	 * @param callable $fn 
	 * @param ModelLockTypeEnum|null $lockType
	 * @return void 
	 */
	public static function withRowLocking(callable $fn, ModelLockTypeEnum $lockType = null) {
		self::startRowLocking($lockType);
	
		try {
			$fn();
		}
		finally {
			self::stopRowLocking();
		}
	}

	public static function getQueryBuilder($table = null) {
		$builder = new ModelQueryBuilder();

		$table = $table ?? basename(str_replace('\\', '/', static::class));
		$builder->from(static::class, $table);
		$builder->addModelColumn(ModelQueryBuilder::SINGLE_RESULT, static::class, $table);

		return $builder;
	}
}