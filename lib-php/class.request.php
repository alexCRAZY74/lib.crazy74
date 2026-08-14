<?php
declare(strict_types=1);

class request {
	public static function get(string|array $key, mixed $default = null): mixed {
		return array_var::get($_REQUEST, $key, $default);
	}

	public static function filter(string|array $key, int $filter = FILTER_DEFAULT, int $options = 0): mixed {
		return filter_var(array_var::get($_REQUEST, $key), $filter, $options);
	}

	public static function get_bool(string|array $key, int $options = 0): bool {
		$value = static::filter($key, FILTER_VALIDATE_BOOLEAN, $options | FILTER_NULL_ON_FAILURE);
		return $value ?? false;
	}
}