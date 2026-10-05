<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Trait;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\Repository\InMemoryRepository;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;

/**
 * Builds the MvcEvent a render listener sees: an HTTP response carrying the
 * status, and optionally an HTTP request for the logged URI.
 */
trait MvcEventTrait
{
    private static function eventWith(int $status, ?string $uri = 'http://example.test/'): MvcEvent
    {
        $response = new HttpResponse();
        $response->setStatusCode($status);

        $event = new MvcEvent();
        $event->setResponse($response);

        if (null !== $uri) {
            $request = new HttpRequest();
            $request->setUri($uri);
            $event->setRequest($request);
        }

        return $event;
    }

    private static function repositoryWith404(): InMemoryRepository
    {
        return new InMemoryRepository([new ErrorPage(404, 'Not found', '<p>Lost.</p>')]);
    }
}
