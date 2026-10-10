<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc;

use RuntimeException;

use function is_bool;
use function is_string;

/**
 * How ErrorListener renders a page: which template, and whether debug mode
 * leaves error responses alone.
 *
 * Mirrors contenir/contenir-errors-mezzio's ErrorPageOptions so both adapters
 * read config[errors] the same way.
 *
 * @api
 */
final readonly class ErrorListenerOptions
{
    public const string DEFAULT_VIEW_TEMPLATE = 'contenir/errors/fault';

    public function __construct(
        public string $viewTemplate = self::DEFAULT_VIEW_TEMPLATE,
        public bool $debug = false,
    ) {}

    /**
     * Builds the options from the `view_template` and `debug` keys of
     * config[errors], applying the defaults for any that are absent.
     *
     * @param array<array-key, mixed> $errors
     *
     * @throws RuntimeException When `debug` is neither null nor a boolean.
     */
    public static function fromConfig(array $errors): self
    {
        return new self(
            viewTemplate: self::viewTemplate($errors['view_template'] ?? null),
            debug: self::debug($errors['debug'] ?? null),
        );
    }

    /**
     * @throws RuntimeException When the value is neither null nor a boolean.
     */
    private static function debug(mixed $debug): bool
    {
        if (null !== $debug && ! is_bool($debug)) {
            throw new RuntimeException(
                'contenir/contenir-errors-laminas-mvc: config[errors][debug] must be a boolean.',
            );
        }

        return true === $debug;
    }

    /**
     * A missing, null or empty template falls back to the package default,
     * rather than handing the renderer a template named "".
     */
    private static function viewTemplate(mixed $template): string
    {
        return is_string($template) && '' !== $template ? $template : self::DEFAULT_VIEW_TEMPLATE;
    }
}
