<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Listener;

use Contenir\Errors\ErrorPageRepositoryInterface;
use Contenir\Errors\Laminas\Mvc\ErrorListenerOptions;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Model\ViewModel;
use Psr\Log\LoggerInterface;
use Throwable;

use function sprintf;

/**
 * Swaps the result ViewModel with admin-authored content for any 4xx/5xx
 * response that has a configured page in the repository.
 *
 * Listens at RENDER (high priority). By the time RENDER fires, the response
 * status is settled — RouteNotFoundStrategy has set 404, ExceptionStrategy
 * has set 500, or the controller has called setStatusCode(403). One hook
 * handles every path uniformly.
 *
 * When no page is configured, the framework's default rendering proceeds
 * unchanged — the listener is non-invasive on first install.
 *
 * In debug mode (config[errors][debug]) the listener still logs and disables
 * the page cache, but otherwise leaves every error response alone, so
 * Laminas's own RouteNotFoundStrategy and ExceptionStrategy output (the
 * unmatched route, the exception and stack trace) reaches the browser while
 * developing. This matches contenir/contenir-errors-mezzio's `debug` option.
 *
 * Logging is independent of admin overrides: every intercepted 4xx/5xx is
 * surfaced to the optional PSR-3 logger so Sites can observe error volume
 * regardless of whether they have authored a custom page.
 *
 * @api
 */
final readonly class ErrorListener
{
    public const string DEFAULT_VIEW_TEMPLATE = ErrorListenerOptions::DEFAULT_VIEW_TEMPLATE;

    /**
     * Event name used to signal page-cache opt-out. Matches the
     * EVENT_DISABLE constant published by contenir/contenir-cache-laminas-mvc.
     * Hardcoded here so this package doesn't take a hard dependency on
     * the cache adapter; if cache-laminas-mvc isn't installed, firing
     * the event is a harmless no-op.
     */
    private const string PAGECACHE_DISABLE_EVENT = 'pagecache.disable';

    public function __construct(
        private ErrorPageRepositoryInterface $repository,
        private ?LoggerInterface $logger = null,
        private ErrorListenerOptions $options = new ErrorListenerOptions(),
    ) {}

    /**
     * @return array{exception?: Throwable}
     */
    private static function exceptionContext(mixed $exception): array
    {
        return $exception instanceof Throwable ? ['exception' => $exception] : [];
    }

    private static function extractUri(MvcEvent $event): string
    {
        $request = $event->getRequest();

        return $request instanceof HttpRequest ? $request->getUri()->toString() : '';
    }

    private static function viewModelOf(mixed $result): ViewModel
    {
        return $result instanceof ViewModel ? $result : new ViewModel();
    }

    /**
     * Tell any listening page-cache that this 4xx/5xx response must not
     * be stored. cache-laminas-mvc's CacheStrategy listens to this event
     * on the same identifier(s) it uses for dispatch/finish; on receipt
     * it flips its disabled flag and onFinish skips storage.
     */
    private function disablePageCache(MvcEvent $event): void
    {
        $event->getApplication()?->getEventManager()->trigger(self::PAGECACHE_DISABLE_EVENT);
    }

    private function log(MvcEvent $event, int $status): void
    {
        if (null === $this->logger) {
            return;
        }

        $uri = self::extractUri($event);

        if ($status >= 500) {
            $this->logger->error(
                sprintf('HTTP %d at %s', $status, $uri),
                self::exceptionContext($event->getParam('exception')),
            );
            return;
        }

        $this->logger->info(sprintf('HTTP %d at %s', $status, $uri));
    }

    public function __invoke(MvcEvent $event): void
    {
        $response = $event->getResponse();
        if (! $response instanceof HttpResponse) {
            return;
        }

        $status = $response->getStatusCode();
        if ($status < 400) {
            return;
        }

        $this->disablePageCache($event);
        $this->log($event, $status);

        if ($this->options->debug) {
            return;
        }

        $page = $this->repository->get($status);
        if (null === $page || $page->isEmpty()) {
            return;
        }

        $viewModel = self::viewModelOf($event->getResult());

        $viewModel->setTemplate($this->options->viewTemplate);
        $viewModel->setVariables([
            'status' => $status,
            'title'  => $page->title,
            'body'   => $page->body,
        ]);
        $viewModel->setTerminal(true);

        $event->setResult($viewModel);
        $event->setViewModel($viewModel);
    }
}
