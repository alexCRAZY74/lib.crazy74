# Описание PHP-компонентов библиотеки (lib.crazy74)

Данный документ описывает структуру, ключевые сервисы и архитектуру серверной части библиотеки `lib.crazy74`.

---

## Автозагрузчик классов

Автозагрузка классов и трейтов в библиотеке реализована через статический метод `SharedCommon::auto_load()`, который регистрируется с помощью `spl_autoload_register()`.

С версии PHP 8.2 (`declare(strict_types=1)` в `common.php`) сигнатуры типизированы: `auto_load(string $name): void`, `includeList(string $name, string $DIR): array`. Важно для тех, кто расширяет `SharedCommon`: `includeList()` при уже существующем классе теперь явно возвращает пустой массив, а не `null` — при строгой типизации `null` ломает `array_merge()` на стороне вызывающего кода.

Метод `auto_load` осуществляет поиск файлов классов и трейтов в директориях, определенных в глобальном массиве `$_ROOTFOLDERS` (при подключении `common.php` директория библиотеки добавляется в этот массив автоматически). Для каждого корня из `$_ROOTFOLDERS` загрузчик сканирует как саму директорию, так и подкаталог `classes/`, а также автоматически транслирует пространства имен (namespace) во вложенную структуру папок.

Поиск файлов производится по следующим паттернам именования:
* `class.ИмяКласса.php`, `default.class.ИмяКласса.php`
* `abstract.ИмяКласса.php`, `class.abstract.ИмяКласса.php`
* `ИмяКласса.class.php`, `ИмяКласса.abstract.php`, `ИмяКласса.class.abstract.php`
* `trait.ИмяТрейта.php`
* `ИмяКласса.php`

Проверка существования каждого файла-кандидата выполняется через `is_readable()`, а не `file_exists()` — это отсекает случай, когда файл физически есть, но недоступен для чтения по правам.

Пример структуры `$_ROOTFOLDERS`:

```php
// Достаточно указать базовые папки проектов — дочерние каталоги и classes/ сканируются автоматически
$_ROOTFOLDERS = [
    __DIR__ . '/',
    __DIR__ . '/custom-modules/',
];
```

Это обеспечивает гибкую и расширяемую систему автозагрузки для всех компонентов библиотеки.

### Точка входа (`common.php`) и запуск приложения

Подключение библиотеки обычно строится по схеме с переключением между боевым сервером и локальной копией для отладки:

```php
$localShared = dirname(__FILE__) . '/lib-php/common.php';
$serverShared = '/var/www/vhosts/bayandin.su/uni.bayandin.su/shared/common.php';

if (is_readable($serverShared)) {
    require_once $serverShared;
} elseif (is_readable($localShared)) {
    require_once $localShared;
}
```

Файл `common.php` регистрирует автозагрузчик и по умолчанию сразу вызывает `App::startup()`. Поведение управляется константой `APP_AUTOSTART` (по умолчанию `true`, определяется через `!defined(...) && define(...)`). Чтобы подключить библиотеку без немедленного старта приложения, необходимо объявить `define('APP_AUTOSTART', false);` **до** `require_once` файла `common.php`.

Легаси-автозагрузчик Twig 1.x (`Twig/Autoloader.php`, `\Twig_Autoloader::register()`) подключается опционально — только если файл присутствует и читаем (`is_readable`). Если библиотека работает с Twig 2+/3+ через Composer/PSR-4, этот блок просто ничего не делает, без фатальной ошибки.

### Единая точка входа и AJAX-диспетчер (`App::ajax`)

Типовая схема маршрутизации URL строится вокруг единой точки входа (`index.php`), которая принимает вызовы и передает управление диспетчеру `App::ajax()`:

```php
// index.php
require_once dirname(__FILE__) . '/lib-php/common.php';
App::ajax();
```

Метод `App::ajax()` берет на себя полный цикл диспетчеризации входящего запроса:

1. **Разрешение контроллера и метода:**
   * Считывает параметры `$_REQUEST['class']` и `$_REQUEST['method']` (по умолчанию `get`).
   * Через `check_class_method_request()` проверяет существование класса и доступность метода.
   * С помощью инспекции через `ReflectionClass` и проверки `__callStatic` определяет характер вызова: статический (`Class::method()`) или экземплярный (`$instance->method()`). Если метод не задан, возвращается вновь созданный объект класса.

2. **Обогащение контекста:**
   * В результирующий массив или объект автоматически внедряется идентификатор сессии `$SID` (`session_id()`).
   * Формируется `debugURL` — полный адрес запроса со всеми переданными параметрами для удобства изолированного воспроизведения и отладки.
   * Подключаются результаты систем сбора ошибок (`\errors::Result()`) и трекинга модификаций данных (`\changes::Result()`).

