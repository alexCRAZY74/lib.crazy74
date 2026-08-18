<?php

declare(strict_types=1);

/**
 * Класс управления подключением к СУБД MySQL/MariaDB.
 * Расширяет базовый функционал работы с БД из ядра (\core\mysqli_db).
 */
class db extends \core\mysqli_db {

	/**
	 * Возвращает параметры подключения к базе данных.
	 *
	 * Порядок элементов массива:
	 *  0 => Host (хост)
	 *  1 => User (пользователь)
	 *  2 => Password (пароль)
	 *  3 => Database (имя БД)
	 *  4 => Port (порт)
	 *
	 * @return array{0: string, 1: string, 2: string, 3: string, 4: int}
	 */
	#[\Override]
	public static function connectData(): array {
		// Если в отладочной директории есть конфиг БД, считываем учетные данные из него
		if (defined('__DIR_DEBUG_')) {
			$configFile = __DIR_DEBUG_ . 'db_config.php';
			if (file_exists($configFile)) {
				$config = require $configFile;
				if (is_array($config)) {
					return $config;
				}
			}
		}

		// Значения по умолчанию для локального окружения
		return ['localhost', 'root', '12345', 'job_search', 3307];
	}
}