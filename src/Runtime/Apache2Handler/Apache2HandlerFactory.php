<?php

declare(strict_types=1);

namespace Marshal\Server\Runtime\Apache2Handler;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final class Apache2HandlerFactory
{
    public function __invoke(ContainerInterface $container): Apache2Handler
    {
        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $isDevMode = $container->get('config')['debug'] ?? FALSE;

        return new Apache2Handler($eventDispatcher, (bool) $isDevMode);
    }
}
