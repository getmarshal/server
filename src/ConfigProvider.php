<?php

declare(strict_types=1);

namespace Marshal\Server;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'events' => $this->getActionsConfig(),
            'dependencies' => $this->getDependencies(),
        ];
    }

    private function getActionsConfig(): array
    {
        return [
            'listeners' => [
                Listener\ServerEventsListener::class => [
                    Event\HttpRequestEvent::class => [
                        'listener' => 'onHttpRequestEvent',
                    ],
                ],
            ],
        ];
    }

    private function getDependencies(): array
    {
        $dependencies = [
            'factories' => [
                Listener\ServerEventsListener::class => Listener\ServerEventsListenerFactory::class,
                Runtime\Apache2Handler\Apache2Handler::class => Runtime\Apache2Handler\Apache2HandlerFactory::class,
                Runtime\Cli\CliRuntime::class => Runtime\Cli\CliRuntimeFactory::class,
            ],
        ];

        $routerConfig = $this->getRouterConfig();
        $dependencies = \array_merge_recursive($dependencies, $routerConfig['dependencies']);

        return $dependencies;
    }

    private function getRouterConfig(): array
    {
        $routerConfigProvider = new \Mezzio\Router\ConfigProvider();
        $fastRouteConfigProvider = new \Mezzio\Router\FastRouteRouter\ConfigProvider();

        return \array_merge_recursive($routerConfigProvider(), $fastRouteConfigProvider());
    }
}
