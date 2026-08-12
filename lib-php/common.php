<?php
if (!isset($_ROOTFOLDERS) || !is_array($_ROOTFOLDERS)) {
  $_ROOTFOLDERS = array();
}
$_ROOTFOLDERS[] = dirname(__FILE__)."/";

if (!defined('SHOW_CLASS_INCLUDES')) define('SHOW_CLASS_INCLUDES',true);
function is_assoc($var) {
  return is_array($var) && array_diff_key($var,array_keys(array_keys($var)));
}
class SharedCommon {
  public static function auto_load($name) {
    global $_ROOTFOLDERS;
    if (class_exists($name,false)) return;
    if (function_exists('trait_exists')) {
      if (trait_exists($name,false)) return;
    }
    $includes = array();
    if (is_array($_ROOTFOLDERS) && !empty($_ROOTFOLDERS)) {
      foreach ($_ROOTFOLDERS as $DIR) {
        $arr = self::includeList($name,$DIR.'classes/');
        $includes = array_merge($includes,$arr);
        $arr = self::includeList($name,$DIR);
        $includes = array_merge($includes,$arr);
      }
    }
    $showautoload = isset($_REQUEST['showautoload']) && filter_var($_REQUEST['showautoload'], FILTER_VALIDATE_BOOLEAN);
    if ($showautoload) echo "<pre>".$name." : ".  print_r($includes,true)."</pre>";
    foreach($includes as $inc) {
      if(file_exists ($inc)) {
        //echo "<pre>".$name." : ".  print_r($inc,true)."</pre>";
        include_once $inc;
        if (class_exists($name,false)) return;
        if (function_exists('trait_exists')) {
          if (trait_exists($name,false)) return;
        }
      }
    }
    $hideautoload = isset($GLOBALS['HIDE_CLASS_INCLUDES']) && $GLOBALS['HIDE_CLASS_INCLUDES'] === true;
    if (SHOW_CLASS_INCLUDES && !$showautoload && !$hideautoload) echo "<pre>".$name." : ".  print_r($includes,true)."</pre>";
  }
  public static function includeList($name,$DIR) {
    if (class_exists($name))      return;
    $includes = array();
    $pname = str_replace('\\', '/', $name);
    $includes[] = $DIR. $pname .".php";
    $parts = explode('/',$pname);
    if (count($parts) > 1) {
      $pp = $parts;
      array_pop($pp);
    }
    if (count($parts) <= 1) {
      $includes[] = $DIR."class.". $pname .".php";
      $includes[] = $DIR."default.class.". $pname .".php";
      $includes[] = $DIR."class.abstract.". $pname .".php";
      $includes[] = $DIR."abstract.". $pname .".php";
      $includes[] = $DIR."trait.". $pname .".php";
      $includes[] = $DIR."". $pname .".class.php";
      $includes[] = $DIR."". $pname .".class.abstract.php";
      $includes[] = $DIR."". $pname .".abstract.php";
    } else {
      $pp = $parts;
      $cnm = array_pop($pp);
      $includes[] = $DIR.implode('/',$pp)."/".$cnm.".class.php";
      $includes[] = $DIR.implode('/',$pp)."/class.".$cnm.".php";
      $includes[] = $DIR.implode('/',$pp)."/abstract.".$cnm.".class.php";
      $includes[] = $DIR.implode('/',$pp)."/".$cnm.".php";
      $includes[] = $DIR.implode('/',$pp)."/abstract.".$cnm.".php";
      $includes[] = $DIR.implode('/',$pp)."/trait.".$cnm.".php";

      $includes[] = $DIR.implode('/',$pp)."/".implode('.',$parts).".php";
      $includes[] = $DIR.implode('/',$pp)."/abstract.".implode('.',$parts).".php";
      $includes[] = $DIR.implode('/',$pp)."/trait.".implode('.',$parts).".php";
      $includes[] = $DIR.implode('.',$parts).".php";

      $includes[] = $DIR."class.abstract.".implode('.',$parts).".php";
      $includes[] = $DIR."abstract.".implode('.',$parts).".php";
      $includes[] = $DIR."trait.".implode('.',$parts).".php";
      $includes[] = $DIR."class.".implode('.',$parts).".php";
      $includes[] = $DIR."default.class.".implode('.',$parts).".php";
    }
    return $includes;
  }
}


require_once dirname(__FILE__).'/Twig/Autoloader.php';
\Twig_Autoloader::register();

spl_autoload_register('SharedCommon::auto_load');
App::startup();