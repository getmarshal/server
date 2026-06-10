<?php

declare(strict_types=1);

namespace Marshal\Server\Event;

use Marshal\Platform\PlatformInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HttpResponseEvent
{
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly PlatformInterface $platform)
    {
    }

    public function getPlatform(): PlatformInterface
    {
        return $this->platform;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
