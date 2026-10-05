<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Unit;

use Contenir\Errors\Laminas\Mvc\ConfigProvider;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Contenir\Errors\Laminas\Mvc\Module;
use Contenir\Errors\Repository\InMemoryRepository;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Module::class)]
#[Group('unit')]
final class ModuleTest extends TestCase
{
    #[Test]
    public function attachesTheListenerToRenderAndRenderError(): void
    {
        $listener = new ErrorListener(new InMemoryRepository());
        $attached = [];
        $events   = $this->createMock(EventManagerInterface::class);
        $events->expects($this->exactly(2))
            ->method('attach')
            ->willReturnCallback(static function (string $name, callable $callback, int $priority) use (
                &$attached,
                $listener,
            ): callable {
                $attached[] = [$name, $callback === $listener, $priority];

                return $callback;
            });

        (new Module())->attachListener($events, $listener);

        static::assertSame(
            [
                [MvcEvent::EVENT_RENDER, true, 100],
                [MvcEvent::EVENT_RENDER_ERROR, true, -100],
            ],
            $attached,
        );
    }

    #[Test]
    public function exposesTheListenerPriorities(): void
    {
        static::assertSame([100, -100], [Module::RENDER_PRIORITY, Module::RENDER_ERROR_PRIORITY]);
    }

    #[Test]
    public function getConfigReturnsTheConfigProviderArray(): void
    {
        static::assertSame((new ConfigProvider())(), (new Module())->getConfig());
    }
}
