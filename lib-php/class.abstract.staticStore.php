<?php

declare(strict_types=1);

abstract class staticStore {

  public static array $list = [];
  public static string $resultKey = '___S';

  public static function Exists(): bool {
    return !empty(static::$list);
  }

  public static function Get(): array {
    return static::$list;
  }

  public static function Clear(): void {
    static::$list = [];
  }

  public static function Result(mixed &$data): void {
    if (empty(static::$list)) {
      return;
    }

    $key = static::$resultKey;

    if (is_object($data)) {
      $data->{$key} = static::$list;
      return;
    }

    if (is_array($data)) {
      if (empty($data) || (function_exists('is_assoc') && is_assoc($data))) {
        $data[$key] = static::$list;
      }
    }
  }
}
