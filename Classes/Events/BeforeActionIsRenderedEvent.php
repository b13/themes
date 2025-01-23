<?php

declare(strict_types=1);

namespace KayStrobach\Themes\Events;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;

final class BeforeActionIsRenderedEvent
{
    protected array $additionalUrlParams = [];

    public function __construct(protected ModuleTemplate $moduleTemplate, protected ServerRequestInterface $request) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getModuleTemplate(): ModuleTemplate
    {
        return $this->moduleTemplate;
    }

    public function setAdditionalUrlParams(array $additionalUrlParams): void
    {
        $this->additionalUrlParams = $additionalUrlParams;
    }

    public function getAdditionalUrlParams(): array
    {
        return $this->additionalUrlParams;
    }
}
