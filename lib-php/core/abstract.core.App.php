<?php
namespace core;
ob_start('ob_gzhandler');
abstract class App {
  static $debugMode = false;
  static $outMode = 'json';
  static $coreVersion = '0.1 beta';
  static function get(){
    return array(
      'coreVersion'=>static::$coreVersion,
      'lang'=>lang::current(),
      'authorized'=>session::authorized(),
      'labels'=>lang::load(),
    );
  }
  public static function set_timezone($value = ''){
    $debug = false && class_exists('debug') && \debug::$echo;
    if ($debug) \debug::echoGroup(__METHOD__.'( '. json_encode(func_get_args()).' )');
    if ($debug) \debug::outecho('$_REQUEST',$_REQUEST);
    if (isset($_REQUEST['_timezone']) && !empty($_REQUEST['_timezone'])) {
      $value = $_REQUEST['_timezone'];
    }
    $result = array('success'=>true);
    $result['timezone'] = \session::timezone($value);
    if ($debug) \debug::outecho('$_SESSION',$_SESSION);
    if ($debug) \debug::outecho('return',$result);
    if ($debug) \debug::echoGroupEnd();
    return $result;
  }
  public static function startup() {
    $outecho = ( isset($_REQUEST['outecho']) && filter_var($_REQUEST['outecho'], FILTER_VALIDATE_BOOLEAN) )
            || ( isset($_REQUEST['debug']) && filter_var($_REQUEST['debug'], FILTER_VALIDATE_BOOLEAN) );;
    self::$debugMode = $outecho;
    if(class_exists('debug')) {
      \debug::$echo = $outecho;
      \debug::$noOut = !$outecho;
    }
    if(class_exists('console')) {
      if (!defined('PHPDEBUG_MODE_OUTPUT')) define('PHPDEBUG_MODE_OUTPUT', $outecho);
    }
    if (!isset($_SESSION)) {
      //\debug::outecho('$_REQUEST',$_REQUEST);
      if (isset($_REQUEST['$SID'])) session_id ($_REQUEST['$SID']);
      session_start();
    }
    if ($outecho) {
      header("Content-Type: text/html; charset=utf-8");
      error_reporting(E_ALL | E_STRICT | E_DEPRECATED | E_USER_DEPRECATED);
      \debug::outecho($_SERVER['HTTP_HOST'],$_SERVER['SERVER_SOFTWARE'],'php '.phpversion(),\session::timezone());
      //\debug::outecho(' -- php '.  phpversion());
    } else {
      error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);
    }
    $debug = false && class_exists('debug') && \debug::$echo;
    if ($debug) \debug::echoGroup(__METHOD__.'( '. json_encode(func_get_args()).' )');
    if (isset($_REQUEST['json'])) {
      try {
        $decoded = json_decode(stripslashes($_REQUEST['json']),true);
        $_REQUEST = array_merge($_REQUEST,$decoded);
      } catch (Exception $ex) {}
      unset($_REQUEST['json']);
    }
    if (isset($_REQUEST['jsondata'])) {
      try {
        $decoded = json_decode(/*stripslashes*/($_REQUEST['jsondata']),true);
        $_REQUEST = array_merge($_REQUEST,$decoded);
      } catch (Exception $ex) {}
      unset($_REQUEST['jsondata']);
    }
    if (isset($_REQUEST['jsongzdata'])) {
      try {
        $decoded = json_decode(gzdecode(base64_decode($_REQUEST['jsongzdata'])),true);
        $_REQUEST = array_merge($_REQUEST,$decoded);
      } catch (Exception $ex) {}
      unset($_REQUEST['jsongzdata']);
    }

    $tz = \session::timezone();
    if (!empty($tz)) {
      date_default_timezone_set($tz);
    } else {
      date_default_timezone_set('Asia/Almaty'); 
    }
    if ($debug) \debug::outecho('timezone',date_default_timezone_get());
    ini_set('serialize_precision', '12');
    ini_set('magic_quotes_runtime', 0);
    ini_set('magic_quotes_gpc', 0);

    \SessionCache::checkTime();

