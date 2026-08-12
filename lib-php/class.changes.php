<?php
class changes extends staticStore {
  static $list = array();
  static function Add($gategory,$uid,$param = 0){
    if (!isset(static::$list[$gategory])) static::$list[$gategory] = array();
    if (!isset(static::$list[$gategory][$uid])) static::$list[$gategory][$uid] = array();
    static::$list[$gategory][$uid][] = $param;
  }
  static $resultKey = '_changes';
}
