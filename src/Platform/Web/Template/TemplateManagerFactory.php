<?php

declare(strict_types= 1);

namespace Marshal\Server\Platform\Web\Template;

use Psr\Container\ContainerInterface;

final class TemplateManagerFactory
{
    public function __invoke(ContainerInterface $container): TemplateManager
    {
        $layoutsConfig = $container->get('config')['layouts'] ?? [];
        $templatesConfig = $container->get('config')['templates'] ?? [];
        return new TemplateManager($layoutsConfig, $templatesConfig);
    }
}
