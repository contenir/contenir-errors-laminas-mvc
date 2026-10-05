# contenir/contenir-errors-laminas-mvc

Formerly `contenir/errors-laminas-mvc`; the old package is abandoned in favour of this one.

[![Continuous Integration](https://github.com/contenir/contenir-errors-laminas-mvc/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-errors-laminas-mvc/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-errors-laminas-mvc/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-errors-laminas-mvc)

Laminas MVC adapter for [`contenir/contenir-errors`](https://github.com/contenir/contenir-errors).

Replaces the framework's default 4xx/5xx rendering with admin-authored
per-status pages, falling back to built-in pages for 403, 404 and 500.
Any other status the admin has not authored keeps the framework's default
rendering.

## Requirements

- PHP 8.3, 8.4 or 8.5
- `contenir/contenir-errors` `^2.1`
- `laminas/laminas-mvc` ^3.8, `laminas/laminas-view` ^2.32,
  `laminas/laminas-http` ^2.19, `laminas/laminas-eventmanager` ^3.11,
  `laminas/laminas-servicemanager` ^3.22
- `psr/container` ^1.1 or ^2.0, `psr/log` ^1.0, ^2.0 or ^3.0

The 0.x releases, which support PHP 8.1, remain available from the `0.x`
branch and `v0.*` tags; see [UPGRADE-2.0.md](UPGRADE-2.0.md).

## Installation

```bash
composer require contenir/contenir-errors-laminas-mvc
```

`laminas/laminas-component-installer` registers the module
`Contenir\Errors\Laminas\Mvc` for you. Without it, add that name to your
application's module list.

## Configuration

Everything lives under the `errors` key. All of it is optional.

```php
// config/autoload/errors.global.php
return [
    'errors' => [
        'view_template' => 'contenir/errors/fault', // the default
        'logger'        => 'log.psr3',              // optional PSR-3 logger
    ],
];
```

| Key | Default | Meaning |
| --- | --- | --- |
| `pages` | `[]` | Admin-authored pages, keyed by status: `[404 => ['title' => '…', 'body' => '…']]` |
| `view_template` | `contenir/errors/fault` | Template rendered for an intercepted error. A missing, `null` or empty value uses the default. |
| `logger` | `null` | `null`, a service name resolving to a `Psr\Log\LoggerInterface`, or a logger instance. Anything else throws a `RuntimeException` when the listener is built. |

### Where the pages come from

The admin side of the CMS writes pages with
`Contenir\Errors\Repository\FileRepository` (from `contenir/contenir-errors`) to a
file such as `config/autoload/errors.local.php`:

```php
return [
    'errors' => [
        'pages' => [
            404 => ['title' => 'Page not found', 'body' => '<p>Try the <a href="/">homepage</a>.</p>'],
        ],
    ],
];
```

Laminas merges that file into the application config at boot, and
`ErrorListenerFactory` builds an in-memory repository from it:

1. The built-in pages in `ConfigProvider::DEFAULT_PAGES` (403, 404, 500)
   are seeded first.
2. Each entry in `errors.pages` replaces the default for its status. Rows
   whose key is not an integer, or whose value is not an array, are
   ignored. A missing or non-scalar `title`/`body` reads as `''`.

To use your own storage instead, register a service named
`Contenir\Errors\ErrorPageRepositoryInterface`; the factory then uses it
and ignores both the defaults and `errors.pages`.

## How it works

`Module::onBootstrap()` pulls `Listener\ErrorListener` from the service
manager and attaches it to two events:

| Event | Priority | Why |
| --- | --- | --- |
| `MvcEvent::EVENT_RENDER` | `Module::RENDER_PRIORITY` (100) | Dispatch-time statuses (404 from the route-not-found strategy, a controller's 403) are already set. |
| `MvcEvent::EVENT_RENDER_ERROR` | `Module::RENDER_ERROR_PRIORITY` (-100) | Runs after Laminas's `ExceptionStrategy` (priority 1) has set the 500. |

For an HTTP response with a status of 400 or more, the listener:

1. Triggers `pagecache.disable` on the application's event manager, so
   [`contenir/contenir-cache-laminas-mvc`](https://github.com/contenir/contenir-cache-laminas-mvc)
   does not store the error response. Without that package the event is a
   no-op.
2. Logs the request through the optional logger: `info()` for 4xx,
   `error()` for 5xx with the event's `exception` parameter in the context
   when there is one. The message is `HTTP <status> at <uri>`.
3. If the repository has a non-empty page for the status, sets the
   template and the variables `status`, `title` and `body` on the result
   `ViewModel` (creating one if the result is not a `ViewModel`), marks it
   terminal so the layout is skipped, and sets it as the event's view
   model.

Statuses below 400 and non-HTTP responses are left alone.

You can also wire the listener yourself:

```php
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Contenir\Errors\Laminas\Mvc\Module;

$listener = new ErrorListener($repository, 'site/error-page', $logger);
(new Module())->attachListener($application->getEventManager(), $listener);
```

`ConfigProvider` returns the same configuration as `Module::getConfig()`
(`service_manager`, `errors` defaults and `view_manager`), split into
`getDependencies()`, `getErrorsDefaults()` and `getViewManagerConfig()`.

## The view

The shipped `contenir/errors/fault` template is self-contained: no layout,
inline styles, system fonts, `<meta name="robots" content="noindex">`, the
`.fault` BEM block (`.fault--<status>` on `<body>`) and a single "Return
home" link. The title is escaped; the body is rendered raw.

To brand the page beyond the body field, point `errors.view_template` at
your own template. It receives:

| Variable | Type | Notes |
| --- | --- | --- |
| `$status` | `int` | HTTP status code (e.g. 404) |
| `$title` | `string` | Plain text written by the admin |
| `$body` | `string` | Sanitized HTML fragment (inline only); render raw |

The body is *trusted*: sanitization is the writer's responsibility (see
the admin-side wiring in the consuming CMS). Render it with `<?= $body ?>`.

## Development

The QA toolchain is [php-db/phpdb-qa-tools](https://github.com/php-db/phpdb-qa-tools).
[Mago](https://mago.carthage.software/) is a standalone binary, installed
separately (`brew install mago`).

```bash
composer check             # everything below
composer cs-check          # mago format --check && mago lint
composer static-analysis   # mago analyze
composer test              # unit suite: listener, factory, module and config, collaborators doubled
composer test-integration  # integration suite: real event manager, service manager and view renderer
composer test-coverage     # both suites, clover.xml for Codecov
composer mutation-test     # Infection mutation testing over both suites
```

## License

MIT. See [LICENSE](LICENSE).
