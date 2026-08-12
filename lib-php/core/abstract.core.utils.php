<?php
namespace core;
abstract class utils {
	public static function removeBOM($str="") {
    if(substr($str, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
        $str = substr($str, 3);
    }
    return $str;
  }
	public static function get_encoding($str){
		$cp_list = array('UTF-8', 'CP1251');
		foreach ($cp_list as $k=>$codepage){
				if (md5($str) === md5(iconv($codepage, $codepage, $str))){
						return $codepage;
				}
		}
		return 'UTF-8';
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
    return numbers::round($value,'0',' ',0,2).' '.$end;
  }
	public static function to_utf8($str) {
		return iconv(self::get_encoding($str), "UTF-8", $str);
	}
	public static function to_CP1251($str) {
		return iconv(self::get_encoding($str), "CP1251", $str);
	}
	public static function rus_bool($data,$FILTER_NULL_ON_FAILURE = false) {
		$data = sql_exec::clean_str($data);
    $result = NULL;
		switch ($data) {
			case 'ДА' : case 'Да': case 'да': case 'YES' : case 'Yes': case 'yes': case 1 : $result = 'yes'; break;
			case 'НЕТ' : case 'Нет': case 'нет': case 'NO' : case 'No': case 'no': case 0 : $result = 'no'; break;
		}
		if ($FILTER_NULL_ON_FAILURE) {
			return filter_var($result, FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
		} else {
			return filter_var($result, FILTER_VALIDATE_BOOLEAN);
		}
  }
	public static function getGUID() {
		if (function_exists('com_create_guid')) {
			return com_create_guid();
		} else {
			mt_srand(round((double) microtime() * 10000)); //optional for php 4.2.0 and up.
			$charid = strtoupper(md5(uniqid(rand(), true)));
			$hyphen = chr(45); // "-"
			$uuid = chr(123)// "{"
				. substr($charid, 0, 8) . $hyphen
				. substr($charid, 8, 4) . $hyphen
				. substr($charid, 12, 4) . $hyphen
				. substr($charid, 16, 4) . $hyphen
				. substr($charid, 20, 12)
				. chr(125); // "}"
			return $uuid;
		}
	}

	public static function array_diff($array1, $array2, $strict = false) {
		if (!is_array($array1)) {
			throw new \InvalidArgumentException('$array1 must be an array!');
		}

		if (!is_array($array2)) {
			return $array1;
		}

		$result = array();

		foreach ($array1 as $key => $value) {
			if (!array_key_exists($key, $array2)) {
				$result[$key] = $value;
				continue;
			}

			if (is_array($value) && count($value) > 0) {
				$recursiveArrayDiff = static::array_diff($value, $array2[$key], $strict);

				if (count($recursiveArrayDiff) > 0) {
					$result[$key] = $recursiveArrayDiff;
				}

				continue;
			}

			$value1 = $value;
			$value2 = $array2[$key];

			if ($strict ? is_float($value1) && is_float($value2) : is_float($value1) || is_float($value2)) {
				$value1 = (string) $value1;
				$value2 = (string) $value2;
			}

			if ($strict ? $value1 !== $value2 : $value1 != $value2) {
				$result[$key] = $value;
			}
		}

		return $result;
	}
	public static function mask_phone($phone) {
 		$debug = false && PHPDEBUG_MODE_OUTPUT;
		if ($debug) console::groupFunc();
		$phone = static::format_phone($phone);
		$array = str_split($phone);
		if ($debug) console::log('$array', $array);
		$first = array_splice($array, 0, 2);
		if ($debug) console::log('$first', $first);
		$last = array_splice($array, -4);
		if ($debug) console::log('$last', $last);
		if ($debug) console::log('$array', $array);
		$phone = implode($first);
		$phone .= preg_replace('/[0-9]/', '*', implode($array));
		$phone .= implode($last);
		if ($debug) console::log('return', $phone);
		if ($debug) console::groupEnd();
    return $phone;
	}
	public static function format_phone($phone = '', $convert = false, $trim = false) {
    // If we have not entered a phone number just return empty
    if (empty($phone)) {
        return '';
    }
 		$debug = false && PHPDEBUG_MODE_OUTPUT;
		if ($debug) console::groupFunc();

    // Strip out any extra characters that we do not need only keep letters and numbers
    $phone = preg_replace("/[^0-9A-Za-z]/", "", $phone);
 
    // Do we want to convert phone numbers with letters to their number equivalent?
    // Samples are: 1-800-TERMINIX, 1-800-FLOWERS, 1-800-Petmeds
    if ($convert == true) {
        $replace = array('2'=>array('a','b','c'),
                 '3'=>array('d','e','f'),
                     '4'=>array('g','h','i'),
                 '5'=>array('j','k','l'),
                                 '6'=>array('m','n','o'),
                 '7'=>array('p','q','r','s'),
                 '8'=>array('t','u','v'), '9'=>array('w','x','y','z'));
 
        // Replace each letter with a number
        // Notice this is case insensitive with the str_ireplace instead of str_replace 
        foreach($replace as $digit=>$letters) {
            $phone = str_ireplace($letters, $digit, $phone);
        }
    }
 
    // If we have a number longer than 11 digits cut the string down to only 11
    // This is also only ran if we want to limit only to 11 characters
    if ($trim == true && strlen($phone)>11) {
        $phone = substr($phone,  0, 11);
    }
 
		if ($debug) console::log('strlen($phone)', strlen($phone));
    // Perform phone number formatting here
    if (strlen($phone) == 7) {
        $phone = preg_replace("/([0-9a-zA-Z]{3})([0-9a-zA-Z]{4})/", "$1-$2", $phone);
    } elseif (strlen($phone) == 10) {
        $phone = preg_replace("/([0-9a-zA-Z]{3})([0-9a-zA-Z]{3})([0-9a-zA-Z]{4})/", "($1) $2-$3", $phone);
    } elseif (strlen($phone) == 11) {
        $phone = preg_replace("/([0-9a-zA-Z]{1})([0-9a-zA-Z]{3})([0-9a-zA-Z]{3})([0-9a-zA-Z]{4})/", "$1($2) $3-$4", $phone);
    } else {
        $phone = preg_replace("/([0-9a-zA-Z]{1})([0-9a-zA-Z]{3})([0-9a-zA-Z]{4})([0-9a-zA-Z])/", "$1($2) $3-$4", $phone);
    }
 
    // Return original phone if not 7, 10 or 11 digits long
		if ($debug) console::log('return', $phone);
		if ($debug) console::groupEnd();
    return '+'.$phone;
	}
		static function json_indent($json) {

		$result = '';
		$pos = 0;
		$strLen = strlen($json);
		$indentStr = '  ';
		$newLine = "\n";
		$prevChar = '';
		$outOfQuotes = true;

		for ($i = 0; $i <= $strLen; $i++) {

			// Grab the next character in the string.
			$char = substr($json, $i, 1);

			// Are we inside a quoted string?
			if ($char == '"' && $prevChar != '\\') {
				$outOfQuotes = !$outOfQuotes;

				// If this character is the end of an element,
				// output a new line and indent the next line.
			} else if (($char == '}' || $char == ']') && $outOfQuotes) {
				$result .= $newLine;
				$pos--;
				for ($j = 0; $j < $pos; $j++) {
					$result .= $indentStr;
				}
			}

			// Add the character to the result string.
			$result .= $char;

			// If the last character was the beginning of an element,
			// output a new line and indent the next line.
			if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
				$result .= $newLine;
				if ($char == '{' || $char == '[') {
					$pos++;
				}

				for ($j = 0; $j < $pos; $j++) {
					$result .= $indentStr;
				}
			}

			$prevChar = $char;
		}

		return $result;
	}
}
