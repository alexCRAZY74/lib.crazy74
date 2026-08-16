<?php

declare(strict_types=1);

namespace core;

abstract class session {

  public static string $cookieKey = 'cRazersUniverseCK';

  public static function timezone(string $value = ''): string {
    if ($value === '') {
      return (string) \array_var::get($_SESSION, '__timezone', '');
    }

    $_SESSION['__timezone'] = $value;
    return $value;
  }

  public static function Clear(): void {
    if (isset($_SESSION['__timezone'])) {
      unset($_SESSION['__timezone']);
    }
    if (isset($_COOKIE[static::$cookieKey])) {
      setcookie(static::$cookieKey, '', time() - 10000, '/');
    }
  }

  public static function Create(array $row): void {
    $_SESSION['account'] = [
        'id' => (int) \array_var::get($row, 'id', 0),
        'isadmin' => \array_var::get_bool($row, 'isadmin', false),
    ];

    $tz = (string) \array_var::get($row, 'timezone', '');
    if ($tz !== '') {
      static::timezone($tz);
    }

    $tm = \dates::fmtForMysql();
    $upd = [
        'lastvisit' => $tm,
    ];

    if (isset($_COOKIE)) {
      $newKey = md5($tm . json_encode($row, JSON_UNESCAPED_UNICODE) . static::$cookieKey);
      $upd['cookie'] = $newKey;
      setcookie(static::$cookieKey, $newKey, time() + (3600 * 168), '/');
    }
  }

  public static function is_localhost(): bool {
    $serverName = (string) \array_var::get($_SERVER, 'SERVER_NAME', '');
    return str_contains($serverName, 'localhost');
  }
}
