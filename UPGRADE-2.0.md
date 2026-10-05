# Upgrading from 0.x to 2.0

| | 0.x | 2.0 |
| --- | --- | --- |
| PHP | ^8.1 | 8.3, 8.4 or 8.5 |
| `contenir/errors` | ^0.1 | ^0.1 or ^2.0 |
| `laminas/laminas-mvc` | ^3.4 | ^3.8 |
| `laminas/laminas-view` | ^2.0 | ^2.32 |
| `laminas/laminas-http` | ^2.0 | ^2.19 |
| `laminas/laminas-eventmanager` | ^3.0 | ^3.11 |
| `laminas/laminas-servicemanager` | ^3.0 | ^3.22 |

To upgrade, update the constraint:

```bash
composer require contenir/errors-laminas-mvc:^2.0
```

Most applications need no code changes: the configuration keys, the
listener's behaviour and the template variables are unchanged.

## `Module` is final

`Module` could be extended in 0.x. It is now `final readonly`, and its
constants are typed (`public const int RENDER_PRIORITY`).

Before:

```php
class MyModule extends \Contenir\Errors\Laminas\Mvc\Module
{
    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        // ...
    }
}
```

After: register the package module as usual and put your own bootstrap
logic in your own module. To attach the listener elsewhere, compose:

```php
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Contenir\Errors\Laminas\Mvc\Module;

(new Module())->attachListener($events, $container->get(ErrorListener::class));
```

`ConfigProvider`, `ErrorListener` and `ErrorListenerFactory` were already
`final`; they are now also `readonly`, which changes nothing for callers.

## Behaviour changes

Configuration that used to fail now falls back to the defaults:

```php
// 'errors' => null, or 'errors' => 'oops'
// 0.x: TypeError "Unsupported operand types" when the listener is built
// 2.0: the defaults (shipped template, no logger, built-in pages)

// 'errors' => ['view_template' => null]
// 0.x: ViewModel template '' and a renderer failure
// 2.0: 'contenir/errors/fault'

// 'errors' => ['pages' => [404 => ['title' => ['oops'], 'body' => '<p>x</p>']]]
// 0.x: "Array to string conversion" warning, title 'Array'
// 2.0: title ''
```

The logged URI is taken only from a `Laminas\Http\Request`. 0.x called
`getUri()` on any request object that had one. Laminas MVC HTTP requests
are `Laminas\Http\Request` instances, so ordinary applications log the same
messages as before.

Projects that must stay on PHP 8.1 or 8.2 can keep using `^0.3`, which is
maintained on the `0.x` branch.

## Package renamed in 2.1

From 2.1, the package is published as `contenir/contenir-errors-laminas-mvc`.
It declares `replace` for `contenir/errors-laminas-mvc`, so the two can never
be installed together. Switch the requirement:

```bash
composer remove contenir/errors-laminas-mvc && composer require contenir/contenir-errors-laminas-mvc:^2.1
```

2.1 also requires `contenir/contenir-errors` `^2.1` (the renamed
`contenir/errors`) instead of `contenir/errors`. If you require
`contenir/errors` directly, switch that requirement as well.

No code changes are needed: namespaces, classes, the module name and the
`contenir/errors/fault` template name are unchanged.