    if (isset($_REQUEST['jsongz']) && filter_var($_REQUEST['jsongz'], FILTER_VALIDATE_BOOLEAN)){
      self::$outMode = 'jsongz';
    }
    if ($debug) \debug::echoGroupEnd();
  }
  public static function ajax($skipExixst = false) {
    $resultAjax = false;
    list($className,$method,$isStatic) = static::check_class_method_request($skipExixst);
    if (is_string($className)) {
      \debug::outecho('call',$className.($method?($isStatic?'::':'->').$method.'()':'').' -- php '.  phpversion());
      if (\debug::$echo && !\debug::$noOut) {
        echo "<title>".$className.($method?($isStatic?'::':'->').$method.'()':'')."</title>";
      }
      $resultAjax = true;
      $allowToUse = true;
      if ($allowToUse) {
        $result = array();
        if (is_string($method)) {
          if ($isStatic) {
            App::$debugTitle = "{$className}::{$method}()";
            $result = $className::$method();
          } else {
            App::$debugTitle = "{$className}->{$method}()";
            $class = new $className();
            $result = $class->$method();
          }
        } else {
          App::$debugTitle = "class $className";
          $result = new $className();
        }
      } else {
        $result = array('deny'=>true);
      }
      $r = array_merge($_GET,$_POST);
      unset($r['PHPSESSID']);
      unset($r['jNomad_SID']);
      unset($r['random']);
      unset($r['_ym_uid']);
      unset($r['jsongz']);
      //$r['outecho'] = 'yes';
      $r['debug'] = 'yes';
      $r['checksize'] = 'yes';
      $rURL = static::urlProtocol().$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.http_build_query($r);
      if (is_object($result)) {
        $result->{'$SID'} = session_id();
        if (!App::$debugMode) $result->{'debugURL'} = $rURL;
      } elseif (is_assoc($result) || (is_array($result) && empty($result))) {
        $result['$SID'] = session_id();
        if (!App::$debugMode) $result['debugURL'] = $rURL;
      }
      if (class_exists('\\errors') && \errors::Exists()) {
        \errors::Result($result);
      }
      if (class_exists('\\changes') && \changes::Exists()) {
        \changes::Result($result);
      }
      self::flush($result);
    }
    return $resultAjax;
  }
  static $debugTitle = 'App::flush($result)';
  static function flush($data = NULL){
    if (self::$debugMode) {
      //header("Content-Type: text/html; charset=utf-8");
      $checksize = isset($_REQUEST['checksize']) && filter_var($_REQUEST['checksize'], FILTER_VALIDATE_BOOLEAN);
      if(class_exists('debug')) {
        if ($checksize) {
          $n = self::encode($data);
          $p = gzencode($n,9);
          \debug::outecho (self::$debugTitle.' size',array(
            'naked'=>  strlen($n),
            'packed'=>  strlen($p),
            'ratio'=>  strlen($n)/strlen($p),
          ));
        }
        \debug::outecho (self::$debugTitle,$data);
      }
    } else {
      switch (static::$outMode){
        case 'direct':
          break;
        case 'jsongz':
          header("Content-Type: application/jsongz; charset=utf-8");
          echo gzencode(self::encode($data),9);
          break;
        default:
          header("Content-Type: application/json; charset=utf-8");
          echo self::encode($data);
          break;
      }
    }
  }
	static function encode($data){
		//$json = json_encode($data, JSON_UNESCAPED_UNICODE);
		//return $json;
		$json = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
		return \utils::json_indent($json);
	}
  static function urlServer($fullProtocol = false){
    if ($fullProtocol) {
      return static::urlProtocol().$_SERVER['HTTP_HOST'];
    } else {
      return '//'.$_SERVER['HTTP_HOST'];
    }
  }
  static function urlProtocol(){
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || 
              $_SERVER['SERVER_PORT'] == 443 || 
      (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') || 
      (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? "https://" : "http://";
    return $protocol;
  }
  static function check_class_method_request($skipExixst = false){
    $className = isset($_REQUEST['class']) ? $_REQUEST['class'] : '';
    if ($skipExixst) {
      $GLOBALS['HIDE_CLASS_INCLUDES'] = $GLOBALS['HIDE_CLASS_INCLUDES'] || $skipExixst;
    }
    if ($skipExixst && !empty($className) && !class_exists($className)) {
      $className = 'StdClass';
    }
    if (!class_exists($className)) {
      return array(false,false,false);
    }
    $method = isset($_REQUEST['method']) ? $_REQUEST['method'] : 'get';
    //\debug::outecho('$method',$method);
    $mlist = get_class_methods($className);
    //\debug::outecho('$mlist',$mlist);
    if (!in_array($method, $mlist) && !in_array('__callStatic', $mlist)) {
      return array($className,false,false);
    }
    $reflection = new \ReflectionClass($className);
    $isStatic = false;
    $slist = $reflection->getMethods(\ReflectionMethod::IS_STATIC);
    //\debug::outecho('$slist',$slist);
    if (is_array($slist) && !empty($slist)) foreach($slist as $row){
      if ($row->name == $method) $isStatic = true;
    }
		if (in_array('__callStatic', $mlist)) {
      $isStatic = true;
    }
		//\debug::outecho('$isStatic',$isStatic);
    return array($className,$method,$isStatic);
  }
}