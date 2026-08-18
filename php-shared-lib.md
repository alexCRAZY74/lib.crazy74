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

При инициализации `App::startup()` проверяет входящие параметры запроса для активации режима отладки: вызовы `?debug=yes`, `?debug=1` или `?outecho=1` включают визуальный вывод отладки в браузере (`DebugHandler::$forcedOutput = true`), а также активируют профилирование времени и памяти.

**Особенность работы режима отладки:** Передача флага `debug=yes` переводит приложение в режим прямого дампа. При этом **стандартный рендеринг Twig полностью блокируется**, а в HTML-поток напрямую транслируется древовидный вывод вызовов `console::*()` (с трассировкой файлов, номеров строк, замерами времени и памяти).

Легаси-автозагрузчик встроенной Twig 1.15.1 (`Twig/Autoloader.php`, `\Twig_Autoloader::register()`) подключается опционально — только если файл присутствует и читаем (`is_readable`). Если библиотека работает с Twig 2+/3+ через Composer/PSR-4, этот блок просто ничего не делает, без фатальной ошибки.

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

Абстракция `TwigPage` (`class.abstract.TwigPage.php`) обеспечивает связь PHP-контроллеров с шаблонизатором Twig.

В состав библиотеки встроена **Twig версии 1.15.1**, переработанная (собственный форк-патч) под совместимость с PHP 7 и PHP 8.

### Стратегия миграции и совместимости Twig

При переходе с устаревших версий (например, экосистемы Paloma365) на PHP 7+ и 8.x проявилась проблема: свежие версии Twig содержат множество breaking changes и выпиливают устаревшие конструкции, что потребовало бы полной переработки нескольких тысяч готовых проектов и шаблонов.

Экономически и архитектурно выгодным решением оказалась **адаптация и патчинг самой библиотеки Twig 1.15.1** (собственный форк), сохранивший синтаксис 1.x при полной поддержке PHP 8.2–8.3:

* **Атрибут `#[\AllowDynamicProperties]`:** Класс `TwigPage` явно помечен этим атрибутом, что предотвращает выброс `Deprecated`-предупреждений в PHP 8.2+ при динамическом связывании пользовательских данных и свойств экземпляра контроллера.
* **Совместимость с strict_types:** Вызовы и методы обёрнуты так, чтобы жесткие правила типов не ломали рендеринг легаси-структур.

### Основной функционал `TwigPage`

* **Байпас рендеринга в режиме отладки:** При вызове `TwigPage::Render()` класс проверяет активный флаг отладки (`?debug=yes` / `DebugHandler::$forcedOutput`). Если режим включен, компиляция и вывод Twig-шаблона отменяются, предотвращая искажение отладочной информации HTML-версткой шаблона.
* **Автоматический поиск и вариативность шаблонов (`templatenameVariants()`):** Формирует приоритетный список возможных имен файла шаблона на основе имени класса (например, `page.имя.twig`, `public.имя.twig`, `admin.имя.twig` и т.д.).
* **Рекурсивное сканирование каталогов (`dirList()`):** Собирает все подкаталоги из `TWIG_TEMPLATES_DIR` для инициализации `\Twig_Loader_Filesystem`.
* **Авторегистрация хелперов (`twig__*`):** Любой публичный статический метод класса, имя которого начинается с префикса `twig__`, автоматически регистрируется в Twig под коротким именем (без префикса) через `addFunction`.

### Встроенные Twig-функции

* `inset(...)`: Динамическая подгрузка и рендеринг вложенных компонентов (`\inset\*`), с передачей контекста родительского класса и встроенной поддержкой режима отладки.
* `snippet(...)`: Вызов логических сниппетов (`\snippets\*`).
* `headLinkFile(string $line)`: Генерация тегов `<script>`, `<link rel="stylesheet">` или Less с автоматическим проставлением версии по времени модификации файла (`?mtver=timestamp`).
* `jsonjs(mixed $data)`: Безопасное кодирование данных в JSON-формат для внедрения прямо в JS-скрипты на странице.
* `text(...)`, `sysLabel(...)`: Интеграция с локализацией (`\lang`).
* `debugOut(...)`, `is_localhost()`, `is_error()`, `errors()`, `empty()`: Вспомогательные утилиты для отладки и проверки состояния приложения прямо из шаблона.

