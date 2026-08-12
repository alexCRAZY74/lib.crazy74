<?php
namespace core;
abstract class session {
  static $cookieKey = 'cRazersUniverseCK';
  public static function timezone($value = '') {
    $result = '';
    if (empty($value)) {
      $result = $_SESSION['__timezone'];
    } else {
      $_SESSION['__timezone'] = $value;
      $result = $value;
    }
    return $result;
  }
  public static function authorized(){
    return (isset($_SESSION) && isset($_SESSION['account']) && !empty($_SESSION['account']));
  }
  public static function Clear(){
    if (isset($_SESSION) && isset($_SESSION['account'])) {
      unset($_SESSION['account']);
    }
    if (isset($_SESSION) && isset($_SESSION['__timezone'])) {
      unset($_SESSION['__timezone']);
    }
    if (is_string(static::$cookieKey) && isset($_COOKIE)) {
      setcookie(static::$cookieKey,"",time()-10000,"/");
    }
  }
  public static function Create($row){
    $_SESSION['account'] = array(
      'id'=>(int)$row['id'],
      'isadmin'=>(bool)$row['isadmin'],
    );
    if (isset($row['timezone']) && !empty($row['timezone'])) {
      static::timezone($row['timezone']);
    }
    $tm = \dates::fmtForMysql();
    $upd = array(
      'lastvisit'=>$tm
    );
    if (is_string(static::$cookieKey) && isset($_COOKIE)) {
      $newKey = md5($tm.json_encode($row).static::$cookieKey);
      $upd['cookie'] = $newKey;
      setcookie(static::$cookieKey,$newKey,time()+(3600*168),"/");
    }
    //\debug::outecho('$upd',$upd);
    if (\db_population::update(dbtableAccounts, (int)$row['id'], $upd)) {
      $_SESSION['account']['lastvisit'] = $tm;
      $user = new \Player($row['id']);
      //\debug::outecho('$user',$user);
      if (is_object($user) && $user->id != 0){
        $_SESSION['account']['character.id'] = $user->id;
        $_SESSION['account']['nick'] = $user->{'name.nick'};
        $_SESSION['account']['name'] = $user->{'name.viewed'};
        $_SESSION['account']['gender'] = $user->{'social.gender'};
        if ($user->{'social.pic.exists'}) {
          $_SESSION['account']['avatar'] = $user->{'social.pic.url'};
        }
      }
    } else {
      unset($_SESSION['account']);
    }
  }
  public static function checkCookie(){
    if (static::authorized() || !isset($_COOKIE) || empty($_COOKIE) || !isset($_COOKIE[static::$cookieKey])) return;
    //\debug::outecho('$_COOKIE',static::$cookieKey,$_COOKIE);
    //\debug::outecho('$_COOKIE',$_COOKIE);
    $row = \db_population::row("select * from `".dbtableAccounts."` where `cookie` = '{$_COOKIE[static::$cookieKey]}'");
    //\debug::outecho('$row',$row);
    if ($row !== false) static::Create($row);
  }
  public static function accountID(){
    if (isset($_SESSION)) {
      if (isset($_SESSION['account']) && isset($_SESSION['account']['id'])) {
        return $_SESSION['account']['id'];
      }
    }
    return false;
  }
  public static function playerID(){
    if (isset($_SESSION)) {
      if (isset($_SESSION['account']) && isset($_SESSION['account']['character.id'])) {
        return $_SESSION['account']['character.id'];
      }
    }
    return false;
  }
  public static function is_localhost(){
    return ( gettype(strpos($_SERVER['SERVER_NAME'],'localhost')) == 'integer' );
  }
}
