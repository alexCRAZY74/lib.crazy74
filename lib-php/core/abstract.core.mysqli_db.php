<?php

declare(strict_types=1);

namespace core;

abstract class mysqli_db {

	public static bool $debug = false;
	public static \mysqli|bool $db_link = false;
	public static \mysqli_result|bool $last_result = false;
	public static bool $connectionInited = false;
	public static bool $ignoreErrors = false;
	public static array $rowCache = [];
	public static array $tableStructureCache = [];
	public static array $tableExistsCache = [];
	public static array $procedureExistsCache = [];
	public static array $fieldExistsCache = [];

	public static function query(string $sql): \mysqli_result|bool {
		if (static::is_inited()) {
			try {
				static::$last_result = static::$db_link->query($sql);
			} catch (\Throwable $ex) {
				\errors::Add($ex->getMessage());
			}

			if (static::$last_result === false && static::$db_link instanceof \mysqli) {
				$errorMsg = 'MySQL error ' . static::$db_link->connect_errno . ' : ' . static::$db_link->connect_error;
				\errors::Add($errorMsg);
			}

			return static::$last_result;
		}

		return false;
	}

	public static function connectData(): array {
		return ['localhost', 'root', '12345', 'mysql'];
	}

	public static function connect(): \mysqli|bool {
		if (static::$db_link instanceof \mysqli) {
			return static::$db_link;
		}

		if (class_exists('mysqli')) {
			ini_set('display_errors', 'Off');
			[$DB_SERVER, $DB_USERNAME, $DB_PASSWORD, $DB_DATABASE, $DB_PORT] = static::connectData();

			if (empty($DB_PORT)) {
				$DB_PORT = (int) ini_get('mysqli.default_port');
			}

			static::$db_link = new \mysqli($DB_SERVER, $DB_USERNAME, $DB_PASSWORD, $DB_DATABASE, (int) $DB_PORT);
			ini_set('display_errors', 'On');

			if (static::$db_link->connect_errno) {
				\errors::Add('Connection MySQL error ' . static::$db_link->connect_errno . ' : ' . static::$db_link->connect_error);
				static::$db_link = false;
				return false;
			}

			return static::$db_link;
		}

		return false;
	}

	public static function init(): bool {
		if (static::$db_link instanceof \mysqli) {
			if (!static::$connectionInited) {
				static::$db_link->query("SET NAMES utf8mb4");
				static::$db_link->query("SET `time_zone` = '" . date('P') . "'");
			}
			static::$connectionInited = true;
			return static::$connectionInited;
		}
		return false;
	}

	public static function is_inited(): bool {
		return (static::connect() && static::init());
	}

	public static function insert(string $table, array $data, bool $typeupdate = true): int|string|bool {
		if (!static::is_inited() || empty($data)) {
			return false;
		}

		$cmd = isset($data['id']) ? 'REPLACE' : 'INSERT';
		if ($typeupdate) {
			$data = static::updateSqlData($data, $table);
		}

		if (!empty($data)) {
			static::rowCacheClear($table);
			$keys = array_keys($data);
			$values = array_values($data);

			$sql = "$cmd INTO $table (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
			$success = static::query($sql);
			if ($success) {
				return static::$db_link->insert_id;
			}
		}

		return false;
	}

	public static function insertIgnore(string $table, array $data, bool $typeupdate = true): int|string|bool {
		if (!static::is_inited() || empty($data)) {
			return false;
		}

		if ($typeupdate) {
			$data = static::updateSqlData($data, $table);
		}

		if (!empty($data)) {
			static::rowCacheClear($table);
			$keys = array_keys($data);
			$values = array_values($data);

			$sql = "INSERT IGNORE INTO $table (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
			$success = static::query($sql);
			if ($success) {
				return static::$db_link->insert_id;
			}
		}

		return false;
	}

	public static function replace(string $table, array $data, bool $typeupdate = true): int|string|bool {
		if (!static::is_inited() || empty($data)) {
			return false;
		}

		if ($typeupdate) {
			$data = static::updateSqlData($data, $table);
		}

		if (!empty($data)) {
			static::rowCacheClear($table);
			$keys = array_keys($data);
			$values = array_values($data);

			$sql = "REPLACE INTO $table (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
			$success = static::query($sql);
			if ($success) {
				return static::$db_link->insert_id;
			}
		}

		return false;
	}

