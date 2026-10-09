# Flames Autoload — AI Developer Instructions

This document describes every class, trait, and global helper in the `flamesphp/autoload` package. Use it as context when working with AI-assisted coding (vibecode) inside a Flames PHP project.

## Package Overview

`flamesphp/autoload` is the **entry-point autoloader** for the Flames PHP framework. It registers a custom PSR-4-style autoloader that resolves classes from:

- Legacy ORM aliases (`Flames\Model`, `Flames\Repository`, `Flames\Database`)
- Flames vendor packages (`Flames\{Package}\*`)
- Application classes (`App\*`)
- Microservice classes (`Microservice\*`)
- A fallback event hook for anything else

It also ships global helper functions (`async`, `await`, `Arr`, `dump`, etc.) loaded from `resources/functions.php`.

**Requirements:** PHP `^8.3`, strict types enabled everywhere.

**External constants expected at runtime:**

| Constant   | Purpose |
|-----------|---------|
| `ROOT_PATH` | Absolute path to the project root (trailing slash). Used to resolve vendor and microservice files. |
| `APP_PATH`  | Absolute path to the application directory (trailing slash). Used to resolve `App\*` classes and subset filtering. |

**External dependencies referenced (not bundled in this repo):**

- `Flames\Framework\PersistentData`, `Flames\Ready\ResetData`, `Flames\Ready\Functions`
- `Flames\Event` (optional, for `LoadEvent` trait)
- `Flames\Env\Env`, `Flames\Subset\Stream\Wrapper`
- `Flames\Async\Async`, `Flames\Collection\Arr`, `Flames\Collection\Functions`
- `Flames\Framework\Shutdown`, `Flames\Framework\Controller\Response`
- `Flames\Dumpper\Dump` (when `DUMP_ENABLED` is true)

---

## File Map

```
Flames/Autoload/
├── Autoload.php          # Main autoloader class
├── C.php                 # Internal async C-extension validator
└── Trait/
    ├── LoadEvent.php     # Fallback load via Event dispatch
    └── Requires.php      # Safe require with optional subset stream wrapper

resources/
└── functions.php         # Global helper functions
```

---

## `Flames\Autoload\Autoload`

The central autoloader. Composes two traits: `LoadEvent` and `Requires`.

### `public static function run(): void`

Registers the autoloader with PHP's `spl_autoload_register`. Every unresolved class name triggers `self::load($name)`.

**When to call:** Once at application bootstrap, before any Flames/App classes are used.

```php
\Flames\Autoload\Autoload::run();
```

### `public static function load(string $name): void`

Core resolution logic. Called automatically by the registered autoloader, but can also be invoked manually to force-load a class.

**Resolution order:**

1. **Legacy ORM aliases** — hard-coded map for backward compatibility:

   | Class name              | File path |
   |-------------------------|-----------|
   | `Flames\Model`          | `{ROOT_PATH}vendor/flamesphp/orm/Flames/Orm/Model.php` |
   | `Flames\Repository`     | `{ROOT_PATH}vendor/flamesphp/orm/Flames/Orm/Repository.php` |
   | `Flames\Database`       | `{ROOT_PATH}vendor/flamesphp/orm/Flames/Orm/Database.php` |

   If matched, loads via `requires()` and returns.

2. **`Flames\{Package}\*` namespaces** — resolves to vendor packages:

   ```
   Flames\Microservice\Foo\Bar
   → {ROOT_PATH}vendor/flamesphp/microservice/Flames/Microservice/Foo/Bar.php
   ```

   The second namespace segment (`Microservice`) is lowercased to form the vendor folder name (`microservice`). If the file exists, it is `require_once`'d directly (not through `requires()`).

3. **`App\*` namespaces** — application classes:

   - Special case: `App\App` loads `{ROOT_PATH}vendor/flamesphp/framework/App.php`.
   - All others: `{APP_PATH}{rest-of-namespace-as-path}.php`
     - Example: `App\Server\Handler\Index` → `{APP_PATH}Server/Handler/Index.php`

   After loading, if the class defines a static initializer `__constructStatic()` **and** the second namespace segment is **not** `Client`, it is called automatically:

   ```php
   App\Server\Foo::__constructStatic();
   ```

   This lets App classes run setup code the moment they are autoloaded.

