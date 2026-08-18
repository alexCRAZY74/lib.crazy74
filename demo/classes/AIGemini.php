<?php

declare(strict_types=1);

/**
 * Класс-провайдер для работы с API Google Gemini.
 * Наследуется от \AI\GoogleType и использует эндпоинты Google Generative Language API.
 */
class AIGemini extends \AI\GoogleType {

	/**
	 * Уникальный ключ сессии для ротации API-ключей Gemini.
	 */
	public const string SESSION_KEY = '__AIGemini__key_num__';

	/**
	 * Список поддерживаемых моделей Google Gemini и их параметров.
	 */
	public const array MODELS = [
		'gemini-flash-latest' => ['modelVersion' => 'gemini-3-flash-preview', 'displayName' => 'Gemini 3 Flash'],
		'gemini-pro-latest' => [],
		'gemini-3-flash-preview' => [],
		'gemini-2.5-flash' => [],
		'gemini-2.0-flash' => [],
		'gemini-2.5-flash-lite' => [],
	];

	/**
	 * Возвращает массив API-ключей Google Gemini.
	 * Подключает отладочный файл, переопределяющий переменную $result.
	 *
	 * @return array<int, string>
	 */
	#[\Override]
	protected function GetApiKeys(): array {
		$result = static::API_KEYS;
		@include __DIR_DEBUG_ . 'gemini_api_keys.php';
		return $result;
	}
}