	public static function update_sql(string $table, mixed $id, array $data, bool $typeupdate = true): string|bool {
		if (!static::is_inited() || empty($data)) {
			return false;
		}

		if ($typeupdate) {
			$data = static::updateSqlData($data, $table);
		}

		if (!empty($data)) {
			$values = [];
			$where = [];

			if (is_array($id)) {
				static::rowCacheClear($table);
				$where = $id;
			} else {
				static::rowCacheClear($table, $id);
				$escapedId = is_string($id) ? "'$id'" : $id;

				if (empty($where) && static::fieldExists($table, 'guid') && is_string($id)) {
					$where[] = "`guid` = $escapedId";
				}
				if (empty($where) && static::fieldExists($table, 'id')) {
					$where[] = "`id` = $escapedId";
				}
			}

			foreach ($data as $key => $value) {
				$values[] = "$key = $value";
			}

			$sql = "UPDATE $table SET " . implode(",  \r\n", $values) . "\r\n";
			if (!empty($where)) {
				$sql .= "WHERE " . implode(' AND ', $where);
			}

			return $sql;
		}

		return false;
	}

	public static function update(string $table, mixed $id, array $data, bool $typeupdate = true): \mysqli_result|bool {
		$sql = static::update_sql($table, $id, $data, $typeupdate);
		if (is_string($sql)) {
			return static::query($sql);
		}
		return false;
	}

	public static function assoc(mixed $param1, mixed $param2 = false): array|bool {
		$sql = $param2 === false ? $param1 : $param2;
		$key = $param2 === false ? false : $param1;
		$result = false;

		$query = static::query($sql);
		if ($query instanceof \mysqli_result && $query->num_rows > 0) {
			$result = [];
			while ($row = $query->fetch_assoc()) {
				if (is_string($key) && !empty($key)) {
					$result[$row[$key]] = $row;
				} else {
					$result[] = $row;
				}
			}
			$query->close();
		}

		return $result;
	}

	public static function id_list(string $table, array $where = [], string $idField = 'id'): array|bool {
		$result = false;
		$sql = "SELECT DISTINCT `$idField` FROM `$table`" . (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where));

		$query = static::query($sql);
		if ($query instanceof \mysqli_result && $query->num_rows > 0) {
			$struc = static::tableKeysTypes($table);
			$result = [];
			while ($row = $query->fetch_assoc()) {
				if (!empty($idField)) {
					if (isset($struc[$idField])) {
						settype($row[$idField], $struc[$idField]);
					}
					$result[] = $row[$idField];
				}
			}
			$query->close();

			if (empty($result)) {
				$result = false;
			}
		}

