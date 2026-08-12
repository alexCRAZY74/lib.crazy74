<?php
class array_var {
	public static function get($target, $key, $default = NULL) {
		if (!is_array($target)) {
			return $default;
		}
		if (is_array($key)) {
			if (empty($key)) {
				return $default;
			}
			$kl = $key;
			$kf = array_shift($kl);
			if (empty($kl)) {
				return static::get($target,$kf,$default);
			} else {
				if (!isset($target[$kf])) {
					return $default;
				}
				return static::get($target[$kf],$kl,$default);
			}
		}
		if (!isset($target[$key])) {
			return $default;
		}
		return $target[$key];
	}
	public static function set(&$target, $key, $value, $operand = '=') {
		if (!is_array($target)) {
			return;
		}
		if (is_array($key) && count($key) == 1) {
			$key = array_shift($key);
		}
		if (is_array($key)) {
			$kl = $key;
			$kf = array_shift($kl);
			if (!isset($target[$kf])) $target[$kf] = array();
			static::set($target[$kf], $kl, $value, $operand);
		} else {
			$val = static::get($target, $key);
			eval('$val '.$operand.' $value;');
			$target[$key] = $val;
		}
	}
	public static function get_bool($target, $key, $default = false) {
		$value = static::get($target, $key, $default);
		if (gettype($value) == 'boolean') {
			return $value;
		}
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}
	public static function get_array($target, $key, $default = false) {
		$value = static::get($target, $key, $default);
		if (is_array($value) && !empty($value)) {
			return $value;
		}
		return $default;
	}
}
