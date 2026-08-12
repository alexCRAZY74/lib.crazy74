<?php
class console extends DebugHandler {
	public static function log(){
		$args = func_get_args();
		array_unshift($args , 'struc');
		return call_user_func_array(array(parent::class, 'echo'), $args);
	}
	public static function dump(){
		$args = func_get_args();
		array_unshift($args , 'dump');
		return call_user_func_array(array(parent::class, 'echo'), $args);
	}
	public static function text(){
		$args = func_get_args();
		array_unshift($args , 'textarea');
		return call_user_func_array(array(parent::class, 'echo'), $args);
	}
	public static function memory(){
		return call_user_func_array(array(parent::class, __FUNCTION__), func_get_args());
	}
	public static function trace(){
		return call_user_func_array(array(parent::class, __FUNCTION__), func_get_args());
	}
	public static function group(){
		return call_user_func_array(array(parent::class, __FUNCTION__), func_get_args());
	}
	public static function groupFunc(){
		return call_user_func_array(array(parent::class, __FUNCTION__), func_get_args());
	}
	public static function groupEnd(){
		return call_user_func_array(array(parent::class, __FUNCTION__), func_get_args());
	}
}
console::$skipfiles[] = __FILE__;