<?php

declare(strict_types=1);

namespace KayStrobach\Themes\Events;

use Psr\Http\Message\ServerRequestInterface;

final class AfterSystemplatesAreLoadedEvent
{
    public function __construct(protected array $sysTemplateRows, protected ServerRequestInterface $request) {}

    public function getSysTemplateRows(): array
    {
        return $this->sysTemplateRows;
    }

    public function setSysTemplateRows(array $sysTemplateRows): void
    {
        $this->sysTemplateRows = $sysTemplateRows;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getSysTemplateConstants(): string
    {
        return $this->sysTemplateRows[count($this->sysTemplateRows) - 1]['constants'] ?? '';
    }
}
