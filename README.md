**English** | [Русский](README_RU.md)

---

# lib.crazy74

A standalone, universal component library for PHP (8.2+) and JavaScript, designed as a core foundation for web applications without hard dependencies on Composer or monolithic external frameworks.

The library is delivered as an independent module that can be integrated into any PHP project.

---

## Is it a Library or a Framework?

Both — depending on how you integrate it.

* **`lib-php`** and **`lib-js`** are completely decoupled layers. Each can be used independently in an existing project: the backend core does not require the frontend JS layer, and `lib.crazy74.js`/`lib.crazy74.jquery.js` function seamlessly as standalone frontend utilities on any web page without the PHP backend.
* **Together**, combined via a unified entry point (`common.php` → `App::startup()` / `App::ajax()`), a shared class autoloader (`SharedCommon::auto_load`), and a unified localization layer, both layers form a **lightweight web framework**: single entry point, page controllers (`TwigPage`), AJAX dispatcher, database abstraction, and debugging services — providing essential core architecture without bloat or rigid framework conventions.

The core is intentionally minimalistic and does not attempt to cover every scenario out-of-the-box — instead, it is **designed as an extensible base**. Nearly all key PHP classes (`\core\lang`, `\core\session`, `\core\dates`, `App`, `TwigPage`, `AI\API`, `AI\Response`, etc.) are implemented as abstract classes or designed specifically for class inheritance rather than direct usage:

* `lang`, `session`, `dates` — global aliases (`class X extends \core\X`) allowing project-level code to override or extend core functionality without modifying library source code.
* `App` and `TwigPage` — base abstractions for application controllers and page rendering; specific application pages and services inherit from them.
* `AI\API`, `AI\Request`, `AI\Response` — built around the Template Method design pattern, requiring only thin provider-specific subclasses for individual LLM services (e.g., `AI\GoogleType`, `AI\OpenAIType`).

As a result, the library can be extended infinitely for any specific project by registering custom root directories in `$_ROOTFOLDERS`, adding language dictionaries, custom controllers, and child classes, all while keeping `lib.crazy74` source code untouched.

---

## Architectural Principles

* **Full Autonomy:** PHP and JS components operate without third-party package managers or complex build pipelines.
* **Extensibility via Inheritance:** The PHP core is designed as a set of base classes and abstractions — application code integrates via inheritance rather than modifying library source code.
* **Unified Localization Layer:** Shared dictionaries and language data structures accessible simultaneously across PHP backend and JS frontend.
* **Strict Typing:** The PHP codebase is optimized for PHP 8.2+ with mandatory `declare(strict_types=1)` support.

---

## Library Structure and Documentation

### 1. Frontend Layer (`/lib-js`)
Contains the standalone client-side JavaScript library and jQuery plugins, usable independently from the PHP layer:
* `lib.crazy74.js` — core JavaScript library (DOM manipulation, object utilities, Cookie & Storage management, event handling, localization).
* `lib.crazy74.jquery.js` — extensions and UI plugins for jQuery.

Detailed Documentation:
* [lib.crazy74.js Documentation](lib.crazy74.md)
* [lib.crazy74.jquery.js Documentation](lib.crazy74.jquery.md)

### 2. Backend Core (`/lib-php`)
Contains core abstractions and backend services designed for project-level inheritance:
* Class and trait autoloader (`SharedCommon::auto_load`).
* Base application abstraction (`App`).
* MySQL database wrapper (`\core\mysqli_db`).
* Twig templating and rendering engine (`TwigPage`).
* Error handling, logging, and debugging services (`errors`, `debug`, `console`).
* LLM provider integration module (`AI\API`, `AI\Request`, `AI\Response`, `AI\GoogleType`, `AI\OpenAIType`).

Detailed Documentation:
* [PHP Components Description (php-shared-lib.md)](php-shared-lib.md)

### 3. Demo Application (`/demo`)
A reference site implementation based on `lib-php` + `lib-js`, demonstrating end-to-end layer integration (single entry point, page controllers, AJAX, AI integration). See [PROJECT.md](PROJECT.md).

