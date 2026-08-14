<?php
/**
 * Единая точка входа демонстрационного приложения
 */

// Флаг автоматического запуска ядра при подключении common.php (App::startup())
if (!defined('APP_AUTOSTART')) define('APP_AUTOSTART', true);

// Настройки точности вывода чисел по умолчанию (библиотечный модуль number)
if (!defined('__Numder_Fix_Precision__')) define('__Numder_Fix_Precision__', 0);
if (!defined('__Numder_Max_Precision__')) define('__Numder_Max_Precision__', 1);

// Базовые каталоги демонстрационного проекта
define('__DIR_ROOT_', dirname(__FILE__) . "/demo/");
define('__DIR_DEBUG_', dirname(__FILE__) . "/debug/");

// Список корней для автоматической загрузки классов (SharedCommon::auto_load)
// Каталоги classes/ и пространства имен сканируются внутри них автоматически
$_ROOTFOLDERS = array();
$_ROOTFOLDERS[] = __DIR_ROOT_;

// Подключение общего ядра библиотеки: приоритет отдается серверной копии,
// при отсутствии — используется локальная ветка для разработки
$localShared = dirname(__FILE__) . '/lib-php/common.php';
$serverShared = '/var/www/vhosts/bayandin.su/uni.bayandin.su/shared/common.php';
if (is_readable($serverShared)) {
  require_once $serverShared;
} elseif (is_readable($localShared)) {
  require_once $localShared;
}

// Перехват и диспетчеризация AJAX-запросов (контроллер class / method)
// Если запрос адресован бэкенд-обработчику, App::ajax() отдаст ответ и завершит работу
if (App::ajax()) {
  exit();
}

// Стандартный рендеринг страницы через классы из пространства \page
// Параметр _page задает целевой класс представления (по умолчанию \page\index)
$pageClass = "\\page\\" . request::get('_page', 'index');
$pageClass::Render();

// Логирование текущего состояния контекста приложения в консоль отладки
console::log('App', App::get());