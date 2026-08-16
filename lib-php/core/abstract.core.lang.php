<?php

declare(strict_types=1);

namespace core;

use \console as console;

abstract class lang {

  public static string $code = '';
  public static string $default = 'ru';
	protected static string $sessionCacheKey = '__lang_cache';
	protected static string $sessionCodeKey = '__lang_code';

  public static function Error(mixed ...$args): mixed {
    if (class_exists('\\errors')) {
			array_unshift($args, 'errors');
      $line = static::Text(...$args);
      return \errors::Add($line);
    }
    return null;
  }

  public static function sysLabel(mixed ...$args): string|false {
    //оставляем пока для совместимости, чтобы не поломалось
    return static::Text(...$args);
  }

  public static function Text(mixed ...$args): string|false {
    if (static::$code === '') {
      static::Init();
    }

    if (empty($args)) {
      return '';
    }

    $originalArgs = $args;
    $section = array_shift($args);

    if (!is_string($section)) {
      return '[' . implode('::', array_map('strval', $originalArgs)) . ']';
    }

    $data = static::load($section);

    if (!empty($data)) {
      // Ищем нужный элемент по цепочке ключей с помощью array_var::get
      $res = \array_var::get($data, $args, false);

      if (is_string($res) || $res === false) {
        return $res;
      }
    }

    return '[' . implode('::', array_map('strval', $originalArgs)) . ']';
  }

  public static function getSection(string $section = 'labels'): array {
    $reqSection = \array_var::get($_REQUEST, ['arguments', 'section']);
    if (is_string($reqSection) && $reqSection !== '') {
      $section = $reqSection;
    }

    if (static::$code === '') {
      static::Init();
    }

    return static::load($section);
  }

  public static function load(string $name = 'labels', string $code = ''): array {
    if ($code === '') {
      $code = static::$code !== '' ? static::$code : static::$default;
    }

    if (!isset($_SESSION[static::$sessionCacheKey][$code])) {
      static::buildCache($code);
    }

    return (array) \array_var::get($_SESSION, [static::$sessionCacheKey, $code, $name], []);
  }

  public static function current(string|bool $newcode = false): string {
		if ($newcode !== false) {
			static::$code = $newcode;
      if (isset($_SESSION)) {
        $_SESSION[static::$sessionCodeKey] = static::$code;
      }
      static::Init();
		}
    if (static::$code === '') {
      static::Init();
    }
    return static::$code;
  }
	
	public static function Get(): mixed {
    if (static::$code === '') {
      static::Init();
    }
		return array(
			'langCode' => static::$code,
			'dictionary' => \array_var::get($_SESSION, [static::$sessionCacheKey, static::$code])
		);
	}

	public static function Init(): void {
    $reqLang = \array_var::get(array_merge($_GET, $_POST), 'lang');

    if (is_string($reqLang) && $reqLang !== '') {
      static::$code = $reqLang;
      if (isset($_SESSION)) {
        $_SESSION[static::$sessionCodeKey] = static::$code;
      }
    } else {
      static::$code = (string) \array_var::get($_SESSION, static::$sessionCodeKey, static::$default);
    }

    if (static::$code === '') {
      static::$code = static::$default;
    }

    if (!isset($_SESSION[static::$sessionCacheKey][static::$code])) {
      static::buildCache(static::$code);
    }

    // Проверка существования перевода (аналог старой проверки info)
    $info = \array_var::get($_SESSION, [static::$sessionCacheKey, static::$code, 'info']);
    if (empty($info)) {
      static::$code = static::$default;
      if (!isset($_SESSION[static::$sessionCacheKey][static::$code])) {
        static::buildCache(static::$code);
      }
    }
  }

  private static function buildCache(string $code): void {
    global $_ROOTFOLDERS;
		$debug = true;
		if ($debug) console::groupFunc();
    $cache = [];

    if (is_array($_ROOTFOLDERS) && !empty($_ROOTFOLDERS)) {
      // Разворачиваем массив, как ты и просил
      $folders = array_reverse($_ROOTFOLDERS);

      foreach ($folders as $dir) {
				if ($debug) console::group($dir);
        $langDir = rtrim((string) $dir, '/\\') . '/language/';
				if ($debug) console::log('$langDir', $langDir);

        if (is_dir($langDir) && is_readable($langDir)) {
          // Считываем все JSON-файлы в директории
          $files = glob($langDir . '*_'.$code.'.json');
					if ($debug) console::log('$files', $files);

          if (is_array($files)) {
            foreach ($files as $file) {
              $content = file_get_contents($file);

              if ($content !== false) {
                $arr = json_decode($content, true);
                if (is_array($arr) && !empty($arr)) {
									$cache = array_replace_recursive($cache, $arr);
                }
              }
            }
          }
        }
				if ($debug) console::groupEnd();
      }
    }

    $_SESSION[static::$sessionCacheKey][$code] = $cache;
		if ($debug) console::groupEnd();
  }

  public static function ClearCache(): void {
    if (isset($_SESSION[static::$sessionCacheKey])) {
      unset($_SESSION[static::$sessionCacheKey]);
    }
  }
}
