<?php
class errors extends staticStore {
  static $list = array();
  static function Add($line){
    static::$list[] = $line;
    return $line;
  }
  static $resultKey = 'Errors';
}