3. **Форматирование и отдача ответа (`App::flush()`):**
   * В режиме отладки (`$debugMode`) выводит структурированную панель данных и замер объема ответа (`checksize`).
   * В боевом режиме сериализует данные в JSON с защитой от XSS (`JSON_HEX_TAG`, `JSON_HEX_APOS`, `JSON_HEX_QUOT`, `JSON_HEX_AMP`) и поддержкой UTF-8 (`JSON_UNESCAPED_UNICODE`).
   * Поддерживает сжатый транспорт `jsongz` (Content-Type: `application/jsongz`) при установленном флаге `$_REQUEST['jsongz']`.

---

## Архитектура рендеринга (`TwigPage`)

Абстракция `class.abstract.TwigPage.php` предоставляет интеграцию с шаблонизатором Twig:

* Автоматизация вариантов шаблонов: Метод `templatenameVariants()` генерирует перечень возможных имен шаблонов на основе установленных префиксов и контекста запроса.
* Авторегистрация хелперов: Методы класса с префиксом `twig__` автоматически регистрируются в качестве доступных функций Twig.
* Вложенные компоненты: Метод `twig__inset()` обеспечивает динамическую подгрузку и вставку дочерних шаблонов.

---

## Компоненты ядра (`/lib-php/core`)

### `\core\mysqli_db`

Абстрактный класс для работы с СУБД MySQL (через расширение `mysqli`). Обеспечивает централизованное управление соединениями, автоматическое экранирование типов, кэширование структуры таблиц и результатов точечных выборок, а также предоставляет ActiveRecord-подобные методы для манипуляции данными.

#### Статические методы

##### Управление соединением и транзакциями
* `public static mysqli|false connect()`: Инициализирует и возвращает объект `mysqli`. Параметры подключения извлекаются из `connectData()`.
* `public static array connectData()`: Возвращает параметры подключения по умолчанию `['localhost', 'root', '12345', 'mysql']`. Переопределяется в наследниках.
* `public static boolean init()`: Настраивает кодировку соединения (`utf8mb4`) и временную зону.
* `public static boolean is_inited()`: Проверяет и обеспечивает готовность соединения к работе.
* `public static boolean transaction()` / `commit()` / `rollback()`: Управление транзакциями базы данных.

##### Выполнение запросов и выборка данных
* `public static mysqli_result|false query(string $sql)`: Выполняет прямой SQL-запрос с обработкой ошибок и логированием в отладочный стек.
* `public static array|false assoc(string $keyField, string $sql)` или `assoc(string $sql)`: Возвращает двумерный ассоциативный массив строк. Если передан первый параметр, результирующий массив индексируется значениями этого поля.
* `public static array|false row(string $valueField, string $sql)` или `row(string $sql)`: Возвращает ассоциативный массив одной строки. Если передан первый параметр, возвращает непосредственно значение этого поля.
* `public static array|false get_array(string $valueField, string $sql)`: Возвращает плоский массив значений указанного поля из всех строк результата.
* `public static array|false id_list(string $table, array $where = [], string $idField = 'id')`: Возвращает плоский массив уникальных идентификаторов из таблицы по заданным условиям.

##### Манипуляция данными (C.R.U.D.)
* `public static int|false insert(string $table, array $data, boolean $typeupdate = true)`: Выполняет вставку записи. Если в `$data` присутствует ключ `id`, операция выполняется через `REPLACE`. Возвращает `insert_id`.
* `public static int|false insertIgnore(string $table, array $data, boolean $typeupdate = true)`: Выполняет вставку с игнорированием ошибок дублирования ключей (`INSERT IGNORE`).
* `public static int|false replace(string $table, array $data, boolean $typeupdate = true)`: Выполняет замену записи (`REPLACE INTO`).
* `public static boolean update(string $table, mixed $id, array $data, boolean $typeupdate = true)`: Обновляет запись по идентификатору (или массиву условий `$id`).
* `public static string update_sql(string $table, mixed $id, array $data, boolean $typeupdate = true)`: Генерирует SQL-текст для операции `UPDATE`.

##### Кэширование и работа с метаданными
* `public static array fullRow(string $table, mixed $id, array $select = ['*'])`: Возвращает строку таблицы с использованием внутреннего кэша в памяти (`$rowCache`), предотвращая повторные запросы к БД в рамках одного процесса.
* `public static array|false tablerow(string $table, mixed $id, array $select = ['*'])`: Выполняет прямой запрос строки из таблицы по первичному ключу (`id` или `guid`) без использования кэша.
* `public static boolean AssignRow(object|array &$object, string $table, mixed $param, string $prefix = "")`: Извлекает строку таблицы и динамически заполняет свойства объекта (или элементы ассоциативного массива) с автоматическим приведением типов данных.
* `public static array tableStructure(string $table)`: Возвращает и кэширует структуру колонок таблицы (`SHOW COLUMNS`).
* `public static array tableKeysTypes(string $table)`: Возвращает карту соответствия полей таблицы их PHP-типам (`integer`, `float`, `boolean`, `datetime`, `string`).
* `public static boolean tableExists(string $table)` / `fieldExists(string $table, string $field)` / `procedureExists(string $name)`: Проверяет существование таблицы, поля или хранимой процедуры в БД с кэшированием результатов проверок.

