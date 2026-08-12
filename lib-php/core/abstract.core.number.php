<?php
namespace core;
if (!defined('__Numder_Fix_Precision__')) define('__Numder_Fix_Precision__',0);
if (!defined('__Numder_Max_Precision__')) define('__Numder_Max_Precision__',4);
if (!defined('__Numder_Rounding_Threshold__')) define('__Numder_Rounding_Threshold__',10000);
abstract class number {
  static function smartFormat($value,$unitKey = false,$hideOne = false,$fix_precision = __Numder_Fix_Precision__,$max_precision = __Numder_Max_Precision__){
    $result = (float)$value;   
    $endings = false;
    $postfix = '';
    $precision = 0;
    
    if (is_string($unitKey)) {
      $section = \lang::getSection('numbers');
      if (is_array($section) && isset($section['units']) && is_array($section['units'])) {
        if (isset($section['units'][$unitKey]) && is_array($section['units'][$unitKey])) {
          $endings = $section['units'][$unitKey];
          $postfix = self::ending(round($result,$precision),$endings);
        }
      }
    }
    if (abs($result) >= __Numder_Rounding_Threshold__) {
      $result /= 1000;
      $postfix = ''.\lang::Text('numbers','postfix','kilo').(is_array($endings)?' '.$endings[2]:'');
      if (abs($result) >= 1000) {
        $result /= 1000;
        $postfix = ''.\lang::Text('numbers','postfix','mega').(is_array($endings)?' '.$endings[2]:'');
        if (abs($result) >= 1000) {
          $result /= 1000;
          $postfix = ''.\lang::Text('numbers','postfix','giga').(is_array($endings)?' '.$endings[2]:'');
        }
      }
    }
    $precision = 0;
    $n_value = round($result, $max_precision);
    $t_value = round($result, $precision);
    while ($t_value != $n_value && $precision < $max_precision) {
      $precision++;
      $t_value = round($result, $precision);
    }
    if ($fix_precision > 0) $precision = $fix_precision;
    $result = self::round($result,'0',' ',$fix_precision,$max_precision).$postfix;
    if (is_string($unitKey) && !is_array($endings)) $result .= ' '.\lang::Text('numbers','units',$unitKey);
    if ($hideOne && (float)$value == 1 && is_array($endings)) {
      $result = trim(self::ending(1,$endings));
    }
    return $result;
  }
  static function ending($num,$endings = array('штука', 'штуки', 'штук'), $null = ''){
    if ($num == 0) return $null;
    $sEnding = '';
    $iNumber = $num % 100;
    if ($iNumber>=11 && $iNumber<=19) {
      $sEnding=$endings[2];
    } else {
      switch($iNumber % 10) {
        case (1): $sEnding = $endings[0]; break;
        case (2):
        case (3):
        case (4): $sEnding = $endings[1]; break;
        default: $sEnding = $endings[2];
      }
    }
    return ' '.$sEnding;
  }
  static function round($lp_value,$replacer = '0',$thousands_sep = '',$fix_precision = 0,$max_precision = 4) {
    $ret = '';
    $value = $lp_value;
    //\debug::outecho('$value', $value);
    //\debug::outecho('$value', gettype($value));
    $precision = 0;
    if ($value !== '' && $value !== NULL) {
      $n_value = round($value, $max_precision);
      $t_value = round($value, $precision);
      while ($t_value != $n_value && $precision < $max_precision) {
        $precision++;
        $t_value = round($value, $precision);
      }
      if ($fix_precision > 0) $precision = $fix_precision;
      $ret = number_format($value, $precision, '.', '|');
      //$ret = $t_value;
    } else {
      $value = floatval($value);
      if ($fix_precision > 0) $precision = $fix_precision;
      $ret = ($replacer != '0')?$replacer:number_format($value, $precision, '.', '|');
    }
    //\debug::outecho('$ret', $ret);
    $ret = str_replace('|',$thousands_sep,$ret);
    return $ret;
  }
  public static function sizebytes($svalue){
    $end = 'b';
    $value = $svalue;
    if (abs($value) >= 1024) {
      $value /= 1024;
      $end = 'Kb';
      if (abs($value) >= 1024) {
        $value /= 1024;
        $end = 'Mb';
        if (abs($value) >= 1024) {
          $value /= 1024;
          $end = 'Gb';
        }
      }
    }
    return self::round($value,'0',' ',0,2).' '.$end;
  }
}