---

## Компоненты ядра (`/lib-php/core`)

### `\core\lang` и псевдоним `lang`

Абстрактный класс `\core\lang` (и соответствующий глобальный класс-наследник `lang extends \core\lang`) отвечает за загрузку, кэширование и получение локализованных строк.

#### Архитектура и кэширование

* **Кэш сессии:** Все загруженные словари сохраняются в `$_SESSION['__lang_cache'][$code]`. Идентификатор активного языка хранятся в `$_SESSION['__lang_code']`.
* **Каскадная сборка переводов (`buildCache`):** Для предотвращения повторного чтения файлов при каждом запросе словарь строится один раз за сессию. Метод проходит по массиву `$_ROOTFOLDERS` в обратном порядке (`array_reverse`), считывая JSON-файлы формата `*_code.json` из поддиректорий `language/`.
* **Приоритет конфигураций:** За счет использования `array_replace_recursive` переводы из конечных проектов (верхних уровней) переопределяют базовые строки ядра библиотеки.

#### Ключевые методы

* `public static function Init(): void` — определяет язык из запроса (`$_GET['lang']` / `$_POST['lang']`) или текущей сессии (по умолчанию `'ru'`), инициализирует и проверяет наличие валидного словаря.
* `public static function Text(mixed ...$args): string|false` — извлекает переведенную строку по цепочке ключей. Первая секция задает имя словаря (например, `'labels'`), последующие аргументы передаются в `array_var::get()` для безопасного обхода вложенных ключей.
* `public static function Get(): mixed` — возвращает массив структуры `['langCode' => static::$code, 'dictionary' => ...]`, предназначенный для синхронизации локализации с клиентом (передается в метод `crazy74.lang.init()` на фронтенде).
* `public static function current(string|bool $newcode = false): string` — переключает или возвращает текущий код языка.
* `public static function ClearCache(): void` — сбрасывает кэш переводов в сессии.

---

### `\core\session` и псевдоним `session`

Абстрактный класс `\core\session` (и глобальный класс `session extends \core\session`) управляет авторизационной кукой, параметрами текущего сеанса и часовым поясом.

#### Ключевые методы

* `public static function timezone(string $value = ''): string` — читает или записывает значение часового пояса пользователя в `$_SESSION['__timezone']`.
* `public static function Create(array $row): void` — регистрирует авторизованного пользователя в `$_SESSION['account']` (сохраняет `id` и флаг `isadmin`), проставляет часовой пояс, обновляет метку `lastvisit` и генерирует зашифрованную авторизационную cookie (`cRazersUniverseCK`) со сроком жизни 7 дней (168 часов).
* `public static function Clear(): void` — сбрасывает `__timezone` в сессии и удаляет авторизационную cookie.
* `public static function is_localhost(): bool` — проверяет, запущен ли скрипт на локальном сервере (анализирует `SERVER_NAME` на содержание строки `localhost`).

---

### `\core\dates` и псевдоним `dates`

Абстрактный класс `\core\dates` (и глобальный класс-наследник `dates extends \core\dates` в `default.class.dates.php`, по аналогии с `lang` и `session`) отвечает за форматирование дат и времени, включая локализацию и русское склонение месяцев.

#### Ключевые методы

* `public static function fmtForMysql(int|string|bool|null $dt = false): string` — приводит произвольное значение даты к формату MySQL (`Y-m-d H:i:s`); при `false`, `null` или пустой строке возвращает текущее время.
* `public static function FormatLocale(int|string|null $dt, ?string $format = null, bool $withTime = true): string` — форматирует дату по локализованному шаблону. Если формат не передан явно, берется из языковой секции `date` (`\lang::getSection('date')`) по ключу `locale_format_with_time` или `locale_format` (в зависимости от `$withTime`), по умолчанию `j.m.Y H:i`. Токены `F`/`M` в шаблоне заменяются на локализованное название месяца из `dateSection['monthsformat']`.
* `public static function fmtRussian(int|string|null $dt, string $format = 'j F Y г.'): string` — форматирование с захардкоженными русскими названиями месяцев в родительном падеже (пары «полное/сокращённое» для каждого месяца), подставляемыми в те же токены `F`/`M` шаблона.
* `public static function fmtSmart(int|string|null $dt, bool $withTime = true): string` — «умное» относительное форматирование: значение `'never'` транслируется в локализованный текст (`\lang::Text('date', 'neverdate')`); для дат, совпадающих с сегодня/вчера/позавчера/завтра/послезавтра, подставляются соответствующие локализованные метки; для остальных дат — откат на `FormatLocale(..., withTime: false)`. При `$withTime = true` к результату добавляется время в формате `H:i`.

