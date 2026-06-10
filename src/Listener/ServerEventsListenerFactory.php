<?php

/**
 *
 */

declare(strict_types=1);

namespace Marshal\Server\Listener;

use Psr\Container\ContainerInterface;

final class ServerEventsListenerFactory
{
    public function __invoke(ContainerInterface $container): ServerEventsListener
    {
        return new ServerEventsListener($container);
    }
}
