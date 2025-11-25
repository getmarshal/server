<?php

declare(strict_types= 1);

namespace Marshal\Server\Platform\Web\Template;

use Marshal\Platform\Web\Render\Dom\DomTemplate;
use Marshal\Platform\Web\Render\Dom\Layout;
use Marshal\Server\Platform\Web\Template\Twig\TwigTemplate;

final class TemplateManager
{
    public function __construct(private array $layoutsConfig, private array $templatesConfig)
    {
    }

    public function get($name, ?array $options = null): TemplateInterface
    {
        $validator = new TemplateConfigValidator($this->templatesConfig);
        if (! $validator->isValid($name)) {
            $message = "";
            foreach ($validator->getMessages() as $str) {
                $message .= $str;
            }

            throw new \InvalidArgumentException($message);
        }

        $config = $this->templatesConfig[$name];
        if (isset($config["elements"])) {
            $template = new DomTemplate($name, $config, $this->getTemplateLayout($config));
        } elseif (isset($config["filename"])) {
            if (FALSE !== \mb_strpos($config["filename"], '.twig')) {
                $template = new TwigTemplate($name, $config);
            }
        }

        if (! isset($template) || ! $template instanceof TemplateInterface) {
            throw new \InvalidArgumentException("Invalid template $name");
        }

        return $template;
    }

    private function getTemplateLayout(array $config): ?Layout
    {
        if (! isset($config['layout'])) {
            return null;
        }

        $name = $config['layout'];
        if (! isset($this->layoutsConfig[$name])) {
            return null;
        }

        return new Layout($name, $this->layoutsConfig[$name]);
    }
}
