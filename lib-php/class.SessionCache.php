<?php
class SessionCache {
  static $interval = '+2 minutes';
  public static function checkTime(){
    if (isset($_SESSION)) {
      if (isset($_SESSION['cache'])) {
        $limit = strtotime(self::$interval,( (int)$_SESSION['cache']['creation'] ) );
        $current = strtotime('now');
        if ($current > $limit) {
          unset($_SESSION['cache']);
        }
      }
      //unset($_SESSION['cache']);
      if (!isset($_SESSION['cache'])) {
        $_SESSION['cache'] = array('creation'=>strtotime('now'));
      }
    }
  }
  public static function clear($key = false){
    if (isset($_SESSION)) {
      if ($key !== false) {
        if (isset($_SESSION['cache']) && isset($_SESSION['cache'][$key])) unset($_SESSION['cache'][$key]);
      } else {
        $_SESSION['cache'] = array('creation'=>strtotime('now'));
      }
    }
  }
  public static function set($key,$value){
    if (isset($_SESSION)) {
      if (!isset($_SESSION['cache'])) {
        $_SESSION['cache'] = array('creation'=>strtotime('now'));
      }
      $_SESSION['cache'][$key] = $value;
    }
  }
  public static function check(){
    $exist = false;
    $cachedata = false;
    $args = func_get_args();
    if (!empty($args)) $key = implode ('::', $args);
    if (!is_string($key) || empty($key)) $key = self::getKey();
    if (isset($_SESSION)) {
      if (isset($_SESSION['cache'][$key])) {
        $cachedata = $_SESSION['cache'][$key];
        $exist = true;
      }
    }
    //debug::outecho('$key',$key);
    return array($exist,$cachedata,$key);
  }
  private static function getKey(){
    $key = '';
    $db = debug_backtrace();
    //debug::outecho('$db',$db);
    if (isset($db[2])) {
      if (isset($db[2]['class'])) {
        $key .= $db[2]['class'].'_';
      } else {
        $key .= '_fn_';
      }
      if (isset($db[2]['function'])) {
        $key .= '_'.$db[2]['function'].'_';
      }
    }
    if (isset($db[1]) && isset($db[1]['args']) && is_array($db[1]['args']) && !empty($db[1]['args'])) {
      $key .= implode($db[1]['args'],'_');
      foreach ($db[1]['args'] as $aa) {
        if (is_array($aa) && !empty($aa)) $key .= implode($aa,'_');
      }
    } 
    if (isset($db[2]) && isset($db[2]['args']) && is_array($db[2]['args']) && !empty($db[2]['args'])) {
      $key .= implode($db[2]['args'],'_');
      foreach ($db[2]['args'] as $aa) {
        if (is_array($aa) && !empty($aa)) $key .= implode($aa,'_');
      }
    }
    return $key;
  }
}
