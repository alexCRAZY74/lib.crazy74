<?php
namespace core;
use \debug;
abstract class mysqli_db {
  static $debug = false;
	static $db_link = false;
	static $last_result = false;
  public static function query($sql){
    $debug = true && static::$debug;
    if ($debug) debug::echoGroup(__METHOD__.'()');
    if (self::is_inited()){
      //if ($debug) debug::outecho('static::$db_link',static::$db_link);
      if ($debug) debug::outecho('textarea','$sql',$sql);
      //echo "<div><pre>".print_r($sql,true)."</pre></div>";
      try {
        static::$last_result = static::$db_link->query($sql);
      } catch (Throwable $ex) {
        if ($debug) debug::outecho('$ex',$ex);
      }
      if (static::$last_result === false) {
        \debug::outecho('textarea','MySQL error '.static::$db_link->connect_errno." : ".static::$db_link->connect_error,$sql);
        \errors::Add('MySQL error '.static::$db_link->connect_errno." : ".static::$db_link->connect_error);
      }
      if (isset($GLOBALS['showSQL']) && $GLOBALS['showSQL'] === true) \debug::outecho('textarea','sql',$sql);
      if ($debug) debug::echoGroupEnd();
      return static::$last_result;
    }
    if ($debug) debug::echoGroupEnd();
    return false;
  }
  public static function connectData(){
    return array('localhost','root','12345','mysql');
  }

