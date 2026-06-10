<?php

declare(strict_types=1);

namespace Marshal\Server\Exception;

use Marshal\Utils\Logger\LoggerManager;

final class MiddlewareNotFoundException extends \RuntimeException
{
    public function __construct(string $middleware)
    {
        LoggerManager::get()->error(\sprintf(
            "Middleware %s not found",
            $middleware
        ));
        
        parent::__construct(\sprintf(
            "Middleware %s not found",
            $middleware
        ));
    }
}