4. **`Microservice\*` namespaces** — microservice-local classes:

   ```
   Microservice\Server\Handler\Index
   → {ROOT_PATH}Microservice/Server/Handler/Index.php
   ```

   Same `__constructStatic()` behavior as `App\*`, but skips initialization when the **third** segment is `Client` (i.e. `Microservice\Something\Client\...`).

5. **Fallback** — anything not matched above calls `loadEvent($name)` (see `LoadEvent` trait).

**Important for AI agents:**

- Do not add new resolution branches without understanding the order above; earlier branches short-circuit later ones.
- `App\Client\*` and `Microservice\*\Client\*` classes intentionally skip `__constructStatic()` to avoid eager initialization of HTTP/gRPC clients.
- Flames vendor packages use `require_once` directly; App/Microservice paths go through `requires()` which may apply subset filtering.

---

## `Flames\Autoload\C`

`@internal` — not part of the public API. Validates that the native C async extension is loaded and matches the expected version.

### `protected const ASYNC_SERVICE_VERSION`

Hard-coded version hash (`a3f8c2e1b7d94056f2a1c3e8b5d07f4a9c2e6b81`). Must match the return value of the C function `__flames_c_async_service_version__()`.

### `public static function validAsync()`

Returns `bool` indicating whether the async C extension is present and version-compatible.

**How it works:**

1. Wraps the check in `Flames\Ready\Functions::once()` so it runs only once per request/process.
2. Returns `false` if the C function `__flames_c_async_service_version__()` does not exist.
3. Returns `true` only if the C function's return value equals `ASYNC_SERVICE_VERSION`.

**Use case:** Framework internals can gate coroutine/non-blocking behavior on this check. AI agents should not modify the version constant unless coordinating with a C extension rebuild.

---

## `Flames\Autoload\Trait\Requires`

Handles file inclusion with optional **subset stream wrapper** support for sandboxed/partial code loading.

### `private static bool $subsetRegistered`

Tracks whether `Flames\Subset\Stream\Wrapper` has already been registered. Prevents duplicate registration.

### `protected static function requires(string $path): void`

Loads a PHP file by path. This is the standard inclusion method used by `Autoload::load()` for App, Microservice, and legacy ORM paths.

**Behavior:**

1. If `shouldUseSubset($path)` returns `true`:
   - Calls `registerSubset()` to register the `flames://` stream wrapper (once).
   - Loads the file via `require 'flames://' . $path` (subset-filtered content).
2. Otherwise: plain `require $path`.

### `private static function shouldUseSubset(string $path): bool`

Determines whether subset mode applies to a given file path.

**Returns `false` when:**

- `APP_PATH` is not defined.
- `Flames\Ready\ResetData::$data['.env']` is not set (env not bootstrapped yet).
- `Env::get('SUBSET')` is not strictly `true`.

**Returns `true` when:**

- Subset mode is enabled **and** the resolved real path of `$path` starts with the real path of `APP_PATH` (falls back to string prefix check if `realpath()` fails).

**Meaning:** Only application code under `APP_PATH` is filtered through the subset wrapper. Vendor/framework files are always loaded in full.

### `private static function registerSubset(): void`

Registers `Flames\Subset\Stream\Wrapper` once. No-op on subsequent calls.

---

## `Flames\Autoload\Trait\LoadEvent`

Provides a fallback hook when a class name does not match any autoload rule.

This trait is **conditionally defined** at include time based on whether `{APP_PATH}Server/Event/Load.php` exists.

### When `Server/Event/Load.php` exists

```php
protected static function loadEvent(string $name)
{
    Event::dispatch('Load', 'onLoad', $name);
}
```

Dispatches a Flames Event so the application can handle custom class loading (e.g. dynamic plugins, generated classes).

### When `Server/Event/Load.php` does not exist

```php
protected static function loadEvent(string $name) {}
```

No-op stub. Unmatched class names silently fail to load (PHP will throw `Class not found` when the class is actually used).

**For AI agents:** If you need custom autoloading beyond the built-in rules, create `App/Server/Event/Load.php` with an `onLoad` event handler rather than modifying `Autoload.php`.

---

## Global Functions (`resources/functions.php`)

These functions are loaded early in the bootstrap and become globally available.

### Shutdown

#### `__shutdown__(): void`

