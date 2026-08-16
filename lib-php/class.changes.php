<?php

declare(strict_types=1);

class changes extends staticStore {

  public static array $list = [];
  public static string $resultKey = '_changes';

  public static function Add(string|int $category, string|int $uid, mixed $param = 0): void {
    static::$list[$category] ??= [];
    static::$list[$category][$uid] ??= [];
    static::$list[$category][$uid][] = $param;
  }
}
