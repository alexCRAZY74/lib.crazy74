<?php

declare(strict_types=1);

namespace core;

use console;

abstract class dates {

	public static function fmtForMysql(int|string|bool|null $dt = false): string {
		if ($dt === false || $dt === null || $dt === '') {
			return date('Y-m-d H:i:s');
		}

		$timestamp = is_numeric($dt) ? (int) $dt : strtotime((string) $dt);
		return date('Y-m-d H:i:s', $timestamp !== false ? $timestamp : time());
	}

	public static function FormatLocale(
		int|string|null $dt,
		?string $format = null,
		bool $withTime = true
	): string {
		if (empty($dt)) {
			return '';
		}

		$debug = false;
		if ($debug) {
			console::groupFunc();
		}

		$timestamp = is_numeric($dt) ? (int) $dt : strtotime((string) $dt);
		if ($timestamp === false) {
			if ($debug) {
				console::groupEnd();
			}
			return '';
		}

		$dateSection = \lang::getSection('date');

		if (empty($format)) {
			$fKey = $withTime ? 'locale_format_with_time' : 'locale_format';
			$format = (string) \array_var::get($dateSection, $fKey, 'j.m.Y' . ($withTime ? ' H:i' : ''));
		}

		$months = \array_var::get_array($dateSection, 'monthsformat', []);
		if (!empty($months)) {
			$monthIndex = ((int) date('n', $timestamp)) - 1;
			$monthName = $months[$monthIndex] ?? '';
			if ($monthName !== '') {
				$format = str_replace(['F', 'M'], $monthName, $format);
			}
		}

		$result = date($format, $timestamp);

		if ($debug) {
			console::log('$result', $result);
			console::groupEnd();
		}

		return $result;
	}

	public static function fmtRussian(
		int|string|null $dt,
		string $format = 'j F Y г.'
	): string {
		if (empty($dt)) {
			return '';
		}

		$timestamp = is_numeric($dt) ? (int) $dt : strtotime((string) $dt);
		if ($timestamp === false) {
			return '';
		}

		$monthNum = (int) date('n', $timestamp);
		$months = [
			1 => ['января', 'янв'],
			2 => ['февраля', 'фев'],
			3 => ['марта', 'мар'],
			4 => ['апреля', 'апр'],
			5 => ['мая', 'мая'],
			6 => ['июня', 'июня'],
			7 => ['июля', 'июля'],
			8 => ['августа', 'авг'],
			9 => ['сентября', 'сен'],
			10 => ['октября', 'окт'],
			11 => ['ноября', 'ноя'],
			12 => ['декабря', 'дек'],
		];

		$m = $months[$monthNum] ?? ['', ''];
		$format = str_replace(['F', 'M'], $m, $format);

		return date($format, $timestamp);
	}

	public static function fmtSmart(int|string|null $dt, bool $withTime = true): string {
		if ($dt === 'never') {
			return (string) \lang::Text('date', 'neverdate');
		}

		if (empty($dt)) {
			return '';
		}

		$timestamp = is_numeric($dt) ? (int) $dt : strtotime((string) $dt);
		if ($timestamp === false) {
			return '';
		}

		$targetDate = date('Y-m-d', $timestamp);
		$today = date('Y-m-d');
		$yesterday = date('Y-m-d', strtotime('-1 day'));
		$beforeYesterday = date('Y-m-d', strtotime('-2 days'));
		$tomorrow = date('Y-m-d', strtotime('+1 day'));
		$afterTomorrow = date('Y-m-d', strtotime('+2 days'));

		$dateStr = match ($targetDate) {
			$today => (string) \lang::Text('date', 'today'),
			$yesterday => (string) \lang::Text('date', 'yesterday'),
			$beforeYesterday => (string) \lang::Text('date', 'before_yesterday'),
			$tomorrow => (string) \lang::Text('date', 'tomorrow'),
			$afterTomorrow => (string) \lang::Text('date', 'aftertomorrow'),
			default => self::FormatLocale($timestamp, null, false),
		};

		if ($withTime) {
			$tm = date('H:i', $timestamp);
			return $dateStr . ' ' . $tm;
		}

		return $dateStr;
	}
}