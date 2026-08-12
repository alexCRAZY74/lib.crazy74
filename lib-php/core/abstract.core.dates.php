<?php
namespace core;
use \debug;
abstract class dates {
  static function fmtForMysql($dt = false) {
    if ($dt === false) return date( 'Y-m-d H:i:s');
    $dt = gettype($dt) == 'string'? strtotime($dt) : $dt;
    return date( 'Y-m-d H:i:s', $dt );
  }
  public static function FormatLocale($dt,$format = null,$withTime = true){
		if (empty($dt)) return '';
    $debug = false;
    $result = false;
    if ($debug) debug::echoGroup(__METHOD__.'( '. json_encode(func_get_args()).' )');
    $dt = gettype($dt) == 'string'? strtotime($dt) : $dt;
    $flist = \lang::load('dates');
    if ($debug) debug::outecho('$flist',$flist);
    if ($format == null || empty($format)) {
      $format = "j.m.Y";
      $fKey = $withTime ? 'locale_format_with_time' : 'locale_format';
      if (isset($flist[$fKey]) && !empty($flist[$fKey])) {
        $format = $flist[$fKey];
      }
    }
    if ($debug) debug::outecho('params', json_encode(array($dt,$format)));
    $date=explode(".", date("j.m.Y",$dt));
    if ($debug) debug::outecho('$date',$date);
    if (is_array($flist['monthsformat']) && !empty($flist['monthsformat'])) {
      $months = $flist['monthsformat'];
      if ($debug) debug::table('$months',$months);
      $key = $date[1];
      if (isset($months[$key]) && !empty($months[$key])) {
        $format = str_replace(array('F','M'),$months[$key],$format);
      }
    }
    if ($debug) debug::outecho('$format',$format);
    $result = date($format,$dt);
    if ($debug) debug::outecho('return',$result);
    if ($debug) debug::echoGroupEnd();
    return $result;
  }
  static function fmtRussian($dt,$format = 'j F Y г.'){
		if (empty($dt)) return '';
    $dt = gettype($dt) == 'string'? strtotime($dt) : $dt;
    $date=explode(".", date("j.m.Y",$dt));
    switch ($date[1]){
      case 1: $m = array('января','янв'); break;
      case 2: $m = array('февраля','фев'); break;
      case 3: $m = array('марта','мар'); break;
      case 4: $m = array('апреля','апр'); break;
      case 5: $m = array('мая','мая'); break;
      case 6: $m = array('июня','июня'); break;
      case 7: $m = array('июля','июля'); break;
      case 8: $m = array('августа','авг'); break;
      case 9: $m = array('сентября','сен'); break;
      case 10: $m = array('октября','окт'); break;
      case 11: $m = array('ноября','ноя'); break;
      case 12: $m = array('декабря','дек'); break;
    }
    $format = str_replace(array('F','M'),$m,$format);
    return date($format,$dt);
  }
  static function fmtSmart($dt,$withTime = true) {
    if ($dt == 'never') return \lang::Text('labels','neverdate');
		if (empty($dt)) return '';
    $dt = gettype($dt) == 'string'? strtotime($dt) : $dt;
    $date = self::FormatLocale($dt,null,false);
    if (date('Y-m-d',$dt) == date('Y-m-d')) $date = \lang::Text('labels','today');
    if (date('Y-m-d',$dt) == date('Y-m-d',strtotime('-2 days'))) $date = \lang::Text('labels','before_yesterday');
    if (date('Y-m-d',$dt) == date('Y-m-d',strtotime('-1 days'))) $date = \lang::Text('labels','yesterday');
    if (date('Y-m-d',$dt) == date('Y-m-d',strtotime('+1 days'))) $date = \lang::Text('labels','tomorrow');
    if (date('Y-m-d',$dt) == date('Y-m-d',strtotime('+2 days'))) $date = \lang::Text('labels','aftertomorrow');
    if ($withTime) {
      $tm = date('H:i',$dt);
      return $date.' '.$tm;
    }
    return $date;
  }
}