---

## Integration and Class Autoloading (PHP)

Class and trait autoloading is handled by the static method `SharedCommon::auto_load()`, which is registered via `spl_autoload_register()`.

The `auto_load` method recursively scans directories listed in the global `$_ROOTFOLDERS` array for class and trait files matching these naming conventions:
* `class.ClassName.php`
* `abstract.ClassName.php`
* `ClassName.class.php`
* `trait.TraitName.php`
* `TraitName.trait.php`

Including `common.php` triggers `App::startup()` immediately. This behavior is controlled by the `APP_AUTOSTART` constant (defaults to `true`) — set `APP_AUTOSTART` to `false` before `require_once` if you only need the autoloader without auto-starting the application. The legacy Twig 1.x autoloader is optionally included: if `Twig/Autoloader.php` is missing or unreadable, `common.php` gracefully skips it without throwing a fatal error.

### Entry Point Setup Example

```php
// 1. Define root directories for autoloader scanning.
// SharedCommon::auto_load() recursively searches for class and trait files
// matching target patterns across all subdirectories of these root paths.
// Using __DIR__ for relative paths is recommended.
$_ROOTFOLDERS = [
    __DIR__ . '/lib-php/', // Core library directory for lib.crazy74
    // ... additional project-specific root directories,
    // e.g., __DIR__ . '/app/',
];

// 2. (Optional) Disable automatic application startup on common.php load
// define('APP_AUTOSTART', false);

// 3. Include the core library bootstrap file
require_once __DIR__ . '/lib-php/common.php';

// 4. Register the autoloader
spl_autoload_register(['SharedCommon', 'auto_load']);

```

This provides a flexible and extensible autoloading architecture for all library components. Adding custom root directories to `$_ROOTFOLDERS` enables application-level subclasses to integrate seamlessly into the core autoloader framework.

---

## AI Assistant Instructions

The library is designed from the ground up to support code generation and maintenance by LLMs (Claude, Gemini, GPT, etc.). To facilitate this, the repository includes **trainer files** — technical reference manuals detailing API behaviors, architectural constraints, and subtle edge cases not evident from code inspection alone:

* [`lib.crazy74.md`](https://www.google.com/search?q=lib.crazy74.md) — Server-side PHP core behavior.
* [`lib.crazy74.jquery.md`](https://www.google.com/search?q=lib.crazy74.jquery.md) — Client-side JS/jQuery layer behavior.
* [`php-shared-lib.md`](https://www.google.com/search?q=php-shared-lib.md) — In-depth reference for PHP components (autoloader, `App`, `TwigPage`, DB, AI integration, etc.).
* [`PROJECT.md`](https://www.google.com/search?q=PROJECT.md) — Architecture overview of the repository and demo application.

Before generating or modifying code in this repository, AI assistants MUST:

1. Read the relevant trainer file(s) for the affected layer (`lib-php` / `lib-js`) rather than relying solely on raw source code or generic PHP/jQuery knowledge.
2. Pay special attention to the "Common Code Generation Pitfalls" sections at the end of each trainer file — these highlight critical behavioral differences (e.g., `$.isEmpty` vs `crazy74.array.notEmpty`, seconds vs milliseconds in date utilities, dual `maxZIndex` behavior).
3. Extend core functionality **via inheritance**, never by modifying library base classes directly — this applies to `lang`, `session`, `dates`, `App`, `TwigPage`, `AI\API`, and their subclasses.
4. To attach trainer files directly to LLM API prompts within the application, use the built-in helper `AI\Request::AddTrainerFile()` — it reads the file from disk and wraps it as a pseudo-HTTP header with the proper `Content-Type` (`md`/`markdown` → `text/markdown`).

---

## Author

Developed and maintained by AlexCRAZY74 (Almaty, KZ):

* [alexcrazy74.taplink.kz](https://alexcrazy74.taplink.kz/)
* [linktr.ee/AlexCRAZY74](https://linktr.ee/AlexCRAZY74)

