<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Integration;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Contenir\Errors\Laminas\Mvc\Module;
use Contenir\Errors\Laminas\Mvc\Tests\Trait\MvcEventTrait;
use Contenir\Errors\Repository\InMemoryRepository;
use Laminas\EventManager\EventManager;
use Laminas\Mvc\Application;
use Laminas\Mvc\ApplicationInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\View\Http\ExceptionStrategy;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Model\ViewModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Module::class)]
#[Group('integration')]
final class ModuleTest extends TestCase
{
    use MvcEventTrait;

    private static function listener(): ErrorListener
    {
        return new ErrorListener(new InMemoryRepository([
            new ErrorPage(404, 'Not found', '<p>Lost.</p>'),
            new ErrorPage(500, 'Oops', '<p>Sorry.</p>'),
        ]));
    }

    private static function template(MvcEvent $event): ?string
    {
        $result = $event->getResult();

        return $result instanceof ViewModel ? $result->getTemplate() : null;
    }

    #[Test]
    public function onBootstrapAttachesTheListenerFromTheServiceManager(): void
    {
        $events      = new EventManager();
        $application = static::createStub(ApplicationInterface::class);
        $application->method('getServiceManager')
            ->willReturn(new ServiceManager([
                'services' => [ErrorListener::class => self::listener()],
            ]));
        $application->method('getEventManager')->willReturn($events);
        $bootstrap = new MvcEvent();
        $bootstrap->setApplication($application);

        (new Module())->onBootstrap($bootstrap);

        $render = self::eventWith(404);
        $render->setName(MvcEvent::EVENT_RENDER);
        $events->triggerEvent($render);

        static::assertSame('contenir/errors/fault', self::template($render));
    }

    #[Test]
    public function renderErrorListenerRunsAfterTheExceptionStrategySetsTheStatus(): void
    {
        $events = new EventManager();
        (new ExceptionStrategy())->attach($events);
        (new Module())->attachListener($events, self::listener());

        $event = self::eventWith(200);
        $event->setName(MvcEvent::EVENT_RENDER_ERROR);
        $event->setError(Application::ERROR_EXCEPTION);
        $event->setParam('exception', new RuntimeException('render failed'));
        $events->triggerEvent($event);

        static::assertSame('contenir/errors/fault', self::template($event));
    }
}
