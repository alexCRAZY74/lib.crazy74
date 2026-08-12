<?php
class request {
	public static function get($key, $default = NULL) {
		if (class_exists('array_var')) {
			return array_var::get($_REQUEST, $key, $default);
		}
		if (!isset($_REQUEST[$key])) {
			return $default;
		}
		return $_REQUEST[$key];
	}
	public static function filter($key, $filter = FILTER_DEFAULT, $options = 0) {
		return filter_var(static::get($key), $filter, $options);
	}
	public static function get_bool($key, $options = 0) {
		return static::filter($key, FILTER_VALIDATE_BOOLEAN, $options);
	}
}