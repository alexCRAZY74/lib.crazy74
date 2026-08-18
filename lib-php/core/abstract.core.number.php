<?php

declare(strict_types=1);

namespace core;

if (!defined('__Numder_Fix_Precision__')) {
	define('__Numder_Fix_Precision__', 0);
}
if (!defined('__Numder_Max_Precision__')) {
	define('__Numder_Max_Precision__', 4);
}
if (!defined('__Numder_Rounding_Threshold__')) {
	define('__Numder_Rounding_Threshold__', 10000);
}

abstract class number {

	public static function smartFormat(
		int|float|string|null $value,
		string|bool $unitKey = false,
		bool $hideOne = false,
		int $fix_precision = __Numder_Fix_Precision__,
		int $max_precision = __Numder_Max_Precision__
	): string {
		$result = (float) $value;
		$endings = false;
		$postfix = '';
		$precision = 0;

		if (is_string($unitKey)) {
			$section = \lang::getSection('numbers');
			if (is_array($section) && isset($section['units']) && is_array($section['units'])) {
				if (isset($section['units'][$unitKey]) && is_array($section['units'][$unitKey])) {
					$endings = $section['units'][$unitKey];
					$postfix = self::ending(round($result, $precision), $endings);
				}
			}
		}

		if (abs($result) >= __Numder_Rounding_Threshold__) {
			$result /= 1000;
			$postfix = ' ' . \lang::Text('numbers', 'postfix', 'kilo') . (is_array($endings) ? ' ' . $endings[2] : '');
			if (abs($result) >= 1000) {
				$result /= 1000;
				$postfix = ' ' . \lang::Text('numbers', 'postfix', 'mega') . (is_array($endings) ? ' ' . $endings[2] : '');
				if (abs($result) >= 1000) {
					$result /= 1000;
					$postfix = ' ' . \lang::Text('numbers', 'postfix', 'giga') . (is_array($endings) ? ' ' . $endings[2] : '');
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

		if ($fix_precision > 0) {
			$precision = $fix_precision;
		}

		$formattedResult = self::round($result, '0', ' ', $fix_precision, $max_precision) . $postfix;

		if (is_string($unitKey) && !is_array($endings)) {
			$formattedResult .= ' ' . \lang::Text('numbers', 'units', $unitKey);
		}

		if ($hideOne && (float) $value === 1.0 && is_array($endings)) {
			$formattedResult = trim(self::ending(1, $endings));
		}

		return $formattedResult;
	}

	public static function ending(
		int|float $num,
		array $endings = ['штука', 'штуки', 'штук'],
		string $null = ''
	): string {
		if ($num == 0) {
			return $null;
		}

		$sEnding = '';
		$iNumber = (int) abs($num) % 100;

		if ($iNumber >= 11 && $iNumber <= 19) {
			$sEnding = $endings[2] ?? '';
		} else {
			switch ($iNumber % 10) {
				case 1:
					$sEnding = $endings[0] ?? '';
					break;
				case 2:
				case 3:
				case 4:
					$sEnding = $endings[1] ?? '';
					break;
				default:
					$sEnding = $endings[2] ?? '';
					break;
			}
		}

		return ' ' . $sEnding;
	}

	public static function round(
		int|float|string|null $lp_value,
		string $replacer = '0',
		string $thousands_sep = '',
		int $fix_precision = 0,
		int $max_precision = 4
	): string {
		$ret = '';
		$value = $lp_value;

		if ($value !== '' && $value !== null) {
			$floatVal = (float) $value;
			$precision = 0;
			$n_value = round($floatVal, $max_precision);
			$t_value = round($floatVal, $precision);

			while ($t_value != $n_value && $precision < $max_precision) {
				$precision++;
				$t_value = round($floatVal, $precision);
			}

			if ($fix_precision > 0) {
				$precision = $fix_precision;
			}

			$ret = number_format($floatVal, $precision, '.', '|');
		} else {
			$floatVal = (float) $value;
			$precision = $fix_precision > 0 ? $fix_precision : 0;
			$ret = ($replacer !== '0') ? $replacer : number_format($floatVal, $precision, '.', '|');
		}

		return str_replace('|', $thousands_sep, $ret);
	}

	public static function sizebytes(int|float|string|null $svalue): string {
		$end = 'b';
		$value = (float) $svalue;

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

		return self::round($value, '0', ' ', 0, 2) . ' ' . $end;
	}
}