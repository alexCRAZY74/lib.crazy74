<?php

declare(strict_types=1);

use \console as console;

header('Cache-Control: max-age=0, no-store'); // Don't cache any html

if (!defined('TWIG_TEMPLATES_DIR')) {
  global $_ROOTFOLDERS;
  $tdir = $_ROOTFOLDERS[0] . "twig/";
  if (is_dir($tdir)) {
    define('TWIG_TEMPLATES_DIR', $tdir);
  } else {
    define('TWIG_TEMPLATES_DIR', $_ROOTFOLDERS[0]);
  }
}

#[\AllowDynamicProperties]
abstract class TwigPage {

  public static bool $isPublic = true;
  public static mixed $Instance = false;
  public bool $isInvalidPage = false;
  public string $title = '';
  public mixed $arguments = false;
  public string $clientTimezone = '';

  public function __construct() {
    $this->clientTimezone = \session::timezone();
  }

  public function Init(): void {
    
  }

  public static function templatenameVariants(): array {
    $class = get_called_class();
    $parts = explode('\\', $class);
    array_shift($parts);

    $baseName = implode(".", $parts);
    return [
        $baseName . ".twig",
        "page." . $baseName . ".twig",
        "public." . $baseName . ".twig",
        "private." . $baseName . ".twig",
        "admin." . $baseName . ".twig",
        "template." . $baseName . ".twig",
        "snippet." . $baseName . ".twig",
    ];
  }

  public static function Render(...$args): void {
    console::groupFunc();
    $class = get_called_class();
    $parts = explode('\\', $class);
    array_shift($parts);

    $pageName = static::templatenameVariants();
    console::log('$pageName', json_encode($pageName));

    $folders = static::dirList(TWIG_TEMPLATES_DIR);
    console::table('$folders', $folders);

    $file = false;
    try {
      $loader = new \Twig_Loader_Filesystem($folders);
      foreach ($pageName as $tt) {
        if ($file === false && $loader->exists($tt)) {
          $file = $tt;
        }
      }
    } catch (\Exception $ex) {
      
    }

    if ($file === false) {
      \errors::Add('twig template no exists (' . implode(", ", $pageName) . ')');
    }

    if (\App::$debugMode) {
      static::$Instance = new $class(...$args);
      static::$Instance->arguments = $args;
      static::$Instance->Init();
      \App::$debugTitle = $class;

      console::log('template', ($file !== false ? $file : json_encode($pageName)));

      if (\errors::Exists()) {
        \console::log('errors', \errors::Get());
        \errors::Result(static::$Instance);
      }

      console::groupEnd();
      \App::flush(static::$Instance);
      exit();
    }

    if (is_string($file)) {
      try {
        static::$Instance = new $class(...$args);
        static::$Instance->arguments = $args;
        static::$Instance->Init();

        $twig = new \Twig_Environment($loader, [
            'auto_reload' => true,
            'autoescape' => false,
        ]);

        foreach (get_class_methods($class) as $fn) {
          if (str_starts_with($fn, 'twig__')) {
            $realFn = "\\" . $class . "::" . $fn;
            $twigFn = substr($fn, 6);
            $twig->addFunction($twigFn, new \Twig_Function_Function($realFn));
          }
        }

        $template = $twig->loadTemplate($file);
        echo $template->render([
            'this' => static::$Instance,
        ]);
      } catch (\Exception $ex) {
        die('ERROR: ' . $ex->getMessage());
      }
    } else {
      $fo = console::$forcedOutput;
      console::$forcedOutput = true;
      console::groupFunc();
      console::log('$pageName', $pageName);
      console::log('$_GET', $_GET);
      console::groupEnd();
      console::$forcedOutput = $fo;
    }
  }

  public static function templateByClass(string $class): string {
    $parts = explode('\\', $class);
    array_shift($parts);
    return implode(".", $parts) . ".twig";
  }

