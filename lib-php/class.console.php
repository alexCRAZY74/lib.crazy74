<?php
declare(strict_types=1);

class console extends DebugHandler {
	public static function log(): mixed {
		$args = func_get_args();
		array_unshift($args , 'struc');
		return call_user_func_array(array(parent::class, 'echo'), $args);
	}
	public static function dump(): mixed {
		$args = func_get_args();
		array_unshift($args , 'dump');
		return call_user_func_array(array(parent::class, 'echo'), $args);
	}
	public static function text(): mixed {
		$args = func_get_args();
		array_unshift($args , 'textarea');
		return call_user_func_array(array(parent::class, 'echo'), $args);
	}
	public static function memory(): mixed {
		return call_user_func_array(array(parent::class, __FUNCTION__), func_get_args());
	}
	public static function trace(): mixed {
		return call_user_func_array(array(parent::class, __FUNCTION__), func_get_args());
	}
	public static function group(): mixed {
		return call_user_func_array(array(parent::class, __FUNCTION__), func_get_args());
	}
	public static function groupFunc(): mixed {
		return call_user_func_array(array(parent::class, __FUNCTION__), func_get_args());
	}
	public static function groupEnd(): mixed {
		return call_user_func_array(array(parent::class, __FUNCTION__), func_get_args());
	}
}
console::$skipfiles[] = __FILE__;