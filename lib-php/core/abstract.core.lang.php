<?php
namespace core;
abstract class lang {
  static $code = false;
  static $default = 'ru';
  static function Error(){
    if (class_exists('\\errors')) {
      $args = func_get_args();
      $line = call_user_func_array(array('\lang','Text'),$args);
      return \errors::Add($line);
    }
  }
  static function sysLabel(){
    $args = func_get_args();
    array_unshift($args, 'labels');
    return call_user_func_array(array('\lang','Text'),$args);
  }
  static function Text(){
    if (!is_string(self::$code)) self::Init ();
		//return '';
    $args = func_get_args();
    $aaa = $args;
    $bb = $args;
    $lastKey = array_pop($bb);
    if (is_array($args) && !empty($args)) {
      $section = array_shift($args);
      $data = self::load($section);
      //\debug::outecho($section.': $data',$data);
      if (!empty($data)) {
        $res = -1;
        $tt = $data;
        foreach($args as $key){
          if (isset($tt[$key])) {
            if ($key == $lastKey) {
              $res = $tt[$key];
              //debug::outecho('$res',$res);
            }
            //if ($tt[$key] === false) $res = $tt[$key];
            //if (is_string($tt[$key])) $res = $tt[$key];
            if (is_array($tt[$key])) $tt = $tt[$key];
          } else {
            $tt = -1;
          }
        }
        if (is_string($res)) return $res;
        if ($res === false) return $res;
      }
    }
    return '['.implode('::',$aaa).']';
  }
  public static function getSection($section = false){
    //debug::outecho('rq',$_REQUEST);
    if (isset($_REQUEST['arguments']) && is_array($_REQUEST['arguments'])) {
      if (isset($_REQUEST['arguments']['section'])) {
        $section = $_REQUEST['arguments']['section'];
      }
    }
    if (!is_string(self::$code)) self::Init ();
    return self::load($section);
  }
  static function load($name = 'labels',$code = false){
    global $_ROOTFOLDERS;
    if (!is_string($code)) {
      $code = is_string(self::$code) ? self::$code : self::$default;
    }
    $ret = array();
    if (is_array($_ROOTFOLDERS) && !empty($_ROOTFOLDERS)) {
      foreach($_ROOTFOLDERS as $dir){
        $file = $dir.'/language/'.$code.'/'.$name.'.json';
        //\debug::outecho($file,  is_readable($file)?'yes':'no');
        if (is_readable($file)) {
          $arr = json_decode(file_get_contents($file),true);
          //\debug::outecho($file,  $arr);
          if (is_array($arr) && !empty($arr)) {
            $ret = array_merge_recursive($ret,$arr);
          }
        }
      }
    }
    return $ret;
  }
  public static function current(){
    if (!is_string(self::$code)) self::Init ();
    return self::$code;
  }
  static function Init(){
    $rr = array_merge($_GET,$_POST);
    if (isset($rr['lang']) && is_string($rr['lang'])) {
      self::$code = $rr['lang'];
      if (isset($_SESSION)) $_SESSION['language'] = self::$code;
    } elseif (isset($_SESSION) && isset($_SESSION['language'])) {
      self::$code = $_SESSION['language'];
    } else {
      self::$code = self::$default;
    }
    $info = self::load('info',self::$code);
    if (empty($info)) self::$code = self::$default;
  }
}
