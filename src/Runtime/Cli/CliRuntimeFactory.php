<?php

declare(strict_types=1);

namespace Marshal\Server\Runtime\Cli;

use Psr\Container\ContainerInterface;

final class CliRuntimeFactory
{
    public function __invoke(ContainerInterface $container): CliRuntime
    {
        $console = new Application('Marshal', '0.0.1');

        // set up commands
        $commands = $container->get('config')['commands'] ?? [];
        foreach (\array_values($commands) as $command) {
            $console->addCommand($container->get($command));
        }

        return new CliRuntime($console);
    }
}