  public static function twig__inset(...$args): string {
    if (empty($args))
      return '';

    $oldflag = \debug::$echo;
    $debug = in_array('debug', $args, true);
    $findclass = in_array('findclass', $args, true);

    if ($debug)
      \debug::$echo = true;
    \debug::$noOut = !\debug::$echo;

    if ($debug) {
      $argpl = [];
      foreach ($args as $v) {
        $argpl[] = "'{$v}'";
      }
      \debug::echoGroup("TwigPage::twig__inset( " . implode(', ', $argpl) . " )");
    }

    $name = array_shift($args);
    $class = "\\inset\\" . $name;
    $renderdata = [];
    $functions = [];
    $file = static::templateByClass($class);
    $result = "<strong>[[ need `{$file}` ]]</strong>";

    $hideautoload = isset($GLOBALS['HIDE_CLASS_INCLUDES']) && $GLOBALS['HIDE_CLASS_INCLUDES'] === true;
    $GLOBALS['HIDE_CLASS_INCLUDES'] = !($findclass && $debug);

    if ($debug)
      \debug::table('inset', [[
      '$class' => $class,
      '$file' => $file,
      '$findclass' => $findclass,
      'HIDE_CLASS_INCLUDES' => $GLOBALS['HIDE_CLASS_INCLUDES'],
      ]]);

    $renderdata['this'] = class_exists($class, true) ? new $class(...$args) : new \stdClass();
    $renderdata['this']->blockID = $name;
    $renderdata['this']->TitleLocale = \lang::Text('insets', 'titles', $name);
    $renderdata['this']->debug = $debug;

    if (class_exists($class)) {
      foreach (get_class_methods($class) as $fn) {
        if (str_starts_with($fn, 'twig__')) {
          $functions[substr($fn, 6)] = "\\" . $class . "::" . $fn;
        }
      }
    }

    $renderdata['this']->arguments = $args;

    if (is_object(static::$Instance)) {
      $renderdata['parent'] = static::$Instance;
      $pclass = get_class(static::$Instance);
      foreach (get_class_methods($pclass) as $fn) {
        if (str_starts_with($fn, 'twig__')) {
          $functions[substr($fn, 6)] = "\\" . $pclass . "::" . $fn;
        }
      }
    }

    if ($debug)
      \debug::outecho('functions', implode(', ', array_keys($functions)));
    if ($debug)
      \debug::outecho('$renderdata', $renderdata);

    try {
      $loader = new \Twig_Loader_Filesystem(static::dirList(TWIG_TEMPLATES_DIR));
      if (!$loader->exists($file) && class_exists($class)) {
        $parentClass = get_parent_class($renderdata['this']);
        if ($parentClass !== false) {
          $fileparent = static::templateByClass('\\' . $parentClass);
          if ($debug)
            \debug::outecho('$fileparent', $fileparent, $loader->exists($fileparent) ? 'exists' : 'lost');
          if ($loader->exists($fileparent))
            $file = $fileparent;
        }
      }

      if ($loader->exists($file)) {
        $twig = new \Twig_Environment($loader, [
            'auto_reload' => true,
            'autoescape' => false,
        ]);

        foreach ($functions as $twigFn => $realFn) {
          $twig->addFunction($twigFn, new \Twig_Function_Function($realFn));
        }

        $template = $twig->loadTemplate($file);
        $result = $template->render($renderdata);
      }
    } catch (\Exception $ex) {
      die('ERROR: ' . $ex->getMessage());
    }

    if ($debug)
      \debug::echoGroupEnd();
    \debug::$echo = $oldflag;
    \debug::$noOut = !\debug::$echo;
    $GLOBALS['HIDE_CLASS_INCLUDES'] = $hideautoload;

    return $result;
  }

  public static function twig__snippet(...$args): mixed {
    if (empty($args))
      return '';
    $name = array_shift($args);
    $class = "\\snippets\\" . $name;

    if (class_exists($class, true)) {
      return $class::Render(...$args);
    }

    return '{{[' . $class . '](' . implode(', ', array_map('strval', $args)) . ')}}';
  }

  public static function twig__headLinkFile(string $line): string {
    global $_ROOTFOLDERS;
    $file = $_ROOTFOLDERS[0] . $line;

    if (is_readable($file)) {
      $arr = explode('.', $file);
      $ext = strtolower((string) end($arr));
      $fileLine = $line . "?mtver=" . filemtime($file);

      switch ($ext) {
        case 'js':
          return "<script type='text/javascript' src='{$fileLine}'></script>";
        case 'css':
          return "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$fileLine}\" >";
        case 'less':
          return "<link rel=\"stylesheet/less\" type=\"text/css\" href=\"{$fileLine}\" >";
        default:
          return '<!-- ' . $fileLine . ' -->';
      }
    }

    return '<!-- ' . $line . ' -->';
  }

  public static function twig__debugOut(string $lbl, mixed $ID): void {
    echo '<pre style="border: 1px solid #d96500; color: #000; background:#f7f4e2; text-align:left; padding:4px;"><strong><span style="color:blue;">' . $lbl . "</span> :: </strong>"
    . htmlspecialchars(print_r($ID, true)) . "</pre>";
  }

  public static function twig__is_localhost(): bool {
    return str_contains($_SERVER['SERVER_NAME'] ?? '', 'localhost');
  }

  public static function twig__is_error(): bool {
    return \errors::Exists();
  }

  public static function twig__errors(): mixed {
    return \errors::Get();
  }

  public static function twig__empty(mixed $line): bool {
    return empty($line);
  }

  public static function twig__text(...$args): mixed {
    return \lang::Text(...$args);
  }

  public static function twig__jsonjs(mixed $data): string {
    $result = \App::encode($data);
    return str_replace(
            ["'", '\"', '\\\\', "\\t"],
            ["\'", '\u02ee', '\\\\\\\\', ' '],
            (string) $result
    );
  }

  public static function twig__sysLabel(...$args): mixed {
    return \lang::sysLabel(...$args);
  }

  public static function dirList(string $dir): array {
    $list = [];
    if (is_readable($dir) && is_dir($dir)) {
      $list[] = $dir;
      $sub = glob($dir . '*');
      if (is_array($sub) && !empty($sub)) {
        foreach ($sub as $ss) {
          $arr = self::dirList($ss . '/');
          $list = array_merge($list, $arr);
        }
      }
    }
    return $list;
  }
}