  public static function connect(){
    if (is_a(static::$db_link, 'mysqli')) {
      return static::$db_link;
    } else {
      if (class_exists('mysqli')) {
        ini_set('display_errors', 'Off');
        list($DB_SERVER, $DB_USERNAME, $DB_PASSWORD, $DB_DATABASE, $DB_PORT) = static::connectData();
        if (empty($DB_PORT)) $DB_PORT = ini_get("mysqli.default_port");
        //\debug::outecho('$DB_PORT',$DB_PORT, empty($DB_PORT));
        static::$db_link = new \mysqli($DB_SERVER, $DB_USERNAME, $DB_PASSWORD, $DB_DATABASE, $DB_PORT);
        ini_set('display_errors', 'On');
        if (static::$db_link->connect_errno) {
          //static::onErrorConnect(static::$db_link->connect_errno,static::$db_link->connect_error);
          \errors::Add('Connection MySQL error '.static::$db_link->connect_errno." : ".static::$db_link->connect_error);
          static::$db_link = false;
          return false;
        }
        \debug::outecho(get_called_class(), "`{$DB_SERVER}:{$DB_PORT}`.`{$DB_DATABASE}` @ ".static::row('v',"select version() as `v`"));
        return static::$db_link;
      } else {
        return false;
      }
    }
  }
  static $connectionInited = false;
  public static function init(){
    $result = false;
    if (is_a(static::$db_link, 'mysqli')) {
      if (!static::$connectionInited) {
        static::$db_link->query("SET NAMES utf8mb4");
        static::$db_link->query("SET `time_zone` = '".date('P')."'");
      }
      static::$connectionInited = true;
      $result = static::$connectionInited;
    }
    return $result;
  }
  public static function is_inited(){
    return (self::connect() && self::init());
  }
  static function insert($table,$data,$typeupdate = true){
    $result = false;
    self::is_inited();
    if (static::$db_link == false) return $result;
    if (!is_array($data) || empty($data)) return $result;
    $cmd = isset($data['id']) ? 'REPLACE' : 'INSERT';
    if ($typeupdate) $data = static::updateSqlData($data,$table);
    if (!empty($data)) {
      static::rowCacheClear($table);
      $keys = array();
      $values = array();
      foreach($data as $key=>$value){
        $keys[] = $key;
        $values[] = $value;
      }
      $sql = "$cmd INTO $table (".implode(', ', $keys).") VALUES (".implode(', ', $values).")";
      $success = static::query($sql);
      if ($success) {
        $result = static::$db_link->insert_id;
      }
    }
    return $result;
  }
  static function insertIgnore($table,$data,$typeupdate = true){
    $result = false;
    self::is_inited();
    if (static::$db_link == false) return $result;
    if (!is_array($data) || empty($data)) return $result;
    if ($typeupdate) $data = static::updateSqlData($data,$table);
    if (!empty($data)) {
      static::rowCacheClear($table);
      $keys = array();
      $values = array();
      foreach($data as $key=>$value){
        $keys[] = $key;
        $values[] = $value;
      }
      $sql = "INSERT IGNORE INTO $table (".implode($keys,', ').") VALUES (".implode($values,', ').")";
      $success = static::query($sql);
      if ($success) {
        $result = static::$db_link->insert_id;
      }
    }
    return $result;
  }
  static function replace($table,$data,$typeupdate = true){
    $result = false;
    self::is_inited();
    if (static::$db_link == false) return $result;
    if (!is_array($data) || empty($data)) return $result;
    if ($typeupdate) $data = static::updateSqlData($data,$table);
    if (!empty($data)) {
      static::rowCacheClear($table);
      $keys = array();
      $values = array();
      foreach($data as $key=>$value){
        $keys[] = $key;
        $values[] = $value;
      }
      $sql = "REPLACE INTO $table (".implode(', ', $keys).") VALUES (".implode(', ', $values).")";
      $success = static::query($sql);
      if ($success) {
        $result = static::$db_link->insert_id;
      }
      //\debug::outecho('textarea','$sql',$sql);
    }
    return $result;
  }
  static function update_sql($table,$id,$data,$typeupdate = true){
    $result = false;
    self::is_inited();
    if (static::$db_link == false) return $result;
    if (!is_array($data) || empty($data)) return $result;
    if ($typeupdate) $data = static::updateSqlData($data,$table);
    if (!empty($data)) {
      $values = array();
      $where = array();
      if (is_array($id)) {
        static::rowCacheClear($table);
        $where = $id;
      } else {
        static::rowCacheClear($table,$id);
				if (empty($where) && static::fieldExists($table,'guid') && is_string($id)) {
					$where[] = "`guid` = ".(is_string($id)?"'$id'":$id);
				}
				if (empty($where) && static::fieldExists($table,'id')) {
					$where[] = "`id` = ".(is_string($id)?"'$id'":$id);
				}
      }
      foreach($data as $key=>$value){
        $values[] = "$key = $value";
      }
      $sql = "UPDATE $table SET ".implode(",  \r\n",$values)."\r\n";
      if (!empty($where)) $sql .= "WHERE ".implode(' AND ',$where);
      //\debug::log('$sql',$sql);
      $result = $sql;
    }
    return $result;
  }
  static function update($table,$id,$data,$typeupdate = true){
    $sql = self::update_sql($table, $id, $data,$typeupdate);
    //\debug::outecho('a',$sql);
    if (is_string($sql)) {
      return static::query($sql);
    }
    return false;
  }
  static function assoc($param1, $param2 = false){
    $sql = $param2 === false ? $param1 : $param2;
    $key = $param2 === false ? false : $param1;
    $result = false;
    $query = static::query($sql);
    if ($query && $query->num_rows > 0) {
      $result = array();
      while ($row = $query->fetch_assoc()) {
        if (is_string($key) && !empty($key)) {
          $result[$row[$key]] = $row;
        } else {
          $result[] = $row;
        }
      }
    }
    if($query) $query->close();
    return $result;
  }
  static function id_list($table,$where = array(),$idField = 'id'){
    $result = false;
    $sql = "select distinct `$idField` from `$table`".(empty($where)?'':' where '. implode(' and ', $where));
    $query = static::query($sql);
    if ($query && $query->num_rows > 0) {
      $struc = static::tableKeysTypes($table);
      //\debug::outecho('$struc',$struc);
      $result = array();
      while ($row = $query->fetch_assoc()) {
        if (isset($idField) && is_string($idField) && !empty($idField)) {
          if (isset($struc[$idField])) {
            //\debug::outecho('$struc',$struc);
            settype($row[$idField], $struc[$idField]);
          }
          $result[] = $row[$idField];
        }
      }
      if (empty($result)) {
        $result = false;
      }
    }
    if($query) $query->close();
    //\debug::outecho('$result',$result);
    return $result;
  }
  static function get_array($param1, $param2 = false, $typekey = 'string'){
    $sql = $param2 === false ? $param1 : $param2;
    $key = $param2 === false ? false : $param1;
    $result = false;
    $query = static::query($sql);
    if ($query && $query->num_rows > 0) {
      $result = array();
      while ($row = $query->fetch_assoc()) {
        if (isset($key) && is_string($key) && !empty($key)) {
          settype($row[$key], $typekey);
          $result[] = $row[$key];
        } else {
          $result[] = $row;
        }
      }
    }
    if($query) $query->close();
    //\debug::outecho('$result',$result);
    return $result;
  }
  static function row($param1, $param2 = false){
    $sql = $param2 === false ? $param1 : $param2;
    $key = $param2 === false ? false : $param1;
    $result = false;
    $query = static::query($sql);
    if ($query && $query->num_rows > 0) {
      $result = $query->fetch_assoc();
      if (is_string($key) && !empty($key)) {
        $result = $result[$key];
      }
    }
    if($query) $query->close();
    return $result;
  }
  static $rowCache = array();
  static function rowCacheStore($table,$id,$row){
    $cid = serialize($id);
    if (!isset(static::$rowCache[$table])) {
      static::$rowCache[$table] = array();
    }
    static::$rowCache[$table][$cid] = $row;
  }
  static function rowCacheGet($table,$id){
    $cid = serialize($id);
    if (isset(static::$rowCache[$table]) && isset(static::$rowCache[$table][$cid])) {
      return static::$rowCache[$table][$cid];
    }
    return null;
  }
  static function rowCacheClear($table = null,$id = null){
    $cid = serialize($id);
    if (is_null($table)) {
      static::$rowCache = array();
      return;
    }
    if (isset(static::$rowCache[$table])) {
      if (is_null($id)) {
        unset(static::$rowCache[$table]);
      } elseif (isset(static::$rowCache[$table][$cid])) {
        unset(static::$rowCache[$table][$cid]);
      }
    }
  }
  static function fullRow($table,$id,$select = array('*')) {
		if (is_array($id)) {
			$cacheID = implode('_', $id);
		} else {
			$cacheID = $id;
		}
    $cacheID .= '_'. implode('_', $select);
    $cache = static::rowCacheGet($table, $cacheID);
    if (is_array($cache)) return $cache;
    $row = static::tablerow($table,$id,$select);
    static::rowCacheStore($table,$cacheID,$row);
    return $row;
  }
  static function tablerow($table,$id,$select = array('*')) {
    $where = array();
    if (is_array($id)) {
      $where = $id;
    } else {
			if (empty($where) && static::fieldExists($table,'guid') && is_string($id)) {
				$where[] = "`guid` = ".(is_string($id)?"'$id'":$id);
			}
			if (empty($where) && static::fieldExists($table,'id')) {
				$where[] = "`id` = ".(is_string($id)?"'$id'":$id);
			}
    }
		if (empty($where)) {
			return false;
		}
    return static::row("SELECT ".implode(', ',$select)." FROM `".addslashes($table)."` WHERE ".implode(' AND ',$where));
  }
  protected static function AssignRowValue(&$object,$table,$key,$value,$type = 'unknown') {
    $legalset = array('boolean','integer','float','double');
    $temp = array();
    if (in_array($type, $legalset)) {
      settype($value, $type);
    }
    switch ($type) {
      case 'datetime':
        $temp[$key] = $value;
        $temp[$key.".text"] = \dates::fmtSmart($value);
        break;
      default:
        $temp[$key] = $value;
        break;
    }
    //\debug::outecho('AssignRowValue $temp',$temp);
    if (!empty($temp)) {
      foreach($temp as $k=>$v) {
        if (is_object($object)) $object->{$k} = $v;
        if (is_assoc($object) || (is_array($object) && empty($object))) {
          $object[$k] = $v;
        }
      }
    }
  }
  public static function AssignRow(&$object,$table,$param,$prefix = ""){
    $row = false;
    $result = false;
    if (is_assoc($param)) {
      $row = $param;
    } else {
      $row = static::fullRow($table,$param);
    }
    if (is_assoc($row) && is_string($table)) {
      $result = true;
      //\debug::outecho('$row',$row);
      foreach ($row as $key=>$value) {
				if (is_string($value)) {
					$value = stripslashes($value);
				}
        static::AssignRowValue($object, $table, $prefix.$key, $value,static::tableKeyType($table,$key));
      }
    }
    return $result;
  }
  public static function tableKeyType($table,$field){
    $result = 'unknown';
    $struc = static::tableKeysTypes($table);
    if (isset($struc[$field])) {
      $result = $struc[$field];
    }
    return $result;
  }
  public static function tableKeysTypes($table){
    $debug = false;
    if ($debug) \debug::echoGroup(get_called_class()."::tableKeysTypes({$table})");
    $struc = array();
    $row = static::tableStructure($table);
    if ($debug) \debug::outecho('$row',$row);
    foreach ($row as $key=>$f) {
      $type = isset($f['Type']) ? strtolower($f['Type']) : 'loss';
      if ($type == 'datetime' || $type == 'timestamp' || substr($type, 0,9) == 'timestamp' ){
        $struc[$key] = 'datetime';
      } elseif (substr($type, 0,10) == 'tinyint(1)'){
        $struc[$key] = 'boolean';
      } elseif (substr($type, 0,3) == 'int' || substr($type, 0,7) == 'tinyint' || substr($type, 0,8) == 'smallint' || substr($type, 0,6) == 'bigint'){
        $struc[$key] = 'integer';
      } elseif (substr($type, 0,5) == 'float' || substr($type, 0,6) == 'double' || substr($type, 0,7) == 'decimal'){
        $struc[$key] = 'float';
        //\debug::outecho($key,$f);
      } else {
        //\debug::outecho($key,$type);
        $struc[$key] = 'string';
      }
    }
    if ($debug) \debug::outecho('$struc',$struc);
    if ($debug) \debug::echoGroupEnd();
    return $struc;
  }
  static $tableStructureCache = array();
  public static function tableStructure($table){
    if (isset(self::$tableStructureCache[$table])) return self::$tableStructureCache[$table];
    $struc = array();
    $row = static::assoc('Field',"SHOW COLUMNS FROM ".$table."");
    //\debug::outecho('$row',$row);
    foreach ($row as $key=>$f) {
      unset($f['Field']);
      unset($f['Null']);
      unset($f['Key']);
      unset($f['Default']);
      unset($f['Extra']);
      $struc[$key] = $f;
    }
    self::$tableStructureCache[$table] = $struc;
    return $struc;
  }
  static function updateSqlData($data,$table = false){
    $result = array();
    foreach($data as $key=>$value){
      $allow = true;
      if (is_string($table)) {
        $allow = static::fieldExists($table,$key);
      }
      if ($allow) {
        $key = '`'.$key.'`';
        if (is_string($value)) $result[$key] = "'".static::clean_str($value)."'";
        if (is_numeric($value)) $result[$key] = $value;
        if (is_null($value)) $result[$key] = 'NULL';
      }
    }
    return $result;
  }
  static function heavyWork(){
    static::query('SET SESSION wait_timeout = 1800'); 
  }
  static function transaction(){
    return self::query('START TRANSACTION');
  }
  static function commit(){
    return self::query('COMMIT');
  }
  static function rollback(){
    return self::query('ROLLBACK');
  }
  static function clean_str($data) {
    $entry = trim($data);
    $entry = strip_tags($entry);
    $entry = addslashes($entry);
    return $entry; 
  }
  static function fmtDate($dt) {
    return dates::fmtForMysql($dt);
  }
  public static function fmtEnumList($list){
    if (is_array($list) && !empty($list)) {
      $u = array();
      foreach($list as $v) {
        $u[] = "'{$v}'";
      }
      return implode(', ', $u);
    } else {
      return "'NULL'";
    }
  }
  static $tableExistsCache = array();
	static function tableExists($table = false){
		if ($table === false) $table = $_REQUEST['table'];
    if (isset(static::$tableExistsCache[$table])) return static::$tableExistsCache[$table];
		$r = self::assoc("SHOW TABLES LIKE '$table'");
		static::$tableExistsCache[$table] = (is_array($r) && !empty($r));
    return static::$tableExistsCache[$table];
	}
  static $procedureExistsCache = array();
	static function procedureExists($name = false){
		if ($name === false) $name = $_REQUEST['name'];
    if (isset(static::$procedureExistsCache[$name])) return static::$procedureExistsCache[$name];
    $ie = static::$ignoreErrors;
    static::$ignoreErrors = true;
		$r = self::assoc("show create procedure $name");
    static::$ignoreErrors = $ie;
		static::$procedureExistsCache[$name] = (is_array($r) && !empty($r));
    return static::$procedureExistsCache[$name];
	}
  static $fieldExistsCache = array();
	static function fieldExists($table = false,$field = false){
		if ($table === false) $table = $_REQUEST['table'];
		if ($field === false) $field = $_REQUEST['field'];
    if (isset(static::$fieldExistsCache[$table][$field])) {
      return static::$fieldExistsCache[$table][$field];
    }
		$result = static::tableExists($table);
    if ($result) {
      $row = static::assoc('Field',"SHOW COLUMNS FROM ".$table."");
      if (is_array($row)) {
        $result = isset($row[$field]);
      }
    }
    static::$fieldExistsCache[$table][$field] = $result;
		return static::$fieldExistsCache[$table][$field];
	}
}
