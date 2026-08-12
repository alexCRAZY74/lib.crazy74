<?php
abstract class staticStore {
  static $list = array();
  static function Exists() {
    return !empty(static::$list);
  }
  static function Get(){
    return static::$list;
  }
  static function Clear(){
    static::$list = array();
  }
  static $resultKey = '___S';
  static function Result(&$data){
    if (!empty(static::$list)) {
      $key = static::$resultKey;
      if (is_object($data)) $data->{$key} = static::$list;
      if (is_assoc($data)) $data[$key] = static::$list;
      if (is_array($data) && empty($data)) $data[$key] = static::$list;
    }
  }
}
