<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Integration;

use Contenir\Errors\Laminas\Mvc\ConfigProvider;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver\TemplatePathStack;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;

/**
 * Renders the shipped view/contenir/errors/fault.phtml through laminas-view.
 */
#[CoversNothing]
#[Group('integration')]
#[Group('view')]
final class FaultTemplateTest extends TestCase
{
    /**
     * @param array<string, mixed> $variables
     */
    private static function render(array $variables): string
    {
        $renderer = new PhpRenderer();
        $renderer->setResolver(new TemplatePathStack([
            'script_paths' => (new ConfigProvider())->getViewManagerConfig()['template_path_stack'],
        ]));

        $model = new ViewModel($variables);
        $model->setTemplate(ConfigProvider::DEFAULT_VIEW_TEMPLATE);

        return $renderer->render($model);
    }

    #[Test]
    public function escapesTheTitleAndRendersTheBodyRaw(): void
    {
        $html = self::render([
            'status' => 404,
            'title'  => 'Tom & <Jerry>',
            'body'   => '<p>Try <a href="/">home</a>.</p>',
        ]);

        static::assertSame(
            [true, true, true, true],
            [
                str_contains($html, '<title>Tom &amp; &lt;Jerry&gt;</title>'),
                str_contains($html, '<h1 class="fault__title">Tom &amp; &lt;Jerry&gt;</h1>'),
                str_contains($html, '<div class="fault__body"><p>Try <a href="/">home</a>.</p></div>'),
                str_contains($html, '<body class="fault fault--404">'),
            ],
        );
    }

    #[Test]
    public function fallsBackToTheStatusWhenTheTitleIsEmpty(): void
    {
        $html = self::render(['status' => 503, 'title' => '', 'body' => '']);

        static::assertSame(
            [true, false, false],
            [
                str_contains($html, '<title>Error 503</title>'),
                str_contains($html, '<h1 class="fault__title">'),
                str_contains($html, '<div class="fault__body">'),
            ],
        );
    }

    #[Test]
    public function keepsSearchEnginesAwayFromErrorPages(): void
    {
        static::assertStringContainsString(
            '<meta name="robots" content="noindex">',
            self::render(['status' => 500, 'title' => 'Oops', 'body' => '']),
        );
    }
}
