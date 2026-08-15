# crazy74 — тренер-файл (core library)

Файл: `lib.crazy74.js`
Формат: IIFE, вешает объект `crazy74` в `window.crazy74` (мёржится с уже существующим, если файл переподключается). Не зависит от jQuery. Должен подключаться **до** `lib.crazy74.jquery.js`.

Структура: `crazy74.{lang, number, array, date, string, uuid, bool}` — набор синглтон-менеджеров.

---

## crazy74.array — ArrayVarManager

Работа с вложенными объектами по пути ключей.

- `get(target, key, defaultValue = null)`
  `key` — строка или массив-путь (`['a','b','c']`). Безопасно спускается по вложенности, на любом undefined/null возвращает `defaultValue`.
- `set(target, key, value, operand = '=')`
  Пишет по пути, создавая промежуточные объекты `{}` при необходимости.
  `operand`: `'='`, `'+='`, `'-='`, `'*='`, `'/='`, `'.='`, `'|='`, `'&='`, `'^='`, `'%='` (для `'.='` — конкатенация строк через `String()`).
- `get_bool(target, key, defaultValue = false)` / алиас `getBool` — приводит значение через `crazy74.bool` (парсит `'true'/'yes'/'1'/'да'` и т.п.).
- `get_array(target, key, defaultValue = false)` / алиас `getArray` — возвращает значение, только если это непустой массив, иначе `defaultValue`.
- `merge(...args)` — как jQuery `$.extend`. Первый аргумент может быть `boolean` = deep-merge флаг. Остальные — объекты-источники (не массивы, массивы как значения **не** мержатся, а перезаписываются целиком). `undefined`-значения из источников игнорируются.
- `notEmpty(val)` — `false` для `null/undefined` и пустых объектов, `true` для всего остального (включая `0`, `''`, `false`!). ⚠️ Не путать с `$.isEmpty` из jquery-файла — логика разная для строк/чисел.
- `clone(obj)` — глубокое клонирование через `new obj.constructor()` + рекурсия по own-properties. Работает и с массивами.
- `assignData(target, data, excludes)` — копирует (с `clone`) свойства `data` в `target`, кроме ключей из массива `excludes`.

## crazy74.lang — LangManager

- `code` (по умолчанию `'ru'`), `dictionary` (`{}` по умолчанию).
- `init(langCode, initialData)` — задаёт код языка и словарь целиком.
- `Text(...args)` / алиас `get(...args)` — достаёт строку из словаря.
  Вызов либо `Text(['a','b'], fallback)` (массив-путь + явный fallback), либо `Text('a','b',...)` (varargs = путь). Если fallback не задан — генерируется `'[a::b]'`.
  Использует `crazy74.array.get` под капотом.

## crazy74.number — NumberManager

- `ending(num, endings = ['штука','штуки','штук'], nullText = '')` — русское склонение по числу. `0` → `nullText`. Дробные → `endings[1]` (родительный ед.). Целые — стандартная логика 11-19 → `[2]`, `*1` → `[0]`, `*2-4` → `[1]`, иначе `[2]`. Возвращает строку **с ведущим пробелом**.
- `sizebytes(bytes)` — форматирует байты в `b/Kb/Mb/Gb/Tb`, десятичный разделитель — запятая, до 2 знаков (лишние нули отбрасываются).
- `smartFormat(value, unitKey = false, hideOne = false, fixPrecision = 0, maxPrecision = 4)` — компактный формат больших чисел с k/M/G-суффиксами (порог `10000`). `unitKey` — ключ единицы в словаре `crazy74.lang.dictionary.numbers.units[unitKey]` (массив окончаний для склонения или строка). `hideOne` — если `value === 1` и есть окончания, вернуть только `endings[0]`. Суффиксы k/M/G берутся из `dictionary.numbers.postfix.{kilo,mega,giga}` (fallback `'k'/'M'/'G'`).
- `withEnding(number, endings, nullStr = '&nbsp;')` — `"N окончание"` строкой, либо `nullStr` если число по модулю `0`.

⚠️ `smartFormat` и `ending` завязаны на `crazy74.lang.dictionary` — без предварительного `crazy74.lang.init(...)` с нужными ключами используют fallback-значения или английские дефолты.

## crazy74.date — DateManager

- `parseDate(dt)` — универсальный парсер:
  - `Date` → как есть (или `null` если invalid).
  - `number` → если `< 1e11`, трактуется как unix-время в **секундах** (`*1000`), иначе — уже в мс.
  - `string`: пусто или `'never'` → `null`; чисто цифровая строка → как number-ветка выше; иначе строка нормализуется (`' '` → `'T'`) и парсится как ISO.
  - иначе → `new Date()` (текущее время).
- `formatLocale(dt, options = {})` — форматирование по PHP-подобным токенам: `j`(день без нуля) `d`(день с нулём) `n`(месяц без нуля) `m`(месяц с нулём) `Y`(год) `y`(год 2 цифры) `H` `i` `s` `F`/`M`(название месяца из `dictionary.date.monthsformat`, массив по индексу `month-1` или объект по ключу `month`).
  `options.format` если не задан — берётся из словаря (`locale_format[_short][_with_time]`), дефолт `'j.m.Y H:i'` / `'j.m.Y'`.
  `dt === 'never'` → строка "никогда" (или из словаря `date.neverdate`). Алиас `FormatLocale`.
- `fmtSmart(dt, options = {})` — "сегодня/вчера/позавчера/завтра/послезавтра HH:MM", иначе полная дата без времени. Диапазон дней жёстко от -2 до +2, дальше — `formatLocale`. Все подписи — из `dictionary.date.*` с русскими fallback-ами.
- `fmtAgo(dt)` / алиас `timeAgo` — "N секунд/минут/часов назад" с русским склонением через `crazy74.number.ending`. `< 10 сек` → "только что". Если разница отрицательна (дата в будущем) или `≥ 24ч` — делегирует в `fmtSmart(d)`.

## crazy74.string — StringManager

- `escapeHtml(text)` — экранирует `& < > " '`. Не-строки возвращает как есть.

## crazy74.uuid — UUID_Manager

- `get(algorithm = 'v4', ...args)`:
  - `'v4'` (default) — RFC4122 v4 через `crypto.getRandomValues`.
  - `'base36'` — `get('base36', length = 16)` — случайная base36-строка.

## crazy74.bool

- `crazy74.bool(value)` — не менеджер, а прямая функция-парсер (используется и как `parseBool` внутри модуля). Строки `'true'/'yes'/'1'/'да'` → `true`; `'false'/'no'/'0'/'нет'` → `false`; иначе `Boolean(value)` только для `boolean`/`number`, для всего остального — `false`.

## crazy74.onPageReady

- `crazy74.onPageReady(callback)` — вспомогательная функция ожидания готовности страницы (обёртка над `StartEvenHandler`). Вызывает переданный `callback` только после наступления обоих условий: готовности DOM (`DOMContentLoaded` либо `document.readyState !== 'loading'`) и полной загрузки всех ресурсов страницы (`window.load`).

---

### Общие ловушки для генерации кода
1. `crazy74.array.merge` **не** мержит массивы-значения глубоко — они просто перезаписываются.
2. Даты в секундах vs миллисекундах различаются порогом `1e11` — при ручном формировании timestamp это нужно учитывать.
3. Локализация (`lang`, `number.ending`, `date.*`) полностью зависит от заранее заполненного `crazy74.lang.dictionary` — без него код рабочий, но со англ./дефолтными подписями.
4. `crazy74.array.clone` использует `obj.constructor()` — не подходит для классов с обязательными аргументами конструктора.
