<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Unit\Listener;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Contenir\Errors\Laminas\Mvc\Tests\Trait\MvcEventTrait;
use Contenir\Errors\Repository\InMemoryRepository;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\ApplicationInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Stdlib\RequestInterface;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\ViewModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(ErrorListener::class)]
#[Group('unit')]
#[Group('listener')]
final class ErrorListenerTest extends TestCase
{
    use MvcEventTrait;

    /**
     * @return array<string, array{int, string}>
     */
    public static function configuredStatusProvider(): array
    {
        return [
            '400' => [400, 'Bad request'],
            '403' => [403, 'Forbidden'],
            '500' => [500, 'Oops'],
            '503' => [503, 'Down for maintenance'],
        ];
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonErrorStatusProvider(): array
    {
        return [
            '200 OK'                   => [200],
            '302 Found'                => [302],
            '399, below the 4xx range' => [399],
        ];
    }

    /**
     * @return array<string, array{InMemoryRepository}>
     */
    public static function noUsablePageProvider(): array
    {
        return [
            'no page for the status' => [new InMemoryRepository()],
            'page is empty'          => [new InMemoryRepository([new ErrorPage(404, '', '')])],
        ];
    }

    private static function applicationWith(EventManagerInterface $events): ApplicationInterface
    {
        $application = static::createStub(ApplicationInterface::class);
        $application->method('getEventManager')->willReturn($events);

        return $application;
    }

    #[Test]
    public function asksThePageCacheToSkipErrorResponses(): void
    {
        $events = $this->createMock(EventManagerInterface::class);
        $events->expects($this->once())->method('trigger')->with('pagecache.disable');
        $event = self::eventWith(404);
        $event->setApplication(self::applicationWith($events));

        (new ErrorListener(new InMemoryRepository()))($event);
    }

    #[Test]
    public function doesNotLogNonErrorStatuses(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method(static::anything());

        (new ErrorListener(self::repositoryWith404(), logger: $logger))(self::eventWith(200));
    }

    #[Test]
    public function exposesTheDefaultViewTemplate(): void
    {
        static::assertSame('contenir/errors/fault', ErrorListener::DEFAULT_VIEW_TEMPLATE);
    }

    #[Test]
    #[DataProvider('nonErrorStatusProvider')]
    public function ignoresNonErrorStatuses(int $status): void
    {
        $event = self::eventWith($status);

        (new ErrorListener(self::repositoryWith404()))($event);

        static::assertNull($event->getResult());
    }

    #[Test]
    public function ignoresResponsesThatAreNotHttp(): void
    {
        $event = new MvcEvent();
        $event->setResponse(static::createStub(ResponseInterface::class));

        (new ErrorListener(self::repositoryWith404()))($event);

        static::assertNull($event->getResult());
    }

    #[Test]
    public function leavesThePageCacheAloneForNonErrorStatuses(): void
    {
        $events = $this->createMock(EventManagerInterface::class);
        $events->expects($this->never())->method('trigger');
        $event = self::eventWith(200);
        $event->setApplication(self::applicationWith($events));

        (new ErrorListener(self::repositoryWith404()))($event);
    }

    #[Test]
    #[DataProvider('noUsablePageProvider')]
    public function leavesTheResultAloneWithoutAUsablePage(InMemoryRepository $repository): void
    {
        $event = self::eventWith(404);

        (new ErrorListener($repository))($event);

        static::assertNull($event->getResult());
    }

    #[Test]
    public function logsAnEmptyUriWhenTheEventHasNoRequest(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('HTTP 404 at ');

        (new ErrorListener(self::repositoryWith404(), logger: $logger))(self::eventWith(404, uri: null));
    }

    #[Test]
    public function logsAnEmptyUriWhenTheRequestIsNotHttp(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('HTTP 404 at ');
        $event = self::eventWith(404, uri: null);
        $event->setRequest(static::createStub(RequestInterface::class));

        (new ErrorListener(self::repositoryWith404(), logger: $logger))($event);
    }

    #[Test]
    public function logsClientErrorsAtInfoWithTheRequestUri(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('HTTP 404 at http://example.test/missing');
        $logger->expects($this->never())->method('error');

        (new ErrorListener(self::repositoryWith404(), logger: $logger))(self::eventWith(
            404,
            'http://example.test/missing',
        ));
    }

    #[Test]
    public function logsEvenWhenNoPageIsConfigured(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        (new ErrorListener(new InMemoryRepository(), logger: $logger))(self::eventWith(404));
    }

    #[Test]
    public function logsServerErrorsAtErrorWithoutContextWhenNoExceptionIsAttached(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('HTTP 500 at http://example.test/boom', []);
        $logger->expects($this->never())->method('info');

        (new ErrorListener(new InMemoryRepository(), logger: $logger))(self::eventWith(
            500,
            'http://example.test/boom',
        ));
    }

    #[Test]
    public function logsServerErrorsWithTheEventExceptionInContext(): void
    {
        $exception = new RuntimeException('database is on fire');
        $logger    = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('HTTP 500 at http://example.test/', [
                'exception' => $exception,
            ]);

        $event = self::eventWith(500);
        $event->setParam('exception', $exception);

        (new ErrorListener(new InMemoryRepository(), logger: $logger))($event);
    }

    #[Test]
    #[DataProvider('configuredStatusProvider')]
    public function rendersAnyConfiguredErrorStatus(int $status, string $title): void
    {
        $event = self::eventWith($status);

        (new ErrorListener(new InMemoryRepository([new ErrorPage($status, $title, '<p>x</p>')])))($event);

        $result = $event->getResult();
        static::assertInstanceOf(ViewModel::class, $result);
        static::assertSame($title, $result->getVariable('title'));
    }

    #[Test]
    public function rendersTheConfiguredPageAsATerminalViewModel(): void
    {
        $event = self::eventWith(404);

        (new ErrorListener(self::repositoryWith404()))($event);

        $result = $event->getResult();
        static::assertInstanceOf(ViewModel::class, $result);
        static::assertSame(
            [
                'contenir/errors/fault',
                ['status' => 404, 'title' => 'Not found', 'body' => '<p>Lost.</p>'],
                true,
                $result,
            ],
            [$result->getTemplate(), (array) $result->getVariables(), $result->terminate(), $event->getViewModel()],
        );
    }

    #[Test]
    public function replacesAResultThatIsNotAViewModel(): void
    {
        $event = self::eventWith(404);
        $event->setResult('some non-viewmodel result');

        (new ErrorListener(self::repositoryWith404()))($event);

        static::assertInstanceOf(ViewModel::class, $event->getResult());
    }

    #[Test]
    public function reusesAnExistingViewModelAndKeepsItsVariables(): void
    {
        $existing = new ViewModel(['preserve_me' => 'yes']);
        $existing->setTemplate('error/index');
        $event = self::eventWith(404);
        $event->setResult($existing);

        (new ErrorListener(self::repositoryWith404()))($event);

        static::assertSame(
            [$existing, 'contenir/errors/fault', 'yes'],
            [$event->getResult(), $existing->getTemplate(), $existing->getVariable('preserve_me')],
        );
    }

    #[Test]
    public function usesTheConfiguredViewTemplate(): void
    {
        $event = self::eventWith(404);

        (new ErrorListener(
            repository: self::repositoryWith404(),
            viewTemplate: 'site/custom-error',
        ))($event);

        $result = $event->getResult();
        static::assertInstanceOf(ViewModel::class, $result);
        static::assertSame('site/custom-error', $result->getTemplate());
    }
}
