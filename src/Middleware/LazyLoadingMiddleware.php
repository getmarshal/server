<?php

declare(strict_types= 1);

namespace Marshal\Server\Middleware;

use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Laminas\Stratigility\MiddlewarePipe;
use Marshal\Server\Exception\MiddlewareNotFoundException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LazyLoadingMiddleware implements MiddlewareInterface
{
    private $middleware;

    private const array ALLOWED_MIDDLEWARE = [
        'string',
        'array',
        'callable',
        RequestHandlerInterface::class,
        MiddlewareInterface::class
    ];

    public function __construct(
        private ContainerInterface $container,
        string|array|callable|RequestHandlerInterface|MiddlewareInterface $middleware
    ) {
        $this->middleware = $middleware;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->middleware instanceof MiddlewareInterface) {
            return $this->middleware->process($request, $handler);
        }

        // convert RequestHandlerInterface objects to MiddlewareInterface
        if ($this->middleware instanceof RequestHandlerInterface) {
            return (new RequestHandlerMiddleware($this->middleware))->process($request, $handler);
        }

        // convert callables to MiddlewareInterface
        if (\is_callable($this->middleware)) {
            return (new CallableMiddlewareDecorator($this->middleware))->process($request, $handler);
        }

        if (\is_array($this->middleware)) {
            $middleware = new MiddlewarePipe();
            foreach ($this->middleware as $class) {
                if (! $this->isValidMiddlewareType($class)) {
                    $this->throwInvalidMiddlewareException($class);
                }

                $middleware->pipe(new self($this->container, $class));
            }

            return $middleware->process($request, $handler);
        }

        if (! \is_string($this->middleware)) {
            $this->throwInvalidMiddlewareException($this->middleware);
        }
        
        if (! $this->container->has($this->middleware)) {
            throw new MiddlewareNotFoundException($this->middleware);
        }

        $instance = $this->container->get($this->middleware);
        if (! $this->isValidMiddlewareType($instance)) {
            $this->throwInvalidMiddlewareException($instance);
        }

        return (new self($this->container, $instance))->process($request, $handler);
    }

    private function isValidMiddlewareType(mixed $class): bool
    {
        return \is_string($class)
            || \is_array($class)
            || \is_callable($class)
            || $class instanceof RequestHandlerInterface
            || $class instanceof MiddlewareInterface;
    }

    private function throwInvalidMiddlewareException(mixed $class): void
    {
        throw new \InvalidArgumentException(\sprintf(
            "Unsupported middleware type %s. Allowed types include: %s",
            \get_debug_type($class),
            \implode(', ', self::ALLOWED_MIDDLEWARE)
        ));
    }
}
