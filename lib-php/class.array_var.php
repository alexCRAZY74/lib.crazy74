<?php
declare(strict_types=1);

class array_var {
	public static function get(mixed $target, string|int|array $key, mixed $default = null): mixed {
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
				return static::get($target, $kf, $default);
			}
			if (!isset($target[$kf])) {
				return $default;
			}
			return static::get($target[$kf], $kl, $default);
		}
		if (!is_string($key) && !is_int($key)) {
			return $default;
		}
		return $target[$key] ?? $default;
	}

	public static function set(mixed &$target, string|int|array $key, mixed $value, string $operand = '='): void {
		if (!is_array($target)) {
			$target = [];
		}
		if (is_array($key)) {
			if (count($key) === 1) {
				$key = reset($key);
			} else {
				$kl = $key;
				$kf = array_shift($kl);
				if (!isset($target[$kf]) || !is_array($target[$kf])) {
					$target[$kf] = [];
				}
				static::set($target[$kf], $kl, $value, $operand);
				return;
			}
		}
		$val = static::get($target, $key);
		$target[$key] = match ($operand) {
			'=' => $value,
			'+=' => ($val ?? 0) + $value,
			'-=' => ($val ?? 0) - $value,
			'*=' => ($val ?? 0) * $value,
			'/=' => ($val ?? 0) / $value,
			'.=' => (string)($val ?? '') . (string)$value,
			'|=' => ($val ?? 0) | $value,
			'&=' => ($val ?? 0) & $value,
			'^=' => ($val ?? 0) ^ $value,
			'%=' => ($val ?? 0) % $value,
			default => $value,
		};
	}

	public static function get_bool(mixed $target, string|int|array $key, bool $default = false): bool {
		$value = static::get($target, $key, $default);
		if (is_bool($value)) {
			return $value;
		}
		$filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		return $filtered ?? $default;
	}

	public static function get_array(mixed $target, string|int|array $key, mixed $default = []): mixed {
		$value = static::get($target, $key, $default);
		if (is_array($value) && !empty($value)) {
			return $value;
		}
		return $default;
	}
}
