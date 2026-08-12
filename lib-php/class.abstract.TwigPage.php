<?php
header('Cache-Control: max-age=0, no-store'); // Don't cache any html
if (!defined('TWIG_TEMPLATES_DIR')) {
  $tdir = $_ROOTFOLDERS[0]."twig_templates/";
  if (is_dir($tdir)) {
    define('TWIG_TEMPLATES_DIR',$tdir);
  } else {
    define('TWIG_TEMPLATES_DIR',$_ROOTFOLDERS[0]);
  }
}
#[\AllowDynamicProperties]
abstract class TwigPage {
  static $isPublic = true;
  static $Instance = false;
  var $isInvalidPage = false;
  var $title = '';
  var $arguments = false;
  var $clientTimezone = '';
  function __construct(){
    $this->clientTimezone = \session::timezone();
  }
  public function Init() {
  }
  public static function templatenameVariants(){
    $class = get_called_class();
    //\debug::outecho('class',  $class);
    $parts = explode('\\',$class);
    array_shift($parts);
    //\debug::outecho('$parts',  $parts);
    $pageName = array();
    $pageName[] = implode(".", $parts).".twig";
    $pageName[] = "page.".implode(".", $parts).".twig";
    $pageName[] = "public.".implode(".", $parts).".twig";
    $pageName[] = "private.".implode(".", $parts).".twig";
    $pageName[] = "admin.".implode(".", $parts).".twig";
    $pageName[] = "template.".implode(".", $parts).".twig";
    $pageName[] = "snippet.".implode(".", $parts).".twig";
    return $pageName;
  }
  public static function Render(){
    \debug::echoGroup("TwigPage::Render()");
    $class = get_called_class();
    //\debug::outecho('class',  $class);
    $parts = explode('\\',$class);
    array_shift($parts);
    //\debug::outecho('$parts',  $parts);
    $pageName = static::templatenameVariants();
    //\debug::outecho('$pageName',  $pageName);
    $folders = static::dirList(TWIG_TEMPLATES_DIR);
    \debug::outecho('$folders',  $folders);
    $file = false;
    try {
      $loader = new \Twig_Loader_Filesystem($folders);
      foreach ($pageName as $tt){
        if ($file === false && $loader->exists($tt)) {
          $file = $tt;
        }
      }
    } catch (Exception $ex) {}
    if (\App::$debugMode) {
      static::$Instance = new $class(...func_get_args());
      static::$Instance->arguments = func_get_args();
      static::$Instance->Init();
      \App::$debugTitle = $class;
      \debug::outecho('template',  $file,$pageName);
      if (\errors::Exists()) {
        \debug::outecho('errors',  \errors::Get());
      }
      \App::flush(static::$Instance);
      \debug::echoGroupEnd();
      exit();
    }
    if (is_string($file)) {
      try {
        static::$Instance = new $class(...func_get_args());
        static::$Instance->arguments = func_get_args();
        static::$Instance->Init();
        $twig = new \Twig_Environment($loader, array(
          //'cache' => $dir['compilation_cache'],
          'auto_reload' => true,
          'autoescape' => false,
        ));
        foreach(get_class_methods($class) as $fn){
          if ( substr($fn, 0,6) == 'twig__' ) {
            $realFn = "\\".$class."::".$fn;
            //\debug::outecho('$realFn', $realFn);
            $twigFn = substr($fn, 6);
            //\debug::outecho('$twigFn', $twigFn);
            $twig->addFunction($twigFn,	new Twig_Function_Function($realFn));
          }
        }
        $template = $twig->loadTemplate($file);
        echo $template->render(array (
          'this' => static::$Instance,
          ));
      } catch (Exception $ex) {
        die ('ERROR: ' . $ex->getMessage());
      }
    } else {
      \debug::$echo = true;
      \debug::$noOut = !\debug::$echo;
      \debug::echoGroup("TwigPage::Render()");
      \debug::outecho('need',  $pageName);
      \debug::outecho('need',  $_GET);
      \debug::echoGroupEnd();
    }
  }
  static function templateByClass($class){
    $parts = explode('\\',$class);
    array_shift($parts);
    $file = implode(".", $parts).".twig";
    return $file;
  }
  static function twig__inset(){
    $args = func_get_args();
    if (empty($args)) return '';
    $result = '';
    $oldflag = \debug::$echo;
    $debug = in_array('debug', $args);
    $findclass = in_array('findclass', $args);
    if ($debug) \debug::$echo = true;
    \debug::$noOut = !\debug::$echo;
    if ($debug) {
      $argpl = array();
      foreach($args as $v) {
        $argpl[] = "'{$v}'";
      }
    }
    if ($debug) \debug::echoGroup("TwigPage::twig__inset( ".implode(', ', $argpl)." )");
    $name = array_shift($args);
    $class = "\\inset\\".$name;
    $renderdata = array();
    $functions = array();
    $file = static::templateByClass($class);
    $result = "<strong>[[ need `{$file}` ]]</strong>";
    $hideautoload = isset($GLOBALS['HIDE_CLASS_INCLUDES']) && $GLOBALS['HIDE_CLASS_INCLUDES'] === true;
    $GLOBALS['HIDE_CLASS_INCLUDES'] = !(bool)($findclass && $debug);
    if ($debug) \debug::table('inset',array(array(
      '$class'=>$class,
      '$file'=>$file,
      '$findclass'=>$findclass,
      'HIDE_CLASS_INCLUDES'=>$GLOBALS['HIDE_CLASS_INCLUDES'],
    )));
    $renderdata['this'] = class_exists($class,true) ? new $class(...$args) : new StdClass(...$args);
    $renderdata['this']->blockID = $name;
    $renderdata['this']->TitleLocale = \lang::Text('insets','titles',$name);
    $renderdata['this']->debug = $debug;
    if (class_exists($class)) {
      foreach(get_class_methods($class) as $fn){
        if ( substr($fn, 0,6) == 'twig__' ) {
          $functions[substr($fn, 6)] = "\\".$class."::".$fn;
        }
      }
    }
    $renderdata['this']->arguments = $args;
    if (is_object(static::$Instance)) {
      $renderdata['parent'] = static::$Instance;
      $pclass = get_class(static::$Instance);
      foreach(get_class_methods($pclass) as $fn){
        if ( substr($fn, 0,6) == 'twig__' ) {
          $functions[substr($fn, 6)] = "\\".$pclass."::".$fn;
        }
      }
    }
    if ($debug) \debug::outecho('functions',  implode(', ', array_keys($functions)));
    //if ($debug) \debug::outecho('functions',  array_keys($functions));
    if ($debug) \debug::outecho('$renderdata',  $renderdata);
    try {
      $loader = new \Twig_Loader_Filesystem(static::dirList(TWIG_TEMPLATES_DIR));
      if (!$loader->exists($file) && class_exists($class)) {
        $fileparent = static::templateByClass('\\'.get_parent_class($renderdata['this']));
        if ($debug) \debug::outecho('$fileparent', $fileparent, $loader->exists($fileparent)?'exists':'lost');
        if ($loader->exists($fileparent)) $file = $fileparent;
      }
      if ($loader->exists($file)) {
        $twig = new \Twig_Environment($loader, array(
          //'cache' => $dir['compilation_cache'],
          'auto_reload' => true,
          'autoescape' => false,
        ));
        foreach($functions as $twigFn=>$realFn){
          $twig->addFunction($twigFn,	new Twig_Function_Function($realFn));
        }
        $template = $twig->loadTemplate($file);
        $result = $template->render($renderdata);
      }
    } catch (Exception $ex) {
      die ('ERROR: ' . $ex->getMessage());
    }
    if ($debug) \debug::echoGroupEnd();
    \debug::$echo = $oldflag;
    \debug::$noOut = !\debug::$echo;
    $GLOBALS['HIDE_CLASS_INCLUDES'] = $hideautoload;
    return $result;
  }
  static function twig__snippet(){
    $args = func_get_args();
    $name = array_shift($args);
    $class = "\\snippets\\".$name;
    if (class_exists($class,true)) {
      return call_user_func_array(array($class,'Render'),$args);
      return $class::Render();
    }
    return '{{['.$class.']('.implode($args,', ').')}}';
  }
  static function twig__headLinkFile($line){
    global $_ROOTFOLDERS;
    $file = $_ROOTFOLDERS[0].$line;
    //\debug::outecho('$file',$file);
    if (is_readable($file)) {
      $arr = explode('.',$file);
      $ext = strtolower(end($arr));
      //\debug::outecho('$ext',$ext);
      $fileLine = $line."?mtver=".filemtime($file);
      switch ($ext) {
        case 'js':
          return "<script type='text/javascript' src='{$fileLine}'></script>";
          break;
        case 'css':
          return "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$fileLine}\" >";
          break;
        case 'less':
          return "<link rel=\"stylesheet/less\" type=\"text/css\" href=\"{$fileLine}\" >";
          break;
        default:
          return '<!-- '.$fileLine.' -->';
          break;
      }
    }
    return '<!-- '.$line.' -->';
  }
	static function twig__debugOut($lbl,$ID){
		echo '<pre  style="border: 1px solid #d96500; color: #000; background:#f7f4e2; text-align:left; padding:4px;"><strong><span style="color:blue;">'.$lbl."</span> :: </strong>"
		.htmlspecialchars(print_r($ID,true))."</pre>";
		//\debug::out($ID);
	}
  static function twig__is_localhost(){
    return gettype(strpos($_SERVER['SERVER_NAME'],'localhost')) == 'integer';
  }
  static function twig__is_error(){
    return \errors::Exists();
  }
  static function twig__errors(){
    return \errors::Get();
  }
  static function twig__empty($line){
    return empty($line);
  }
  static function twig__text(){
    $args = func_get_args();
    return call_user_func_array(array('\lang','Text'),$args);
  }
  static function twig__jsonjs($data){
    $result = \App::encode($data);
    return str_replace(
        array("'",'\"','\\'.'\\','\\'.'t'),
        array("\'",'\u02ee','\\'.'\\'.'\\'.'\\',' '),
        $result
      );
  }
  static function twig__sysLabel(){
    $args = func_get_args();
    return call_user_func_array(array('\lang','sysLabel'),$args);
  }
  static function dirList($dir){
    //\debug::$noOut = false;
    //\debug::outecho('$dir',  $dir);
    $list = array();
    if (is_readable($dir) && is_dir($dir)) {
      $list[] = $dir;
      $sub = glob($dir.'*');
      //\debug::outecho('$sub',  $sub);
      if (is_array($sub) && !empty($sub)) {
        foreach($sub as $ss){
          $arr = self::dirList($ss.'/');
          $list = array_merge($list,$arr);
        }
      }
    }
    return $list;
  }
}