<?php
declare(strict_types=1);

namespace page;

use console;

abstract class basePage extends \TwigPage {

	public function __construct() {
		$debug = true;
		console::groupFunc();
		parent::__construct();

		$this->languageCode = \lang::current();
		$this->actionURL = \App::urlServer() . '/index.php';

		$parts = explode('\\', static::class);
		array_shift($parts);

		$this->PageID = implode('_', $parts);
    $this->title = \lang::Text('pages', $this->PageID, 'title');
		$scriptName = '/demo/js/page.' . implode('.', $parts) . '.js';

		// \debug::outecho('test', \lang::getSection('pages'));
		if ($debug) {
			console::log('$scriptName', $scriptName);
		}

		if (file_exists(__DIR_ROOT_ . $scriptName)) {
			$this->pageJS = $scriptName;
		}

		if ($debug) {
			console::log('$this', $this);
		}

		console::groupEnd();
	}

	public static function twig__pageURL(mixed ...$args): string {
		return \App::urlToPage(...$args);
	}

	public static function twig__strcut(mixed ...$args): ?string {
		return \App::strcut(...$args);
	}
}
