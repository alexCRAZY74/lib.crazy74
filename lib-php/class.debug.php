<?php
declare(strict_types=1);

class debug extends DebugHandler {

  protected static string|false $filename = false;

  protected static function filePath(): string {
    if (static::$filename === false) {
      static::$filename = $_SERVER['DOCUMENT_ROOT'].'/company/warehouse/__debug.log';
    }
    return static::$filename;
  }

  public static function setFile(string $filename): bool {
    if ($filename !== '' && file_exists($filename)) {
      static::$filename = $filename;
      return true;
    }
    return false;
  }

  public static function log(string $name, mixed $var = null): void {
    $filename = static::filePath();
    if (file_exists($filename) && filesize($filename) >= (5 * 1024 * 1024)) {
      file_put_contents(
        $filename,
        '========================== Обрезано '.date('j F Y г. H:i:s').' =========================='
      );
    }
    $fp = fopen($filename, 'a+');
    if (!$fp) return;

    $trace = static::backtrace();
    $first = $trace[0] ?? array();
    $path = array();
    foreach ($trace as $r) {
      $path[] = (isset($r['line']) ? '['.$r['line'].']' : '')
        .(isset($r['class']) ? $r['class'].'::' : '')
        .($r['function'] ?? '');
    }

    fputs($fp, (isset($_SESSION['base']) ? $_SESSION['base'] : '').': ');
    fputs($fp, date('d.m.Y H:i:s').' ');
    fputs($fp, ($first['file'] ?? '').':'.($first['line'] ?? '')
      .(!empty($path) ? "\r\n".implode(' -> ', $path) : '').' {{'."\r\n");
    fputs($fp, '  '.$name);
    if (!empty($var)) {
      ob_start();
      var_export($var);
      $dump = ob_get_contents();
      ob_end_clean();
      $dump = str_replace("=> \n", '=>', $dump);
      fputs($fp, " :: «\r\n".$dump."\r\n»\r\n");
    } else {
      fputs($fp, "\r\n");
    }
    fputs($fp, '}}'."\r\n");
    fclose($fp);
  }
}
debug::$skipfiles[] = __FILE__;