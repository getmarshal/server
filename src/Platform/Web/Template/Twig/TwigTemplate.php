<?php

declare(strict_types=1);

namespace Marshal\Server\Platform\Web\Template\Twig;

use Marshal\Server\Platform\Web\Template\TemplateInterface;

final class TwigTemplate implements TemplateInterface
{
    public const string TEMPLATE_FORMAT = "twig";

    public function __construct(private string $identifier, private array $config)
    {
    }

    public function getCollectionQuery(): array
    {
        return $this->config['collection'] ?? [];
    }

    public function getFilename(): string
    {
        return $this->config['filename'];
    }

    public function getFormat(): string
    {
        return self::TEMPLATE_FORMAT;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getIncludes(): array
    {
        $includes = $this->config['includes'] ?? [];
        if (! \is_array($includes)) {
            return [];
        }

        return $includes;
    }

    public function getMenus(): array
    {
        return $this->config['menus'] ?? [];
    }

    public function getQueryParams(): array
    {
        return $this->config['query_params'] ?? [];
    }

    public function hasCollectionQuery(): bool
    {
        return isset($this->config['collection']) && \is_array($this->config['collection']);
    }

    public function hasQueryParams(): bool
    {
        return isset($this->config['query_params']) && \is_array($this->config['query_params']);
    }

    public function hasLayout(): bool
    {
        return FALSE;
    }
}
