<?php

declare(strict_types=1);

namespace Marshal\Server\Handler;

use Marshal\Platform\PlatformInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HomeHandler implements RequestHandlerInterface
{
    public const string ROUTE_NAME = "marshal::home";
    public const string TEMPLATE_HOME = "marshal::home";

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $platform = $request->getAttribute(PlatformInterface::class);
        \assert($platform instanceof PlatformInterface);

        return $platform->formatResponse($request, template: self::TEMPLATE_HOME);
    }
}
