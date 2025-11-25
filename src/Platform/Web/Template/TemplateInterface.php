<?php

declare(strict_types=1);

namespace Marshal\Server\Platform\Web\Template;

interface TemplateInterface
{
    public function getCollectionQuery() : array;
    public function getFormat(): string;
    public function getIdentifier(): string;
    public function getMenus(): array;
    public function getQueryParams() : array;
    public function hasCollectionQuery() : bool;
    public function hasQueryParams() : bool;
    public function hasLayout(): bool;
}
