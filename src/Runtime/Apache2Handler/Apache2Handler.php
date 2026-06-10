<?php

declare(strict_types=1);

namespace Marshal\Server\Runtime\Apache2Handler;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Marshal\Platform\PlatformInterface;
use Marshal\Server\Event\HttpRequestEvent;
use Marshal\Server\Event\HttpResponseEvent;
use Marshal\Server\Response\SseResponseEmitter;
use Marshal\Server\Runtime\RuntimeInterface;
use Marshal\Utils\Logger\LoggerManager;
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
        try {
            $event = $this->eventDispatcher->dispatch(new HttpRequestEvent(
                $request->withAttribute(RuntimeInterface::class, self::class)
            ));
            \assert($event instanceof HttpRequestEvent);

            $response = $event->getResponse();
        } catch (\Throwable $e) {
            if ($this->isDebugMode) {
                throw $e;
            }

            $response = $this->generateErrorResponse($e);
        }

        // dispatch the response event
        // try {
        //     $this->eventDispatcher->dispatch(new HttpResponseEvent(
        //         $request,
        //         $response,
        //         $request->getAttribute(PlatformInterface::class)
        //     ));
        // } catch (\Throwable $e) {
        //     LoggerManager::get()->error($e->getMessage());
        // }

        // emit the response
        // @todo add the stream sapi emitter
        $emitter = new EmitterStack();
        $emitter->push(new SapiEmitter());
        $emitter->push(new SseResponseEmitter());
        $emitter->emit($response);
    }

    private function generateErrorResponse(\Throwable $e): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse(500);
        $response->getBody()->write("Server error");
        return $response;
    }
}