Дата на входе принимается как timestamp (`int`) либо как строка, разбираемая `strtotime()`; при нераспознанном значении методы возвращают пустую строку (кроме `fmtForMysql`, который в этом случае отдает текущее время).

---

### `SessionCache`

Класс `SessionCache` предоставляет механизм временного кэширования результатов тяжелых операций (запросов к БД, расчетов) в пределах пользовательской сессии.

#### Принцип работы

* Хранит данные в массиве `$_SESSION['cache']`.
* Автоматически контролирует время жизни кэша через свойство `public static string $interval = '+2 minutes'`.
* **Автоматическое формирование ключей (`getKey`):** Если ключ кэша не передан явно в метод `check()`, класс анализирует стек вызовов через `debug_backtrace()` и генерирует уникальный идентификатор на основе вызывающего класса, метода и скалярных аргументов.

#### Ключевые методы

* `public static function checkTime(): void` — проверяет время создания кэша; при превышении лимита временно очищает `$_SESSION['cache']`.
* `public static function set(string $key, mixed $value): void` — сохраняет значение под указанным ключом.
* `public static function check(mixed ...$args): array` — проверяет наличие ключа и возвращает кортеж `[$exist, $cachedata, $key]`.
* `public static function clear(string|bool $key = false): void` — удаляет конкретный ключ или полностью сбрасывает кэш сессии.

---

## Аккумулирование результатов и ошибок (`staticStore`)

Базовый абстрактный класс `staticStore` и его наследники обеспечивают централизованное накопление служебных данных во время выполнения запроса и их автоматическое внедрение в итоговый JSON-ответ приложения.

### `staticStore`

Содержит статический массив `static::$list` и ключ ответа `static::$resultKey = '___S'`.

* `public static function Result(mixed &$data): void` — внедряет накопленный массив `static::$list` в передаваемый массив или объект по ключу `static::$resultKey`.
* `public static function Clear(): void` — очищает накопленный стек.
* `public static function Exists(): bool` — проверяет, есть ли сохраненные элементы.

### `errors` (`class errors extends staticStore`)

Предназначен для сбора текстовых сообщений об ошибках во время работы приложения.

* Переопределяет ключ ответа: `public static string $resultKey = 'Errors'`.
* `public static function Add(mixed $line): mixed` — добавляет ошибку в общий стек и возвращает переданное значение.

### `changes` (`class changes extends staticStore`)

Используется для фиксации изменений сущностей в процессе выполнения бизнес-логики (например, для нотификации фронтенда о перерисовываемых блоках).

* Переопределяет ключ ответа: `public static string $resultKey = '_changes'`.
* `public static function Add(string|int $category, string|int $uid, mixed $param = 0): void` — регистрирует факт изменения объекта, группируя записи по категориям и идентификаторам (`$list[$category][$uid][] = $param`).

---

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

### `\core\utils`

Статический класс-хелпер общих утилит для обработки строк, форматирования и конвертации данных (`declare(strict_types=1)`):

