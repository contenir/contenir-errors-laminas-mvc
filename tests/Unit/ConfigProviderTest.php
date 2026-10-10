<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Unit;

use Contenir\Errors\Laminas\Mvc\ConfigProvider;
use Contenir\Errors\Laminas\Mvc\Factory\ErrorListenerFactory;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function realpath;

#[CoversClass(ConfigProvider::class)]
#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    /**
     * @return array<string, array{int}>
     */
    public static function defaultStatusProvider(): array
    {
        return [
            '403' => [403],
            '404' => [404],
            '500' => [500],
        ];
    }

    #[Test]
    public function errorDefaultsUseTheShippedTemplateNoLoggerAndDebugOff(): void
    {
        static::assertSame(
            ['view_template' => 'contenir/errors/fault', 'logger' => null, 'debug' => false],
            (new ConfigProvider())->getErrorsDefaults(),
        );
    }

    #[Test]
    public function invokeCombinesDependenciesErrorDefaultsAndViewManagerConfig(): void
    {
        $provider = new ConfigProvider();

        static::assertSame(
            [
                'service_manager' => $provider->getDependencies(),
                'errors'          => $provider->getErrorsDefaults(),
                'view_manager'    => $provider->getViewManagerConfig(),
            ],
            $provider(),
        );
    }

    #[Test]
    public function registersTheListenerFactory(): void
    {
        static::assertSame(
            ['factories' => [ErrorListener::class => ErrorListenerFactory::class]],
            (new ConfigProvider())->getDependencies(),
        );
    }

    #[Test]
    #[DataProvider('defaultStatusProvider')]
    public function shipsANonEmptyDefaultPageForCommonStatuses(int $status): void
    {
        $page = ConfigProvider::DEFAULT_PAGES[$status] ?? ['title' => '', 'body' => ''];

        static::assertNotContains('', [$page['title'], $page['body']]);
    }

    #[Test]
    public function viewManagerConfigPointsAtTheShippedViewDirectory(): void
    {
        $stack = (new ConfigProvider())->getViewManagerConfig()['template_path_stack'] ?? [];

        static::assertSame(
            [realpath(dirname(__DIR__, levels: 2) . '/view')],
            [realpath($stack[0] ?? '')],
        );
    }
}
