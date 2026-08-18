<?php

declare(strict_types=1);

namespace page;

use console;

/**
 * Базовый абстрактный класс для всех страниц приложения с шаблонизацией Twig.
 * Наследуется от \TwigPage и выполняет первичную инициализацию локализации,
 * идентификаторов страниц и автоматическое подключение JS-скриптов.
 */
abstract class basePage extends \TwigPage {

	/**
	 * Конструктор базовой страницы.
	 * Настраивает языковой код, базовый URL, автоопределение ID страницы
	 * и подключение соответствующего клиенского JS-файла.
	 */
	public function __construct() {
		$debug = true;

		if ($debug) {
			console::groupFunc();
		}

		parent::__construct();

		// Установка текущего языка и базового URL действия
		$this->languageCode = \lang::current();
		$this->actionURL = \App::urlServer() . '/index.php';

		// Вычисление ID страницы на основе пространства имён (например, page\demo\main -> demo_main)
		$parts = explode('\\', static::class);
		array_shift($parts);

		$this->PageID = implode('_', $parts);
		$this->title = \lang::Text('pages', $this->PageID, 'title');

		// Формирование пути к JS-скрипту страницы (например, /demo/js/page.demo.main.js)
		$scriptName = '/demo/js/page.' . implode('.', $parts) . '.js';

		if ($debug) {
			console::log('$scriptName', $scriptName);
		}

		// Подключаем JS-файл только при его физическом наличии на диске
		if (file_exists(__DIR_ROOT_ . $scriptName)) {
			$this->pageJS = $scriptName;
		}

		if ($debug) {
			console::groupEnd();
		}
	}

	/**
	 * Вспомогательный статическая функция-хелпер для вызова из шаблонов Twig.
	 * Генерирует URL страницы через App::urlToPage.
	 *
	 * @param mixed ...$args Параметры для формирования ссылки
	 * @return string Сформированная ссылка
	 */
	public static function twig__pageURL(mixed ...$args): string {
		return \App::urlToPage(...$args);
	}

	/**
	 * Вспомогательный статическая функция-хелпер для вызова из шаблонов Twig.
	 * Обрезает длинный текст по центру через App::strcut.
	 *
	 * @param mixed ...$args Аргументы (текст и максимальная длина)
	 * @return string|null Сокращенная строка
	 */
	public static function twig__strcut(mixed ...$args): ?string {
		$text = isset($args[0]) && is_string($args[0]) ? $args[0] : null;
		$maxlen = isset($args[1]) && is_numeric($args[1]) ? (int) $args[1] : 50;

		return \App::strcut($text, $maxlen);
	}
}