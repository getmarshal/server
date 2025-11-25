<?php

declare(strict_types= 1);

namespace Marshal\Server\Platform\Web\Template;

use Laminas\Validator\AbstractValidator;

final class TemplateConfigValidator extends AbstractValidator
{
    public const string TEMPLATE_NOT_FOUND_IN_CONFIG = "templateNotFoundInConfig";
    protected array $messageTemplates = [
        self::TEMPLATE_NOT_FOUND_IN_CONFIG => "Template %value% not found in config",
    ];

    public function __construct(private array $config)
    {
        parent::__construct();
    }

    public function isValid(mixed $value): bool
    {
        if (! isset($this->config[$value])) {
            $this->setValue($value);
            $this->error(self::TEMPLATE_NOT_FOUND_IN_CONFIG);
            return FALSE;
        }

        return TRUE;
    }
}
