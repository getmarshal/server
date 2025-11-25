<?php

declare(strict_types=1);

namespace Marshal\Server\Listener;

use Laminas\Stratigility\MiddlewarePipe;
use Marshal\EventManager\EventListenerInterface;
use Marshal\Server\Event\HttpRequestEvent;
use Marshal\Server\Middleware\LazyLoadingMiddleware;
use Psr\Container\ContainerInterface;

class ServerEventsListener implements EventListenerInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function getListeners(): array
    {
        return [
            HttpRequestEvent::class => ['listener' => [$this, 'onHttpRequestEvent']],
        ];
    }

    public function onHttpRequestEvent(HttpRequestEvent $event): void
    {
        $config = $this->container->get('config')['middleware_pipeline'] ?? [];
        if (! \is_array($config) || empty($config)) {
            return;
        }

        $pipeline = new MiddlewarePipe;
        foreach ($config as $middleware) {
            $pipeline->pipe(new LazyLoadingMiddleware(container: $this->container, middleware: $middleware));
        }

        $event->setResponse(response: $pipeline->handle(request: $event->getRequest()));
    }
}