* `removeBOM(string $str): string` — удаление UTF-8 BOM-символа (`\xEF\xBB\xBF`) из начала строки.
* `get_encoding(string $str): string` — определение кодировки строки (проверка списков `UTF-8`, `CP1251` через `iconv` и MD5-сравнение).
* `to_utf8(string $str): string` / `to_CP1251(string $str): string` — конвертация строки в UTF-8 или CP1251 с автоопределением исходной кодировки.
* `sizebytes(float|int $svalue): string` — форматирование размера байт в читаемый вид (`b`, `Kb`, `Mb`, `Gb`) с округлением.
* `rus_bool(mixed $data, bool $FILTER_NULL_ON_FAILURE = false): ?bool` — трансляция текстовых булевых значений (включая русские `ДА`/`НЕТ`, `YES`/`NO`, `1`/`0`) в `bool`, с поддержкой санитизации через `\sql_exec::clean_str` при его наличии.
* `getGUID(): string` — генерация GUIDv4 в формате `{XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX}`.
* `array_diff(mixed $array1, mixed $array2, bool $strict = false): array` — рекурсивное сравнение двух многомерных массивов с учетом типов данных (строгое/нестрогое).
* `format_phone(string $phone, bool $convert = false, bool $trim = false): string` — форматирование номера телефона под стандартный вид (`+7(XXX) XXX-XXXX` или `+X(XXX) XXX-XXXX`), с возможностью преобразования буквенных номеров (например, 1-800-FLOWERS).
* `mask_phone(string $phone): string` — маскирование средних цифр номера телефона звездочками (например, `+7******1234`).
* `json_indent(string $json): string` — красиво форматированный индент для JSON-строк с переносами и отступами.

### `\core\number` и псевдоним `number`

Абстрактный класс `\core\number` (и глобальный псевдоним `number extends \core\number`) содержит статические методы для форматирования чисел, адаптивного округления, склонения числительных и работы с единицами измерения:

* `smartFormat(int|float|string|null $value, string|bool $unitKey = false, bool $hideOne = false, int $fix_precision = __Numder_Fix_Precision__, int $max_precision = __Numder_Max_Precision__): string` — умное форматирование чисел с адаптивным округлением, сокращением больших порядков (kilo, mega, giga через языковые константы `\lang` при превышении порога `__Numder_Rounding_Threshold__`) и автоматическим склонением единиц измерения из языковой секции `numbers.units`.
* `ending(int|float $num, array $endings = ['штука', 'штуки', 'штук'], string $null = ''): string` — грамматическое склонение существительных по числительным согласно правилам русского языка (1 штука, 2 штуки, 5 штук).
* `round(int|float|string|null $lp_value, string $replacer = '0', string $thousands_sep = '', int $fix_precision = 0, int $max_precision = 4): string` — гибкое округление чисел с отсечением незначащих нулей (в диапазоне от `$fix_precision` до `$max_precision`), настраиваемым разделителем тысяч и значением-заглушкой (`$replacer`) для пустых данных.
* `sizebytes(int|float|string|null $svalue): string` — конвертация и форматирование объема данных в байтах в человекочитаемый вид (`b`, `Kb`, `Mb`, `Gb`).

---

## Инструменты профилирования (`worktimes`)

Класс `worktimes` используется для точного замера и анализа времени выполнения отдельных участков кода в миллисекундах и секундах:

* `start(string|int $id, ?string $title = null, bool $forceIgnore = false): void` — фиксация начальной точки отсчета для контрольной метки.
* `stop(string|int|null $id = null): void` — остановка таймера для конкретной метки или массовая остановка всех незавершенных меток.
* `getstrtime(float|int $tt): string` — форматирование микросекунд в человекпонятные единицы (`ms`, `sec`, `min`).
* `get_list(): array` — получение списка всех замеров, отсортированных по убыванию времени выполнения (`<=>`), что позволяет быстро находить узкие места (bottlenecks) в скриптах.

---

## Сервисы отладки и обработки ошибок

* `class.errors.php` — перехват системных ошибок и исключений PHP, логирование и форматирование стека вызовов.

### `DebugHandler` (`class.abstract.DebugHandler.php`) — общий диспетчер отладочного вывода

Абстрактный класс-предок для `console` и `debug`. Не содержит публичного API сам по себе — методы вызываются через `__callStatic()`, который транслирует `SomeClass::foo(...)` в защищённый `_foo__static(...)`, если такой метод существует у наследника; иначе выводит список доступных методов (диагностика опечатки).

