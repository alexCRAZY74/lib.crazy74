<?php

declare(strict_types=1);

/**
 * Класс-провайдер для работы с API OpenRouter.
 * Наследуется от \AI\OpenAIType и поддерживает соответствующий формат запросов.
 */
class AIOpenRouter extends \AI\OpenAIType {

	/**
	 * Эндпоинт API OpenRouter для отправки запросов.
	 */
	public const string API_URL = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * Ключ сессии для ротации ключей API.
	 */
	public const string SESSION_KEY = '__AIOpenRouter__key_num__';

	/**
	 * Список доступных моделей.
	 */
	public const array MODELS = [
		'qwen/qwen3-coder:free' => ['displayName' => 'Qwen: Qwen3 Coder 480B A35B (free)'],
		'qwen/qwen3-next-80b-a3b-instruct:free' => ['displayName' => 'Qwen: Qwen3 Next 80B A3B Instruct (free)'],
		'google/gemma-4-26b-a4b-it:free' => ['displayName' => 'Google: Gemma 4 26B A4B'],
		'nvidia/nemotron-3-ultra-550b-a55b:free' => ['displayName' => 'NVIDIA: Nemotron 3 Ultra (free)'],
	];

	/**
	 * Возвращает список ключей API.
	 * Подключает файл из отладочной папки, перезаписывая $result.
	 *
	 * @return array<int, string>
	 */
	#[\Override]
	protected function GetApiKeys(): array {
		$result = static::API_KEYS;
		@include __DIR_DEBUG_ . 'openrouter_api_keys.php';
		return $result;
	}
}