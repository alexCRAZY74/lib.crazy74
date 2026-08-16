<?php

declare(strict_types=1);

class SessionCache {

  public static string $interval = '+2 minutes';

  public static function checkTime(): void {
    if (!isset($_SESSION)) {
      return;
    }

    $creation = (int) \array_var::get($_SESSION, ['cache', 'creation'], 0);
    if ($creation > 0) {
      $limit = strtotime(self::$interval, $creation);
      if (time() > $limit) {
        unset($_SESSION['cache']);
      }
    }

    if (!isset($_SESSION['cache'])) {
      $_SESSION['cache'] = ['creation' => time()];
    }
  }

  public static function clear(string|bool $key = false): void {
    if (!isset($_SESSION)) {
      return;
    }

    if ($key !== false && $key !== '') {
      if (isset($_SESSION['cache'][$key])) {
        unset($_SESSION['cache'][$key]);
      }
    } else {
      $_SESSION['cache'] = ['creation' => time()];
    }
  }

  public static function set(string $key, mixed $value): void {
    if (!isset($_SESSION)) {
      return;
    }

    if (!isset($_SESSION['cache'])) {
      $_SESSION['cache'] = ['creation' => time()];
    }

    $_SESSION['cache'][$key] = $value;
  }

  public static function check(mixed ...$args): array {
    $key = '';
    if (!empty($args)) {
      $key = implode('::', array_map('strval', $args));
    }

    if ($key === '') {
      $key = self::getKey();
    }

    $exist = false;
    $cachedata = null;

    if (isset($_SESSION['cache']) && array_key_exists($key, $_SESSION['cache'])) {
      $cachedata = $_SESSION['cache'][$key];
      $exist = true;
    }

    return [$exist, $cachedata, $key];
  }

  private static function getKey(): string {
    $db = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);
    $key = '';

    $frame2 = \array_var::get_array($db, 2);
    if (!empty($frame2)) {
      $class = (string) \array_var::get($frame2, 'class', '');
      $key .= $class !== '' ? $class . '_' : '_fn_';

      $func = (string) \array_var::get($frame2, 'function', '');
      if ($func !== '') {
        $key .= '_' . $func . '_';
      }
    }

    foreach ([1, 2] as $index) {
      $frameArgs = \array_var::get_array($db, [$index, 'args']);
      if (!empty($frameArgs)) {
        $scalarArgs = array_filter($frameArgs, 'is_scalar');
        $key .= implode('_', $scalarArgs);

        foreach ($frameArgs as $aa) {
          if (is_array($aa) && !empty($aa)) {
            $scalarSub = array_filter($aa, 'is_scalar');
            $key .= implode('_', $scalarSub);
          }
        }
      }
    }

    return $key;
  }
}
