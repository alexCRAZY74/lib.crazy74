<?php

declare(strict_types=1);

namespace REST;

use console;

class API {

	public ?object $http = null;
	protected static ?object $Instance = null;

	public static function __callStatic(string $name, array $arguments): mixed {
		$debug = false;
		$result = null;

		if ($debug) {
			console::groupFunc();
		}

		$class = static::class;
		if (!is_object(static::$Instance)) {
			static::$Instance = new $class();
		}

		if ($debug) {
			console::log('static::$Instance', static::$Instance);
		}

		if (is_object(static::$Instance->http)) {
			array_unshift($arguments, $name);
			if ($name === 'request') {
				array_shift($arguments);
			}

			if (method_exists(static::$Instance->http, 'request')) {
				$result = static::$Instance->http->request(...$arguments);
			}
		}

		if ($debug) {
			console::groupEnd();
		}

		return $result;
	}
}