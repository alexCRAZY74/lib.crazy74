<?php

declare(strict_types=1);

namespace core;

use \console as console;

abstract class lang {

  public static string $code = '';
  public static string $default = 'ru';
	protected static string $sessionCacheKey = '__lang_cache';
	protected static string $sessionCodeKey = '__lang_code';
	protected static string $sessionTimeKey = '__lang_mtime';
	protected static bool $keysInitialized = false;

	protected static function initKeys(): void {
		if (static::$keysInitialized) {
			return;
		}
		global $_ROOTFOLDERS;
		if (is_array($_ROOTFOLDERS) && !empty($_ROOTFOLDERS[0]) && is_string($_ROOTFOLDERS[0])) {
			$suffix = '_' . md5($_ROOTFOLDERS[0]);
			static::$sessionCacheKey .= $suffix;
			static::$sessionCodeKey .= $suffix;
			static::$sessionTimeKey .= $suffix;
		}
		static::$keysInitialized = true;
	}

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

		$result = \array_var::get($_SESSION, [static::$sessionCacheKey, static::$code, ...$args], false);

		if($result === false) {
			return '[' . implode('::', array_map('strval', $args)) . ']';
		}
		
		return $result;
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
    static::initKeys();
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
		static::initKeys();
		$debug = false;
		if ($debug) console::groupFunc();
		if ($debug) console::log('static::$sessionCodeKey', static::$sessionCodeKey);
		if ($debug) console::log('static::$sessionCacheKey', static::$sessionCacheKey);
    $reqLang = \array_var::get(array_merge($_GET, $_POST), 'lang');
		if ($debug) console::log('$reqLang', $reqLang);

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

    $currentMtime = static::getFilesMtime(static::$code);
    $cachedMtime = (int) \array_var::get($_SESSION, [static::$sessionTimeKey, static::$code], 0);

    if (!isset($_SESSION[static::$sessionCacheKey][static::$code]) || $currentMtime > $cachedMtime) {
      static::buildCache(static::$code, $currentMtime);
    }

    // Проверка существования перевода (аналог старой проверки info)
    /*$info = \array_var::get($_SESSION, [static::$sessionCacheKey, static::$code, 'info']);
    if (empty($info)) {
      static::$code = static::$default;
      if (!isset($_SESSION[static::$sessionCacheKey][static::$code])) {
        static::buildCache(static::$code);
      }
    }*/
		if ($debug) console::log('static::$code', static::$code);
		if ($debug) console::groupEnd();
  }

  protected static function getFilesMtime(string $code): int {
    global $_ROOTFOLDERS;
    $maxMtime = 0;
    if (is_array($_ROOTFOLDERS) && !empty($_ROOTFOLDERS)) {
      foreach ($_ROOTFOLDERS as $dir) {
        $langDir = rtrim((string) $dir, '/\\') . '/language/';
        if (is_dir($langDir) && is_readable($langDir)) {
          $files = glob($langDir . '*_' . $code . '.json');
          if (is_array($files)) {
            foreach ($files as $file) {
              $mtime = filemtime($file);
              if ($mtime !== false && $mtime > $maxMtime) {
                $maxMtime = $mtime;
              }
            }
          }
        }
      }
    }
    return $maxMtime;
  }

  private static function buildCache(string $code, int $mtime = 0): void {
    global $_ROOTFOLDERS;
		$debug = false;
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
    if ($mtime === 0) {
      $mtime = static::getFilesMtime($code);
    }
    $_SESSION[static::$sessionTimeKey][$code] = $mtime;
		if ($debug) console::groupEnd();
  }

  public static function ClearCache(): void {
    static::initKeys();
    if (isset($_SESSION[static::$sessionCacheKey])) {
      unset($_SESSION[static::$sessionCacheKey]);
    }
    if (isset($_SESSION[static::$sessionTimeKey])) {
      unset($_SESSION[static::$sessionTimeKey]);
    }
  }
}
