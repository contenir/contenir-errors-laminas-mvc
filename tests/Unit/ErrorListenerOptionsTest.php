<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Unit;

use Contenir\Errors\Laminas\Mvc\ErrorListenerOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ErrorListenerOptions::class)]
#[Group('unit')]
final class ErrorListenerOptionsTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function debugOffProvider(): array
    {
        return [
            'not configured' => [[]],
            'null'           => [['debug' => null]],
            'false'          => [['debug' => false]],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidDebugProvider(): array
    {
        return [
            'integer' => [1],
            'string'  => ['true'],
            'array'   => [[true]],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableTemplateProvider(): array
    {
        return [
            'not configured' => [null],
            'empty string'   => [''],
            'not a string'   => [['site/error']],
        ];
    }

    #[Test]
    public function defaultsToTheShippedTemplateWithDebugOff(): void
    {
        $options = new ErrorListenerOptions();

        static::assertSame(['contenir/errors/fault', false], [$options->viewTemplate, $options->debug]);
    }

    #[Test]
    #[DataProvider('unusableTemplateProvider')]
    public function fallsBackToTheShippedTemplateWhenTheConfiguredOneIsUnusable(mixed $template): void
    {
        static::assertSame(
            ErrorListenerOptions::DEFAULT_VIEW_TEMPLATE,
            ErrorListenerOptions::fromConfig(['view_template' => $template])->viewTemplate,
        );
    }

    #[Test]
    #[DataProvider('debugOffProvider')]
    public function leavesDebugOffUnlessConfiguredTrue(array $errors): void
    {
        static::assertFalse(ErrorListenerOptions::fromConfig($errors)->debug);
    }

    #[Test]
    public function readsDebugFromConfig(): void
    {
        static::assertTrue(ErrorListenerOptions::fromConfig(['debug' => true])->debug);
    }

    #[Test]
    public function readsTheViewTemplateFromConfig(): void
    {
        static::assertSame(
            'site/custom-error',
            ErrorListenerOptions::fromConfig(['view_template' => 'site/custom-error'])->viewTemplate,
        );
    }

    #[Test]
    #[DataProvider('invalidDebugProvider')]
    public function rejectsADebugConfigThatIsNotABoolean(mixed $debug): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contenir/contenir-errors-laminas-mvc: config[errors][debug] must be a boolean.');

        ErrorListenerOptions::fromConfig(['debug' => $debug]);
    }
}