---

## Сервисы отладки и обработки ошибок

* `class.errors.php` — перехват системных ошибок и исключений PHP, логирование и форматирование стека вызовов.

### `DebugHandler` (`class.abstract.DebugHandler.php`) — общий диспетчер отладочного вывода

Абстрактный класс-предок для `console` и `debug`. Не содержит публичного API сам по себе — методы вызываются через `__callStatic()`, который транслирует `SomeClass::foo(...)` в защищённый `_foo__static(...)`, если такой метод существует у наследника; иначе выводит список доступных методов (диагностика опечатки).

* **Управление выводом:** `public static bool $forcedOutput` — если `false` (по умолчанию), вывод глушится константой `PHPDEBUG_MODE_OUTPUT` (когда она определена и равна `false`). Действует одинаково на всех наследников `DebugHandler` разом.
* **Фильтрация трассировки:** `public static array $skipfiles` — список файлов, кадры которых исключаются из `backtrace()`/`lineFile()` (каждый наследник добавляет себя: `console::$skipfiles[] = __FILE__;`).
* **Базовый набор методов** (доступны и `console::`, и `debug::` без переопределения): `outecho()`/`echo()` — эхо переменной с заголовком (`struc`/`dump`/`textarea`/`table`), `memory()` — текущее потребление памяти, `trace()`/`logTrace()` — текущий стек вызовов, `group()`/`groupEnd()` (алиасы `echoGroup()`/`echoGroupEnd()`) — вложенные визуальные блоки с замером времени (через `worktimes`, если доступен) и памяти, `table()` — табличный вывод массива/списка объектов, `groupFunc()` — автоматический `group()` с заголовком, сгенерированным из имени и аргументов вызывающей функции.

### `console` (`class.console.php`) — вывод в браузер

Тонкая статическая обёртка над `DebugHandler`: `log()` (тип `struc`), `dump()` (`var_dump`), `text()` (текст в `<textarea>`) — явные переопределения, плюс проброс `memory()`/`trace()`/`group()`/`groupFunc()`/`groupEnd()` из предка. Используется для отладочного вывода прямо на страницу.

### `debug` (`class.debug.php`) — файловый лог + тот же API

Тоже наследник `DebugHandler` — весь набор из предыдущего пункта (`outecho`, `echoGroup`/`echoGroupEnd`, `table`, `group`/`groupEnd`, `memory`, `trace`, `groupFunc`) работает у `debug` идентично `console`, без дублирования кода. Отличие — один явно переопределённый метод, которому нет аналога у `console`:

* `public static function log(string $name, mixed $var = null): void` — вместо эха в браузер пишет запись в файл (путь по умолчанию задаётся внутри класса, переопределяется через `debug::setFile(string $filename): bool`). Ротация файла при достижении 5 МБ, привязка записи к `$_SESSION['base']`, путь вызова через `static::backtrace()`. Важно: `PHPDEBUG_MODE_OUTPUT` на `debug::log()` не действует — отключение HTML-эха константой не гасит файловый лог.

---

## Служебные утилиты

### `array_var` (`class.array_var.php`)

Статический хелпер для безопасной и строго типизированной работы с многомерными массивами и вложенными структурами данных (`declare(strict_types=1)`):

* `public static function get(mixed $target, string|int|array $key, mixed $default = null): mixed` — извлечение значения по скалярному ключу или цепочке вложенных ключей (массив ключей).
* `public static function set(mixed &$target, string|int|array $key, mixed $value, string $operand = '='): void` — безопасная модификация и создание вложенных структур по цепочке ключей с поддержкой операторов (`=`, `+=`, `-=`, `*=`, `/=`, `.=`, `|=`, `&=`, `^=`, `%=`) без использования `eval()`.
* `public static function get_bool(mixed $target, string|int|array $key, bool $default = false): bool` — извлечение булева значения с валидацией через `filter_var()` и флагом `FILTER_NULL_ON_FAILURE`.
* `public static function get_array(mixed $target, string|int|array $key, mixed $default = []): mixed` — безопасное получение непустого массива или возврат значения по умолчанию.

### `request` (`class.request.php`)

Статический класс для безопасного и типизированного доступа к данным из суперглобальных массивов (`$_REQUEST`). Делегирует обработку вложенных ключей и типизацию классу `array_var` и использует `filter_var` для валидации.

* `public static function get(string|array $key, mixed $default = null): mixed` — универсальный метод для получения значения из `$_REQUEST` по скалярному ключу или цепочке вложенных ключей, делегируя `array_var::get()`.
* `public static function filter(string|array $key, int $filter = FILTER_DEFAULT, int $options = 0): mixed` — фильтрация значения из `$_REQUEST` с помощью `filter_var()`, используя `array_var::get()` для извлечения исходного значения.
* `public static function get_bool(string|array $key, int $options = 0): bool` — получение булева значения из `$_REQUEST` с корректной валидацией через `static::filter()` и `FILTER_VALIDATE_BOOLEAN`, возвращая `false` при неудачной фильтрации.
