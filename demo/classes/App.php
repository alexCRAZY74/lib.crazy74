<?php

declare(strict_types=1);

/**
 * Главный класс приложения (демка и базовые точки входа).
 * Наследуется от базового класса ядра \core\App.
 */
class App extends \core\App {

	/**
	 * Формирует абсолютный URL страницы с GET-параметрами.
	 *
	 * Аргументы сопоставляются последовательно:
	 *  0 => '_page'
	 *  1 => 'lang'
	 *  N => 'paramN'
	 *
	 * @param mixed ...$arguments Динамический список значений параметров
	 * @return string Сформированная ссылка
	 */
	public static function urlToPage(mixed ...$arguments): string {
		$link = static::urlServer() . '/index.php';
		$query = [];

		if (!empty($arguments)) {
			// Имена ключевых параметров по порядку следования аргументов
			$keys = ['_page', 'lang'];
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

	/**
	 * Тестовый метод проверки работы с СУБД.
	 * Выводит первенцев из таблицы вакансий в консоль отладки.
	 *
	 * Отладочная ссылка: http://localhost:12528/?debug=yes&class=App&method=TestDB
	 */
	public static function TestDB(): void {
		\console::groupFunc();
		\console::table('test', \db::assoc('SELECT `url`, `rating`, `status`, `position` FROM `job_openings` LIMIT 5'));
		\console::groupEnd();
	}

	/**
	 * Тестовый метод проверки интеграции с AI-сервисами.
	 * Принимает модель из запроса, инициализирует провайдера, отправляет тестовый запрос и возвращает статус.
	 *
	 * Отладочная ссылка: http://localhost:12528/?debug=yes&class=App&method=TestAI
	 *
	 * @return array{success: bool} Результат выполнения операции
	 */
	public static function TestAI(): array {
		$result = ['success' => false];

		\console::groupFunc();

		// Получаем имя модели из GET/POST (по умолчанию 'gemini-flash-latest')
		$model = (string) \request::get('model', 'gemini-flash-latest');
		\console::log('$model', $model);

		// Выбираем соответствующий класс провайдера по наличию модели в списке OpenRouter
		$API = isset(\AIOpenRouter::MODELS[$model]) 
			? new \AIOpenRouter($model) 
			: new \AIGemini($model);

		\console::log('$API', $API);

		// Инициализируем объекты запроса и ответа
		$request = new \AI\Request();
		$response = new \AI\Response();

		// Заполняем промпт
		$request->Add('Ты знаешь свою модель?');

		// Отправляем запрос через API-клиент
		$API->Request($request, $response);

		\console::log('$response->get()', $response->get());

		if (!$response->success) {
			\console::log('$response->answer', $response->answer);
		}

		// Фиксируем ошибки и итоговый статус
		\errors::Result($result);
		$result['success'] = $result['success'] && !\errors::Exists();

		\console::groupEnd();

		return $result;
	}

	/**
	 * Обрезает длинную строку по центру, оставляя начало и конец и вставляя разделитель '… ⋯ …'.
	 *
	 * @param string|null $text Исходный текст
	 * @param int $maxlen Максимально допустимая длина итоговой строки
	 * @return string|null Сокращенная строка или исходное значение
	 */
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

		// Если лимит меньше длины разделителя — просто обрезаем сначала
		if ($maxlen <= $sepLength) {
			return mb_substr($text, 0, $maxlen);
		}

		// Распределяем оставшуюся длину: 65% на левую часть, 35% на правую
		$leftLength = (int) ceil(($maxlen - $sepLength) * 0.65);
		$rightLength = $maxlen - $sepLength - $leftLength;

		$left = mb_substr($text, 0, $leftLength);
		$right = $rightLength > 0 ? mb_substr($text, -$rightLength) : '';

		return $left . $separator . $right;
	}
}