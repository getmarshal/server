<?php

declare(strict_types=1);

namespace Marshal\Server;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    private function getDependencies(): array
    {
        return [
            'factories' => [
                Runtime\Apache2Handler\Apache2Handler::class => Runtime\Apache2Handler\Apache2HandlerFactory::class,
                Runtime\Cli\CliRuntime::class => Runtime\Cli\CliRuntimeFactory::class,
            ],
        ];
    }
}
