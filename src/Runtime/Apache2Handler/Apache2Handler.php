<?php

declare(strict_types=1);

namespace Marshal\Server\Runtime\Apache2Handler;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Marshal\Server\Event\HttpRequestEvent;
use Marshal\Server\Runtime\RuntimeInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;

class Apache2Handler implements RuntimeInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private bool $isDebugMode = FALSE
    ) {
    }

    public function run(): void
    {
        // generate a PSR-7 request
        $request = ServerRequestFactory::fromGlobals();

        // handle the request
        $event = new HttpRequestEvent($request->withAttribute(RuntimeInterface::class, self::class));

        try {
            $event = $this->eventDispatcher->dispatch($event);
        } catch (\Throwable $e) {
            if ($this->isDebugMode) {
                throw $e;
            }

            $event->setResponse($this->generateErrorResponse($e));
        }

        // emit the response
        $emitter = new SapiEmitter;
        $emitter->emit($event->getResponse());
    }

    private function generateErrorResponse(\Throwable $e): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse(500);
        $response->getBody()->write("Server error");
        return $response;
    }
}
