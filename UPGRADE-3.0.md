# Upgrading from 2.x to 3.0

Applications that let `ErrorListenerFactory` build the listener (the
default, through `Module`) need no code changes. The configuration keys and
template variables are unchanged, and `config[errors][debug]` is new.

## `ErrorListener`'s constructor takes `ErrorListenerOptions`

The view template moved out of the constructor and into
`ErrorListenerOptions`, alongside the new `debug` flag. The logger is now
the second argument.

Before:

```php
$listener = new ErrorListener($repository, 'site/error-page', $logger);
```

After:

```php
use Contenir\Errors\Laminas\Mvc\ErrorListenerOptions;

$listener = new ErrorListener(
    $repository,
    $logger,
    new ErrorListenerOptions(viewTemplate: 'site/error-page'),
);
```

To build the options from configuration, use
`ErrorListenerOptions::fromConfig($config['errors'])`.

## `config[errors][debug]` must be a boolean

`debug` is new, so existing configuration is unaffected. Setting it to
anything other than `true`, `false` or `null` throws a `RuntimeException`
when the listener is built.
