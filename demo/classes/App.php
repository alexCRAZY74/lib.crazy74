<?php
declare(strict_types=1);

class App extends \core\App {

	public static function urlToPage(mixed ...$arguments): string {
		$link = static::urlServer() . '/index.php';
		$query = [];

		if (!empty($arguments)) {
			$keys = ['_page', 'URL'];
			foreach ($arguments as $idx => $value) {
				$key = $keys[$idx] ?? 'param' . $idx;
				$query[$key] = $value;
			}
		}

		// \console::log('$query', $query);

		if (!empty($query)) {
			$link .= '?' . http_build_query($query);
		}

		return $link;
	}

	public static function strcut(?string $text, int $maxlen = 50): ?string {
		if (empty($text)) {
			return $text;
		}

		$length = mb_strlen($text);
		if ($length <= $maxlen) {
			return $text;
		}

		$separator = '… ⋯ …';
		$sepLength = mb_strlen($separator);

		if ($maxlen <= $sepLength) {
			return mb_substr($text, 0, $maxlen);
		}

		$leftLength = (int) ceil(($maxlen - $sepLength) * 0.65);
		$rightLength = $maxlen - $sepLength - $leftLength;

		$left = mb_substr($text, 0, $leftLength);
		$right = $rightLength > 0 ? mb_substr($text, -$rightLength) : '';

		return $left . $separator . $right;
	}
}
