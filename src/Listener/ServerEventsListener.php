<?php

declare(strict_types=1);

namespace Marshal\Server\Listener;

use Laminas\Stratigility\MiddlewarePipe;
use Marshal\Server\Middleware\LazyLoadingMiddleware;
use Marshal\Server\Event\HttpRequestEvent;
use Mezzio\Router\RouteCollectorInterface;
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

        $this->setupRouting($this->container);

        $pipeline = new MiddlewarePipe;
        foreach ($config as $middleware) {
            $pipeline->pipe(new LazyLoadingMiddleware($this->container, $middleware));
        }

        $event->setResponse($pipeline->handle($event->getRequest()));
    }

    private function setupRouting(ContainerInterface $container): void
    {
        // @todo validate routes
        $navigation = $container->get('config')['navigation'] ?? [];
        $routeCollector = $container->get(RouteCollectorInterface::class);
        if (! $routeCollector instanceof RouteCollectorInterface) {
            return;
        }

        if (\is_array($navigation)) {
            foreach ($navigation['paths'] ?? [] as $pattern => $config) {
                $route = $routeCollector->route(
                    path: $pattern,
                    middleware: new LazyLoadingMiddleware($container, $config['middleware']),
                    methods: $config['methods'] ?? ['GET'],
                    name: $config['name'],
                );

                // set route options
                $route->setOptions($config['options'] ?? []);
            }
        }
    }
}
