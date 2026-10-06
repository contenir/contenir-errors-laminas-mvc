<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Factory;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\ErrorPageRepositoryInterface;
use Contenir\Errors\Laminas\Mvc\ConfigProvider;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Contenir\Errors\Repository\InMemoryRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function array_filter;
use function array_replace;
use function is_array;
use function is_int;
use function is_scalar;
use function is_string;
use function sprintf;

use const ARRAY_FILTER_USE_BOTH;

/**
 * Builds the ErrorListener from config[errors] (view_template, logger,
 * pages) and an optional ErrorPageRepositoryInterface service.
 *
 * @api
 */
final readonly class ErrorListenerFactory
{
    /**
     * @return array<array-key, mixed>
     */
    private static function arrayOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @throws RuntimeException
     */
    private static function loggerService(mixed $service, string $id): LoggerInterface
    {
        if (! $service instanceof LoggerInterface) {
            throw new RuntimeException(sprintf(
                'contenir/contenir-errors-laminas-mvc: logger service "%s" must implement Psr\Log\LoggerInterface.',
                $id,
            ));
        }

        return $service;
    }

    /**
     * Scalars are cast as before; anything else (a nested array, an object)
     * reads as an empty string rather than raising "Array to string
     * conversion" on every request.
     */
    private static function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A missing, null or empty template falls back to the package default,
     * rather than handing the renderer a template named "".
     */
    private static function viewTemplate(mixed $template): string
    {
        return is_string($template) && '' !== $template ? $template : ConfigProvider::DEFAULT_VIEW_TEMPLATE;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws RuntimeException When the logger config or service has the wrong type.
     */
    private function resolveLogger(ContainerInterface $container, mixed $logger): ?LoggerInterface
    {
        if (null === $logger || $logger instanceof LoggerInterface) {
            return $logger;
        }

        if (is_string($logger) && '' !== $logger) {
            return self::loggerService($container->get($logger), $logger);
        }

        throw new RuntimeException(
            'contenir/contenir-errors-laminas-mvc: config[errors][logger] must be null, a service ID string,'
                . ' or a Psr\Log\LoggerInterface instance.',
        );
    }

    /**
     * Build an in-memory repository pre-seeded with the package's built-in
     * default pages (ConfigProvider::DEFAULT_PAGES) and then layered with
     * admin-authored pages from config[errors][pages] (auto-merged into
     * $config from config/autoload/errors.local.php). Admin entries override
     * defaults per-status, so any status the operator hasn't customised still
     * renders a presentable page rather than falling through to the
     * framework's bare default. Admin rows that are not status-keyed arrays
     * are ignored, leaving any default for that status in place.
     *
     * If a service is registered for ErrorPageRepositoryInterface (e.g. a
     * consumer wants to swap in their own implementation), that wins.
     *
     * @param array<array-key, mixed> $configured
     *
     * @throws ContainerExceptionInterface
     */
    private function resolveRepository(ContainerInterface $container, array $configured): ErrorPageRepositoryInterface
    {
        if ($container->has(ErrorPageRepositoryInterface::class)) {
            return $container->get(ErrorPageRepositoryInterface::class);
        }

        /** @var array<int, array<array-key, mixed>> $rows */
        $rows = array_replace(ConfigProvider::DEFAULT_PAGES, array_filter(
            $configured,
            static fn(mixed $row, int|string $status): bool => is_int($status) && is_array($row),
            ARRAY_FILTER_USE_BOTH,
        ));

        $pages = [];
        foreach ($rows as $status => $row) {
            $row     += ['title' => '', 'body' => ''];
            $pages[] = new ErrorPage($status, self::scalarString($row['title']), self::scalarString($row['body']));
        }

        return new InMemoryRepository($pages);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws RuntimeException When the logger config or service has the wrong type.
     */
    public function __invoke(ContainerInterface $container): ErrorListener
    {
        $config = $container->has('config') ? self::arrayOrEmpty($container->get('config')) : [];
        $errors = self::arrayOrEmpty($config['errors'] ?? null)
        + [
            'pages'         => null,
            'view_template' => null,
            'logger'        => null,
        ];

        return new ErrorListener(
            repository: $this->resolveRepository($container, self::arrayOrEmpty($errors['pages'])),
            viewTemplate: self::viewTemplate($errors['view_template']),
            logger: $this->resolveLogger($container, $errors['logger']),
        );
    }
}
