<?php
declare(strict_types=1);

namespace core;

abstract class utils {

    public static function removeBOM(string $str = ""): string {
        if (str_starts_with($str, "\xEF\xBB\xBF")) {
            return substr($str, 3);
        }
        return $str;
    }

    public static function get_encoding(string $str): string {
        $cp_list = ['UTF-8', 'CP1251'];
        foreach ($cp_list as $codepage) {
            $converted = iconv($codepage, $codepage, $str);
            if ($converted !== false && md5($str) === md5($converted)) {
                return $codepage;
            }
        }
        return 'UTF-8';
    }

    public static function sizebytes(float|int $svalue): string {
        $end = 'b';
        $value = (float)$svalue;
        
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
        
        return \numbers::round($value, '0', ' ', 0, 2) . ' ' . $end;
    }

    public static function to_utf8(string $str): string {
        $converted = iconv(self::get_encoding($str), "UTF-8//IGNORE", $str);
        return $converted !== false ? $converted : $str;
    }

    public static function to_CP1251(string $str): string {
        $converted = iconv(self::get_encoding($str), "CP1251//IGNORE", $str);
        return $converted !== false ? $converted : $str;
    }

    public static function rus_bool(mixed $data, bool $FILTER_NULL_ON_FAILURE = false): ?bool {
        $cleanData = (string)$data;
        if (class_exists('\\sql_exec') && method_exists('\\sql_exec', 'clean_str')) {
            $cleanData = \sql_exec::clean_str($cleanData);
        }

        $result = null;
        switch (mb_strtoupper(trim($cleanData), 'UTF-8')) {
            case 'ДА':
            case 'YES':
            case '1':
            case 'TRUE':
                $result = 'yes';
                break;
            case 'НЕТ':
            case 'NO':
            case '0':
            case 'FALSE':
                $result = 'no';
                break;
        }

        if ($result === null) {
            return $FILTER_NULL_ON_FAILURE ? null : false;
        }

        $flags = $FILTER_NULL_ON_FAILURE ? FILTER_NULL_ON_FAILURE : 0;
        return filter_var($result, FILTER_VALIDATE_BOOLEAN, $flags);
    }

    public static function getGUID(): string {
        if (function_exists('com_create_guid')) {
            return com_create_guid();
        }

        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        return '{' . strtoupper($uuid) . '}';
    }

    public static function array_diff(mixed $array1, mixed $array2, bool $strict = false): array {
        if (!is_array($array1)) {
            throw new \InvalidArgumentException('$array1 must be an array!');
        }

        if (!is_array($array2)) {
            return $array1;
        }

        $result = [];

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

            if ($strict ? (is_float($value1) && is_float($value2)) : (is_float($value1) || is_float($value2))) {
                $value1 = (string)$value1;
                $value2 = (string)$value2;
            }

            if ($strict ? $value1 !== $value2 : $value1 != $value2) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public static function mask_phone(string $phone): string {
        $debug = false && defined('PHPDEBUG_MODE_OUTPUT') && PHPDEBUG_MODE_OUTPUT;
        if ($debug && class_exists('\\console')) \console::groupFunc();

        $formatted = static::format_phone($phone);
        $array = str_split($formatted);

        if ($debug && class_exists('\\console')) \console::log('$array', $array);
        $first = array_splice($array, 0, 2);
        if ($debug && class_exists('\\console')) \console::log('$first', $first);
        $last = array_splice($array, -4);
        if ($debug && class_exists('\\console')) \console::log('$last', $last);
        if ($debug && class_exists('\\console')) \console::log('$array', $array);

        $result = implode('', $first);
        $result .= preg_replace('/[0-9]/', '*', implode('', $array));
        $result .= implode('', $last);

        if ($debug && class_exists('\\console')) \console::log('return', $result);
        if ($debug && class_exists('\\console')) \console::groupEnd();

        return $result;
    }

    public static function format_phone(string $phone = '', bool $convert = false, bool $trim = false): string {
        if ($phone === '') {
            return '';
        }

        $debug = false && defined('PHPDEBUG_MODE_OUTPUT') && PHPDEBUG_MODE_OUTPUT;
        if ($debug && class_exists('\\console')) \console::groupFunc();

        $phone = (string)preg_replace("/[^0-9A-Za-z]/", "", $phone);

        if ($convert) {
            $replace = [
                '2' => ['a', 'b', 'c'],
                '3' => ['d', 'e', 'f'],
                '4' => ['g', 'h', 'i'],
                '5' => ['j', 'k', 'l'],
                '6' => ['m', 'n', 'o'],
                '7' => ['p', 'q', 'r', 's'],
                '8' => ['t', 'u', 'v'],
                '9' => ['w', 'x', 'y', 'z']
            ];

            foreach ($replace as $digit => $letters) {
                $phone = str_ireplace($letters, (string)$digit, $phone);
            }
        }

        if ($trim && strlen($phone) > 11) {
            $phone = substr($phone, 0, 11);
        }

        if ($debug && class_exists('\\console')) \console::log('strlen($phone)', strlen($phone));

        $len = strlen($phone);
        if ($len === 7) {
            $phone = (string)preg_replace("/([0-9a-zA-Z]{3})([0-9a-zA-Z]{4})/", "$1-$2", $phone);
        } elseif ($len === 10) {
            $phone = (string)preg_replace("/([0-9a-zA-Z]{3})([0-9a-zA-Z]{3})([0-9a-zA-Z]{4})/", "($1) $2-$3", $phone);
        } elseif ($len === 11) {
            $phone = (string)preg_replace("/([0-9a-zA-Z]{1})([0-9a-zA-Z]{3})([0-9a-zA-Z]{3})([0-9a-zA-Z]{4})/", "$1($2) $3-$4", $phone);
        } else {
            $phone = (string)preg_replace("/([0-9a-zA-Z]{1})([0-9a-zA-Z]{3})([0-9a-zA-Z]{4})([0-9a-zA-Z])/", "$1($2) $3-$4", $phone);
        }

        if ($debug && class_exists('\\console')) \console::log('return', $phone);
        if ($debug && class_exists('\\console')) \console::groupEnd();

        return '+' . $phone;
    }

    public static function json_indent(string $json): string {
        $result = '';
        $pos = 0;
        $strLen = strlen($json);
        $indentStr = '  ';
        $newLine = "\n";
        $prevChar = '';
        $outOfQuotes = true;

        for ($i = 0; $i < $strLen; $i++) {
            $char = $json[$i];

            if ($char === '"' && $prevChar !== '\\') {
                $outOfQuotes = !$outOfQuotes;
            } elseif (($char === '}' || $char === ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos--;
                $result .= str_repeat($indentStr, max(0, $pos));
            }

            $result .= $char;

            if (($char === ',' || $char === '{' || $char === '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char === '{' || $char === '[') {
                    $pos++;
                }
                $result .= str_repeat($indentStr, max(0, $pos));
            }

            $prevChar = $char;
        }

        return $result;
    }
}