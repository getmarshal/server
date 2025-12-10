<?php

declare(strict_types=1);

namespace Marshal\Server\Listener;

use Laminas\Stratigility\MiddlewarePipe;
use Marshal\Server\Event\HttpRequestEvent;
use Marshal\Server\Middleware\LazyLoadingMiddleware;
use Psr\Container\ContainerInterface;

class ServerEventsListener
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function onHttpRequestEvent(HttpRequestEvent $event): void
    {
        $config = $this->container->get('config')['middleware_pipeline'] ?? [];
        if (! \is_array($config) || empty($config)) {
            return;
        }

        $pipeline = new MiddlewarePipe;
        foreach ($config as $middleware) {
            $pipeline->pipe(new LazyLoadingMiddleware($this->container, $middleware));
        }

        $event->setResponse(response: $pipeline->handle(request: $event->getRequest()));
    }
}