		return $result;
	}

	public static function get_array(mixed $param1, mixed $param2 = false, string $typekey = 'string'): array|bool {
		$sql = $param2 === false ? $param1 : $param2;
		$key = $param2 === false ? false : $param1;
		$result = false;

		$query = static::query($sql);
		if ($query instanceof \mysqli_result && $query->num_rows > 0) {
			$result = [];
			while ($row = $query->fetch_assoc()) {
				if (is_string($key) && !empty($key)) {
					settype($row[$key], $typekey);
					$result[] = $row[$key];
				} else {
					$result[] = $row;
				}
			}
			$query->close();
		}

		return $result;
	}

	public static function row(mixed $param1, mixed $param2 = false): mixed {
		$sql = $param2 === false ? $param1 : $param2;
		$key = $param2 === false ? false : $param1;
		$result = false;

		$query = static::query($sql);
		if ($query instanceof \mysqli_result && $query->num_rows > 0) {
			$result = $query->fetch_assoc();
			if (is_string($key) && !empty($key)) {
				$result = $result[$key] ?? false;
			}
			$query->close();
		}

		return $result;
	}

	public static function rowCacheStore(string $table, mixed $id, mixed $row): void {
		$cid = serialize($id);
		if (!isset(static::$rowCache[$table])) {
			static::$rowCache[$table] = [];
		}
		static::$rowCache[$table][$cid] = $row;
	}

	public static function rowCacheGet(string $table, mixed $id): mixed {
		$cid = serialize($id);
		return static::$rowCache[$table][$cid] ?? null;
	}

	public static function rowCacheClear(?string $table = null, mixed $id = null): void {
		if (is_null($table)) {
			static::$rowCache = [];
			return;
		}

		$cid = serialize($id);
		if (isset(static::$rowCache[$table])) {
			if (is_null($id)) {
				unset(static::$rowCache[$table]);
			} elseif (isset(static::$rowCache[$table][$cid])) {
				unset(static::$rowCache[$table][$cid]);
			}
		}
	}

	public static function fullRow(string $table, mixed $id, array $select = ['*']): mixed {
		$cacheID = (is_array($id) ? implode('_', $id) : $id) . '_' . implode('_', $select);
		$cache = static::rowCacheGet($table, $cacheID);

		if (is_array($cache)) {
			return $cache;
		}

		$row = static::tablerow($table, $id, $select);
		static::rowCacheStore($table, $cacheID, $row);
		return $row;
	}

	public static function tablerow(string $table, mixed $id, array $select = ['*']): mixed {
		$where = [];
		if (is_array($id)) {
			$where = $id;
		} else {
			$escapedId = is_string($id) ? "'$id'" : $id;
			if (empty($where) && static::fieldExists($table, 'guid') && is_string($id)) {
				$where[] = "`guid` = $escapedId";
			}
			if (empty($where) && static::fieldExists($table, 'id')) {
				$where[] = "`id` = $escapedId";
			}
		}

		if (empty($where)) {
			return false;
		}

		return static::row("SELECT " . implode(', ', $select) . " FROM `" . addslashes($table) . "` WHERE " . implode(' AND ', $where));
	}

	protected static function AssignRowValue(mixed &$object, string $table, string $key, mixed $value, string $type = 'unknown'): void {
		$legalset = ['boolean', 'integer', 'float', 'double'];
		$temp = [];

		if (in_array($type, $legalset, true)) {
			settype($value, $type);
		}

		switch ($type) {
			case 'datetime':
				$temp[$key] = $value;
				$temp[$key . ".text"] = \dates::fmtSmart($value);
				break;
			default:
				$temp[$key] = $value;
				break;
		}

		if (!empty($temp)) {
			foreach ($temp as $k => $v) {
				if (is_object($object)) {
					$object->{$k} = $v;
				}
				if (is_assoc($object) || (is_array($object) && empty($object))) {
					$object[$k] = $v;
				}
			}
		}
	}

	public static function AssignRow(mixed &$object, string $table, mixed $param, string $prefix = ""): bool {
		$row = is_assoc($param) ? $param : static::fullRow($table, $param);

		if (is_assoc($row) && is_string($table)) {
			foreach ($row as $key => $value) {
				if (is_string($value)) {
					$value = stripslashes($value);
				}
				static::AssignRowValue($object, $table, $prefix . $key, $value, static::tableKeyType($table, $key));
			}
			return true;
		}

		return false;
	}

	public static function tableKeyType(string $table, string $field): string {
		$struc = static::tableKeysTypes($table);
		return $struc[$field] ?? 'unknown';
	}

	public static function tableKeysTypes(string $table): array {
		$struc = [];
		$row = static::tableStructure($table);

		foreach ($row as $key => $f) {
			$type = isset($f['Type']) ? strtolower($f['Type']) : 'loss';

			if ($type === 'datetime' || str_starts_with($type, 'timestamp')) {
				$struc[$key] = 'datetime';
			} elseif (str_starts_with($type, 'tinyint(1)')) {
				$struc[$key] = 'boolean';
			} elseif (
				str_starts_with($type, 'int') ||
				str_starts_with($type, 'tinyint') ||
				str_starts_with($type, 'smallint') ||
				str_starts_with($type, 'bigint')
			) {
				$struc[$key] = 'integer';
			} elseif (
				str_starts_with($type, 'float') ||
				str_starts_with($type, 'double') ||
				str_starts_with($type, 'decimal')
			) {
				$struc[$key] = 'float';
			} else {
				$struc[$key] = 'string';
			}
		}

		return $struc;
	}

	public static function tableStructure(string $table): array {
		if (isset(static::$tableStructureCache[$table])) {
			return static::$tableStructureCache[$table];
		}

		$struc = [];
		$row = static::assoc('Field', "SHOW COLUMNS FROM " . $table);

		if (is_array($row)) {
			foreach ($row as $key => $f) {
				unset($f['Field'], $f['Null'], $f['Key'], $f['Default'], $f['Extra']);
				$struc[$key] = $f;
			}
		}

		static::$tableStructureCache[$table] = $struc;
		return $struc;
	}

	public static function updateSqlData(array $data, mixed $table = false): array {
		$result = [];
		foreach ($data as $key => $value) {
			$allow = true;
			if (is_string($table)) {
				$allow = static::fieldExists($table, $key);
			}
			if ($allow) {
				$colKey = '`' . $key . '`';
				if (is_string($value)) {
					$result[$colKey] = "'" . static::clean_str($value) . "'";
				} elseif (is_numeric($value)) {
					$result[$colKey] = $value;
				} elseif (is_null($value)) {
					$result[$colKey] = 'NULL';
				}
			}
		}
		return $result;
	}

	public static function heavyWork(): \mysqli_result|bool {
		return static::query('SET SESSION wait_timeout = 1800');
	}

	public static function transaction(): \mysqli_result|bool {
		return static::query('START TRANSACTION');
	}

	public static function commit(): \mysqli_result|bool {
		return static::query('COMMIT');
	}

	public static function rollback(): \mysqli_result|bool {
		return static::query('ROLLBACK');
	}

	public static function clean_str(string $data): string {
		$entry = trim($data);
		$entry = strip_tags($entry);
		return addslashes($entry);
	}

	public static function fmtDate(mixed $dt): mixed {
		return \dates::fmtForMysql($dt);
	}

	public static function fmtEnumList(mixed $list): string {
		if (is_array($list) && !empty($list)) {
			$u = [];
			foreach ($list as $v) {
				$u[] = "'{$v}'";
			}
			return implode(', ', $u);
		}
		return "'NULL'";
	}

	public static function tableExists(mixed $table = false): bool {
		if ($table === false) {
			$table = $_REQUEST['table'] ?? false;
		}
		if (!$table) {
			return false;
		}

		if (isset(static::$tableExistsCache[$table])) {
			return static::$tableExistsCache[$table];
		}

		$r = static::assoc("SHOW TABLES LIKE '$table'");
		static::$tableExistsCache[$table] = (is_array($r) && !empty($r));
		return static::$tableExistsCache[$table];
	}

	public static function procedureExists(mixed $name = false): bool {
		if ($name === false) {
			$name = $_REQUEST['name'] ?? false;
		}
		if (!$name) {
			return false;
		}

		if (isset(static::$procedureExistsCache[$name])) {
			return static::$procedureExistsCache[$name];
		}

		$ie = static::$ignoreErrors;
		static::$ignoreErrors = true;
		$r = static::assoc("SHOW CREATE PROCEDURE $name");
		static::$ignoreErrors = $ie;

		static::$procedureExistsCache[$name] = (is_array($r) && !empty($r));
		return static::$procedureExistsCache[$name];
	}

	public static function fieldExists(mixed $table = false, mixed $field = false): bool {
		if ($table === false) {
			$table = $_REQUEST['table'] ?? false;
		}
		if ($field === false) {
			$field = $_REQUEST['field'] ?? false;
		}

		if (!$table || !$field) {
			return false;
		}

		if (isset(static::$fieldExistsCache[$table][$field])) {
			return static::$fieldExistsCache[$table][$field];
		}

		$result = static::tableExists($table);
		if ($result) {
			$row = static::assoc('Field', "SHOW COLUMNS FROM " . $table);
			if (is_array($row)) {
				$result = isset($row[$field]);
			}
		}

		static::$fieldExistsCache[$table][$field] = $result;
		return static::$fieldExistsCache[$table][$field];
	}
}
