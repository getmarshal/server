<?php

declare(strict_types= 1);

namespace Marshal\Server\Platform\Web\Template\Twig;

use Mezzio\Helper\ServerUrlHelper;
use Mezzio\Helper\UrlHelperInterface;
use Twig\Environment;

final class UrlExtension
{
    public function __construct(
        private ServerUrlHelper $serverUrlHelper,
        private UrlHelperInterface $urlHelper,
        private string $assetsUrl,
        private null|string|int $assetsVersion,
        private ?string $developmentUrl
    ) {
    }

    /**
     * Render media url, optionally versioned
     *
     * Usage: {{ media('path/to/asset/name.ext', version=3) }}
     * Generates: path/to/asset/name.ext?v=3
     */
    public function media($context, ?string $path = '', ?string $version = null): string
    {
        $assetsVersion = $version !== null && $version !== '' ? $version : $this->assetsVersion;

        // One more time, in case $this->assetsVersion was null or an empty string
        $assetsVersion = $assetsVersion !== null && $assetsVersion !== '' ? '?v=' . $assetsVersion : '';

        // media url
        return "/media/$path";
    }

    public function path(
        ?string $route = null,
        array $routeParams = [],
        array $queryParams = [],
        ?string $fragmentIdentifier = null,
        array $options = []
    ): string {
        return $this->urlHelper->generate($route, $routeParams, $queryParams, $fragmentIdentifier, $options);
    }

    public function static(Environment $env, $context, string $path, ?string $version = null): string
    {
        $assetsVersion = $version !== null && $version !== '' ? $version : $this->assetsVersion;

        // One more time, in case $this->assetsVersion was null or an empty string
        $assetsVersion = $assetsVersion !== null && $assetsVersion !== '' ? '?v=' . $assetsVersion : '';

        // asset url
        $assetsUrl = $env->isDebug() ? $this->developmentUrl : $this->assetsUrl;

        return $assetsUrl .  $path;
    }

    public function url(
        ?string $route = null,
        array $routeParams = [],
        array $queryParams = [],
        ?string $fragmentIdentifier = null,
        array $options = []
    ): string {
        return $this->serverUrlHelper->generate(
            $this->path($route, $routeParams, $queryParams, $fragmentIdentifier, $options)
        );
    }
}
