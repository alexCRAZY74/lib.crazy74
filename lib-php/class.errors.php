<?php

declare(strict_types=1);

class errors extends staticStore {

  public static array $list = [];
  public static string $resultKey = 'Errors';

  public static function Add(mixed $line): mixed {
    static::$list[] = $line;
    return $line;
  }
}
