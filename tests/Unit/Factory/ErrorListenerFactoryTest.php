<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Unit\Factory;

use Contenir\Errors\ErrorPageRepositoryInterface;
use Contenir\Errors\Laminas\Mvc\ConfigProvider;
use Contenir\Errors\Laminas\Mvc\Factory\ErrorListenerFactory;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Contenir\Errors\Laminas\Mvc\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Errors\Laminas\Mvc\Tests\Trait\MvcEventTrait;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Model\ViewModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;

#[CoversClass(ErrorListenerFactory::class)]
#[Group('unit')]
#[Group('factory')]
final class ErrorListenerFactoryTest extends TestCase
{
    use MvcEventTrait;

    /**
     * @return array<string, array{mixed}>
     */
    public static function fallbackTemplateProvider(): array
    {
        return [
            'null'         => [null],
            'empty string' => [''],
            'not a string' => [['site/error']],
        ];
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function fieldValueProvider(): array
    {
        return [
            'string'  => ['Lost', 'Lost'],
            'integer' => [404, '404'],
            'true'    => [true, '1'],
            'null'    => [null, ''],
            'array'   => [['nested'], ''],
            'object'  => [new stdClass(), ''],
        ];
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidLoggerProvider(): array
    {
        return [
            'integer'      => [42, 'config[errors][logger] must be null'],
            'empty string' => ['', 'config[errors][logger] must be null'],
            'array'        => [['log.psr3'], 'config[errors][logger] must be null'],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function unusableConfigProvider(): array
    {
        return [
            'no config service'      => [[]],
            'config is not an array' => [['config' => 'oops']],
            'errors key missing'     => [['config' => []]],
            'errors is not an array' => [['config' => ['errors' => 'oops']]],
            'errors is null'         => [['config' => ['errors' => null]]],
            'pages is not an array'  => [['config' => ['errors' => ['pages' => 'oops']]]],
        ];
    }

    /**
     * @param array<array-key, mixed> $pages
     */
    private static function buildWithoutRepository(array $pages): ErrorListener
    {
        return (new ErrorListenerFactory())(new InMemoryContainer(['config' => ['errors' => ['pages' => $pages]]]));
    }

    private static function template(MvcEvent $event): ?string
    {
        $result = $event->getResult();

        return $result instanceof ViewModel ? $result->getTemplate() : null;
    }

    private static function variable(MvcEvent $event, string $name): mixed
    {
        $result = $event->getResult();

        return $result instanceof ViewModel ? $result->getVariable($name) : null;
    }

    #[Test]
    public function adminPagesOverrideOnlyTheirOwnStatus(): void
    {
        $listener = self::buildWithoutRepository([404 => ['title' => 'Site 404', 'body' => '<p>site</p>']]);
        $notFound = self::eventWith(404);
        $broken   = self::eventWith(500);

        $listener($notFound);
        $listener($broken);

        static::assertSame(
            ['Site 404', ConfigProvider::DEFAULT_PAGES[500]['title']],
            [self::variable($notFound, 'title'), self::variable($broken, 'title')],
        );
    }

    #[Test]
    public function appliesTheConfiguredViewTemplate(): void
    {
        $event = self::eventWith(404);

        $this->build(['view_template' => 'site/custom-error'])($event);

        static::assertSame('site/custom-error', self::template($event));
    }

    #[Test]
    public function buildsPagesFromConfigWhenNoRepositoryServiceIsRegistered(): void
    {
        $event = self::eventWith(404);

        self::buildWithoutRepository([404 => ['title' => 'Lost', 'body' => '<p>x</p>']])($event);

        static::assertSame(['Lost', '<p>x</p>'], [self::variable($event, 'title'), self::variable($event, 'body')]);
    }

    #[Test]
    #[DataProvider('unusableConfigProvider')]
    public function fallsBackToThePackageDefaultsWhenConfigIsUnusable(array $services): void
    {
        $event = self::eventWith(404);

        (new ErrorListenerFactory())(new InMemoryContainer($services))($event);

        static::assertSame(
            ['contenir/errors/fault', ConfigProvider::DEFAULT_PAGES[404]['title']],
            [self::template($event), self::variable($event, 'title')],
        );
    }

    #[Test]
    #[DataProvider('fallbackTemplateProvider')]
    public function fallsBackToTheShippedTemplateWhenTheConfiguredOneIsUnusable(mixed $template): void
    {
        $event = self::eventWith(404);

        $this->build(['view_template' => $template])($event);

        static::assertSame('contenir/errors/fault', self::template($event));
    }

    #[Test]
    public function ignoresMalformedAdminRowsAndKeepsTheDefault(): void
    {
        $event = self::eventWith(404);

        self::buildWithoutRepository(['oops' => ['title' => 'Bad', 'body' => ''], 404 => 'not a row'])($event);

        static::assertSame(ConfigProvider::DEFAULT_PAGES[404]['title'], self::variable($event, 'title'));
    }

    #[Test]
    public function ignoresStatusesOutsideTheDefaultsAndConfig(): void
    {
        $event = self::eventWith(418);

        self::buildWithoutRepository([404 => ['title' => 'Lost', 'body' => '']])($event);

        static::assertNull($event->getResult());
    }

    #[Test]
    public function passesALoggerInstanceStraightThrough(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $this->build(['logger' => $logger])(self::eventWith(404));
    }

    #[Test]
    #[DataProvider('fieldValueProvider')]
    public function readsAdminFieldsAsStrings(mixed $value, string $expected): void
    {
        $event = self::eventWith(418);

        self::buildWithoutRepository([418 => ['title' => $value, 'body' => '<p>x</p>']])($event);

        static::assertSame($expected, self::variable($event, 'title'));
    }

    #[Test]
    public function readsMissingAdminFieldsAsEmptyStrings(): void
    {
        $event = self::eventWith(418);

        self::buildWithoutRepository([418 => ['title' => 'Teapot']])($event);

        static::assertSame('', self::variable($event, 'body'));
    }

    #[Test]
    #[DataProvider('invalidLoggerProvider')]
    public function rejectsALoggerConfigOfTheWrongType(mixed $logger, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->build(['logger' => $logger]);
    }

    #[Test]
    public function rejectsALoggerServiceThatIsNotAPsrLogger(): void
    {
        $container = new InMemoryContainer([
            'config'                            => ['errors' => ['logger' => 'not-a-logger']],
            ErrorPageRepositoryInterface::class => self::repositoryWith404(),
            'not-a-logger'                      => new stdClass(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('logger service "not-a-logger" must implement Psr\Log\LoggerInterface');

        (new ErrorListenerFactory())($container);
    }

    #[Test]
    public function resolvesTheLoggerByServiceId(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');
        $container = new InMemoryContainer([
            'config'                            => ['errors' => ['logger' => 'log.psr3']],
            ErrorPageRepositoryInterface::class => self::repositoryWith404(),
            'log.psr3'                          => $logger,
        ]);

        (new ErrorListenerFactory())($container)(self::eventWith(404));
    }

    #[Test]
    public function seedsTheBuiltInDefaultsWhenNoAdminPagesAreConfigured(): void
    {
        $event = self::eventWith(500);

        self::buildWithoutRepository([])($event);

        static::assertSame(
            [ConfigProvider::DEFAULT_PAGES[500]['title'], ConfigProvider::DEFAULT_PAGES[500]['body']],
            [self::variable($event, 'title'), self::variable($event, 'body')],
        );
    }

    #[Test]
    public function usesARegisteredRepositoryService(): void
    {
        $event = self::eventWith(404);

        $this->build(['pages' => [404 => ['title' => 'From config', 'body' => '']]])($event);

        static::assertSame('Not found', self::variable($event, 'title'));
    }

    /**
     * @param array<string, mixed> $errors
     */
    private function build(array $errors): ErrorListener
    {
        return (new ErrorListenerFactory())(new InMemoryContainer([
            'config'                            => ['errors' => $errors],
            ErrorPageRepositoryInterface::class => self::repositoryWith404(),
        ]));
    }
}
