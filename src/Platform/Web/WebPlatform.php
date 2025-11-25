<?php

declare(strict_types= 1);

namespace Marshal\Server\Platform\Web;

use Fig\Http\Message\StatusCodeInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Marshal\EventManager\EventDispatcherAwareInterface;
use Marshal\EventManager\EventDispatcherAwareTrait;
use Marshal\Server\Platform\ConfigProvider;
use Marshal\Server\Platform\PlatformInterface;
use Marshal\Server\Platform\Web\Event\RenderTemplateEvent;
use Marshal\Server\Platform\Web\Template\TemplateManager;
use Marshal\Util\Logger\LoggerFactoryAwareInterface;
use Marshal\Util\Logger\LoggerFactoryAwareTrait;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class WebPlatform implements EventDispatcherAwareInterface, LoggerFactoryAwareInterface, PlatformInterface
{
    use EventDispatcherAwareTrait;
    use LoggerFactoryAwareTrait;

    private const string PLATFORM_LOGGER = ConfigProvider::PLATFORM_LOGGER;

    public function __construct(private TemplateManager $templateManager)
    {
    }

    public function formatResponse(
        ServerRequestInterface $request,
        array|\Traversable $data = [],
        int $status = StatusCodeInterface::STATUS_OK,
        array $headers = [],
        array $options = [],
        string $message = ""
    ): ResponseInterface {
        // json responses
        if (FALSE !== \strpos($request->getHeaderLine('accept'), 'application/json')) {
            return new JsonResponse($data, $status, $headers);
        }

        if ($status !== StatusCodeInterface::STATUS_OK) {
            switch ($status) {
                case StatusCodeInterface::STATUS_UNAUTHORIZED:
                    $template = "marshal::error-401";
                    break;

                case StatusCodeInterface::STATUS_SERVICE_UNAVAILABLE:
                    $template = "marshal::error-500";
                    break;

                case StatusCodeInterface::STATUS_NOT_FOUND:
                default:
                    $template = "marshal::error-404";
                    break;
            }
            $options['template'] = $template;
        }

        $template = $this->templateManager->get($this->getTemplateName($request, $options));
        $event = new RenderTemplateEvent($template, $data);
        $this->getEventDispatcher()->dispatch($event);

        // return a html response
        return new HtmlResponse($event->getContents(), $status, $headers);
    }

    public function getRoutes(): array
    {
        return [];
    }

    private function getTemplateName(ServerRequestInterface $request, array $options): string
    {
        if (isset($options['template']) && \is_string($options['template'])) {
            return $options['template'];
        }

        $routeResult = $request->getAttribute(RouteResult::class);
        if (! $routeResult instanceof RouteResult || $routeResult->isFailure()) {
            return "marshal::error-404";
        }

        $routeOptions = $routeResult->getMatchedRoute()->getOptions();
        if (! isset($routeOptions['template']) || ! \is_string($routeOptions['template'])) {
            return "marshal::error-404";
        }

        return $routeOptions['template'];
    }
}