* **Управление выводом:** `public static bool $forcedOutput` — если `false` (по умолчанию), вывод глушится константой `PHPDEBUG_MODE_OUTPUT` (когда она определена и равна `false`). Действует одинаково на всех наследников `DebugHandler` разом.
* **Фильтрация трассировки:** `public static array $skipfiles` — список файлов, кадры которых исключаются из `backtrace()`/`lineFile()` (каждый наследник добавляет себя: `console::$skipfiles[] = __FILE__;`).
* **Базовый набор методов** (доступны и `console::`, и `debug::` без переопределения): `outecho()`/`echo()` — эхо переменной с заголовком (`struc`/`dump`/`textarea`/`table`), `memory()` — текущее потребление памяти, `trace()`/`logTrace()` — текущий стек вызовов, `group()`/`groupEnd()` (алиасы `echoGroup()`/`echoGroupEnd()`) — вложенные визуальные блоки с замером времени (через `worktimes`, если доступен) и памяти, `table()` — табличный вывод массива/списка объектов, `groupFunc()` — автоматический `group()` с заголовком, сгенерированным из имени и аргументов вызывающей функции.

### `console` (`class.console.php`) — вывод в браузер

Тонкая статическая обёртка над `DebugHandler`: `log()` (тип `struc`), `dump()` (`var_dump`), `text()` (текст в `<textarea>`) — явные переопределения, плюс проброс `memory()`/`trace()`/`group()`/`groupFunc()`/`groupEnd()` из предка. Используется для отладочного вывода прямо на страницу в обход шаблонизатора (в режиме `?debug=yes`).

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

---

## Интеграция с AI-провайдерами (`namespace AI`)

Компоненты пространства имен `AI` реализуют единый интерфейс для работы с внешними LLM-провайдерами (Google Gemini, OpenAI-совместимые API) поверх `\REST\HTTP`, разделяя формирование запроса, отправку и разбор ответа на три независимых слоя: `Request` (что спрашиваем), `Response` (в каком формате ждем ответ и как его накапливаем) и наследники `API` (как именно обращаемся к конкретному провайдеру).

### `AI\API` — базовый шаблон запроса

Не абстрактный класс, но рассчитан на наследование по схеме Template Method: `MakeREST()`, `MakeDATA()`, `ParseAnswer()` в базовой реализации — заглушки/дефолты, переопределяемые в провайдер-специфичных наследниках.

* `public function Request(Request $request, Response $response): void` — основной цикл: получает REST-клиента через `MakeREST()` (если результат не объект или не содержит метод `request`, выполнение тихо прерывается), собирает тело запроса через `MakeDATA()`, выполняет `$rest->request($data)`, передает сырой ответ в `ParseAnswer()`, а результат — в `$response->Process()`.
* `protected function ParseAnswer(mixed $answer): mixed` — в базовом классе возвращает ответ без изменений; провайдеры переопределяют для нормализации в общий формат (`text_list`/`error`/`raw`).
* `protected function MakeDATA(Request $request, Response $response): array` — дефолтная сборка тела в формате Gemini (`contents[0].parts[]`): сначала системные инструкции из `$response->getFormat()`, затем содержимое `$request->get()`, каждый элемент — отдельный `['text' => ...]`.
* `protected function MakeREST(): ?object` — в базовом классе возвращает `null` (запрос не выполнится); переопределяется в `GoogleType`/`OpenAIType`.

### `AI\Request`

Накопитель содержимого запроса: разделяет статичные «системные» инструкции (`defines`) и динамические пользовательские данные (`data`).

* Конструктор `__construct(array $def = [])` — принимает начальный набор инструкций/трейнер-файлов.
* `public function Add(mixed $val): void` — добавляет элемент в пользовательские данные.
* `public function AddDefine(mixed $val): void` / `public function AddTrainer(mixed $val): void` — алиасы, добавляют элемент в `defines`.
* `public function AddTrainerFile(string $file, string $title = '', string $location = ''): bool` — читает файл-трейнер с диска (`is_readable`/`file_get_contents`), по расширению определяет `Content-Type` (`json` → `application/json`, `md`/`markdown` → `text/markdown`, иначе `text/plain`), формирует псевдо-HTTP-заголовок (`Content-Type`, опционально `Content-Title`, `Content-Location`) и добавляет его вместе с содержимым файла как единый элемент `defines` через `AddTrainer()`. Возвращает `false`, если файл не читается или пуст.
* `public function get(): array` — возвращает `defines` и `data`, объединенные через `array_merge()` (порядок: сначала инструкции, затем пользовательский ввод).

### `AI\Response` и `AI\ResponseJSON`

Базовый класс-получатель ответа, нормализующий результат работы провайдера в единый плоский вид.

