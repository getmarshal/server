<?php

declare(strict_types=1);

namespace Marshal\Server\Runtime;

use Mezzio\Router\RouteCollectorInterface;
use Marshal\Server\Middleware\LazyLoadingMiddleware;
use Psr\Container\ContainerInterface;

trait RuntimeProviderTrait
{
    private function setupRouting(ContainerInterface $container): void
    {
        // @todo validate routes
        $navigation = $container->get('config')['navigation'] ?? [];
        if (! \is_array($navigation) || empty($navigation)) {
            return;
        }

        $routeCollector = $container->get(RouteCollectorInterface::class);
        if (! $routeCollector instanceof RouteCollectorInterface) {
            return;
        }

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