Internal shutdown handler. Calls `Flames\Framework\Shutdown::onShutdown()`.

Registered via `register_shutdown_function('__shutdown__')` at file load time.

---

### Collection Helpers

#### `Arr(mixed $value = null): Flames\Collection\Arr`

Factory for `Flames\Collection\Arr`.

- If `$value` is already an `Arr` instance, returns it as-is.
- Otherwise wraps `$value` in a new `Arr`.

```php
$items = Arr([1, 2, 3]);
$same  = Arr($items); // no double-wrap
```

---

### Async / Coroutine Helpers

#### `async(Closure $delegate, bool $parallel = true): Flames\Async\Container`

Starts an async task. Delegates to `Flames\Async\Async::async()`.

- `$delegate` — the work to run asynchronously.
- `$parallel` — whether the task may run in parallel with others (default `true`).

Also available as a **named constant** via `define('async', async(...))`, so it can be referenced without calling as a function in some contexts.

#### `await(Flames\Async\Container ...$tasks): mixed`

Waits for one or more async containers to complete. Delegates to `Flames\Async\Async::await()`.

Also defined as a constant: `define('await', await(...))`.

```php
$task = async(fn () => fetchData());
$result = await($task);
```

#### `asleep(float $seconds): void`

Non-blocking sleep inside managed coroutines (when the C extension is loaded and patches `usleep`). In pure PHP mode, falls back to a regular blocking `usleep`.

```php
asleep(0.5); // sleep 500ms
```

---

### Memoization

#### `once(Closure $delegate): mixed`

Runs `$delegate` once and caches the return value for subsequent calls within the same process. Delegates to `Flames\Collection\Functions::once()`.

```php
$config = once(fn () => loadHeavyConfig());
```

---

### HTTP Response

#### `response(mixed $data = null, int $statusCode = 200, ?string $contentType = null): Flames\Framework\Controller\Response`

Creates a new `Response` object for controller handlers.

```php
return response(['ok' => true], 200, 'application/json');
```

---

### Debugging

#### `dump(...$args): void`

Behavior depends on `Env::get('DUMP_ENABLED')`:

**When `DUMP_ENABLED` is truthy:**

- Uses `Flames\Dumpper\Dump` with PhpStorm as the configured editor.
- Registers itself as a dump alias.
- Captures output buffering; if Dump produces no output but arguments were passed, falls back to a JSON `<pre>` block.
- Rewrites HTML links: `Flames\Collection\Arr` → `Arr`, `Flames\Collection\ArrImmutable` → `ArrImmutable`.

**When `DUMP_ENABLED` is falsy:**

- Falls back to PHP's native `var_dump(func_get_args())`.

---

## Autoload Flow Diagram

```
Class requested
       │
       ▼
  Legacy ORM? ──yes──► requires(path) ──► done
       │ no
       ▼
  Flames\{Pkg}\*? ──yes──► require_once vendor file ──► done
       │ no
       ▼
  App\*? ──yes──► requires(app path)
       │              │
       │              ▼
       │         __constructStatic()? (skip if App\Client\*)
       │              │
       └──────────────► done
       │ no
       ▼
  Microservice\*? ──yes──► requires(root path)
       │                       │
       │                       ▼
       │                  __constructStatic()? (skip if *\Client\*)
       │                       │
       └───────────────────────► done
       │ no
       ▼
  loadEvent(name) ──► Event::dispatch('Load', 'onLoad', name) or no-op
```

---

## Conventions for AI-Assisted Development

1. **Class placement:**
   - Framework/vendor code → `vendor/flamesphp/{package}/Flames/{Package}/...`
   - Application code → `App/` under `APP_PATH`
   - Microservice code → `Microservice/` under `ROOT_PATH`

2. **Static initialization:** Add `public static function __constructStatic(): void` to App/Microservice classes that need eager setup. Avoid this on `*Client*` classes.

3. **Custom loading:** Prefer an event handler in `App/Server/Event/Load.php` over modifying `Autoload.php`.

4. **Subset mode:** When `SUBSET=true` in env, only files under `APP_PATH` are filtered. Do not expect vendor files to be subsetted.

5. **Do not rename or remove** the global functions in `resources/functions.php`; they are part of the Flames DSL used throughout the framework.

6. **Strict types:** All files use `declare(strict_types=1)`. Match this in any code you add.