* `public const ?string FORMAT = null` — системная инструкция по умолчанию (отсутствует); переопределяется в наследниках.
* `public bool $success`, `public mixed $answer` (сырой ответ провайдера), `public mixed $error` — публичное состояние после обработки.
* `public function getFormat(): ?array` — оборачивает `static::FORMAT` в массив из одного элемента либо возвращает `null`, если формат не задан.
* `public function ParseAnswerText(string $text): void` — накопление одного распознанного текстового фрагмента во внутренний `$data`.
* `public function Process(mixed $parsedResponse): void` — принимает результат провайдер-специфичного `ParseAnswer()` в ожидаемом формате (`text_list`, `error`, `raw`): для каждого элемента `text_list` вызывает `ParseAnswerText()` и выставляет `success = true`; сохраняет `error`/`raw`, если ключи присутствуют. Содержит отключаемую флагом `$debug` трассировку через `console::groupFunc()`/`console::log()`.
* `public function get(): string` / `public function toArray(): array` — отдача накопленного текста строкой (через `\r\n`) или массивом.

`ResponseJSON extends Response` переопределяет `FORMAT` жесткой системной инструкцией на русском («Отвечай ТОЛЬКО чистым JSON без пояснений и Markdown-разметки»), принуждая модель отвечать чистым JSON без пояснений.

### `AI\GoogleType` — адаптер Google Gemini

Абстрактный наследник `API`, инкапсулирующий формат запроса/ответа Gemini Generative Language API.

* Константы, переопределяемые в конкретных провайдерах: `MODELS` (карта доступных моделей), `API_KEYS` (пул ключей для ротации), `SESSION_KEY` — ключ хранения индекса текущего API-ключа в сессии.
* `SetModel(string|int|false $model): void` — принимает либо имя модели строкой, либо числовой индекс (резолвится через `array_keys(static::MODELS)`); в конструкторе, если модель не задана явно, выбирается модель с индексом 0.
* `protected function MakeREST(): ?\REST\HTTP` — при пустом `API_KEYS` возвращает `null`; иначе выбирает ключ по круговой ротации (счетчик в `$_SESSION[SESSION_KEY]`, инкремент с обнулением по достижении `count($keys)`), формирует `\REST\HTTP` с URL вида `.../models/{model}:generateContent?key={apikey}`, `COMMAND_IN_PARAMS`, отключенной проверкой SSL-сертификата и JSON-телом.
* `protected function MakeDATA(...): array` — дополняет базовую Gemini-структуру полем `generationConfig.responseMimeType = 'application/json'`, требуя JSON-ответ на уровне API независимо от текста системной инструкции.
* `protected function ParseAnswer(mixed $answer): array` — разбирает `candidates[].content.parts[].text` в плоский `text_list`, извлекает `error`, сохраняет исходный ответ в `raw`.

### `AI\OpenAIType` — адаптер OpenAI-совместимых API

Аналогичный по назначению адаптер для чат-эндпоинтов формата OpenAI (`/chat/completions` и совместимые).

* Те же константы, что и у `GoogleType` (`MODELS`, `API_KEYS`, `SESSION_KEY`), плюс `API_URL` — фиксированный адрес эндпоинта (в отличие от Gemini, URL не собирается динамически по имени модели).
* Выбор модели (`SetModel`) и ротация ключей в `MakeREST()` реализованы независимо от `GoogleType`, дублируя ту же логику; отличие — ключ передается не в query-параметре, а через `$http->token` (авторизация заголовком), а `webhookUrl` берется напрямую из `static::API_URL`.
* `protected function MakeDATA(&$request, &$response): array` — сигнатура без типов параметров (в отличие от `API::MakeDATA(Request $request, Response $response)`), с ручной проверкой типа через `is_a($request, 'AI\Request')`/`is_a($response, 'AI\Response')` вместо декларации типов. Собирает тело в формате OpenAI: `model`, `messages[]`, где системный формат передается с `role: 'system'`, а элементы запроса — с `role: 'user'` (приведение к строке, пропуск `null`/пустых значений).
* `protected function ParseAnswer($answer): array` — разбирает `choices[].message.content` в `text_list`, аналогично извлекает `error` и сохраняет `raw`.
