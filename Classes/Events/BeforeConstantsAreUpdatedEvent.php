<?php

declare(strict_types=1);

namespace KayStrobach\Themes\Events;

use Psr\Http\Message\ServerRequestInterface;

final class BeforeConstantsAreUpdatedEvent
{
    protected $sysTemplateShouldBeUpdated = true;

    public function __construct(protected array $constants, protected ServerRequestInterface $request) {}

    public function getConstants(): array
    {
        return $this->constants;
    }

    public function skipSystemplateUpdate(): void
    {
        $this->sysTemplateShouldBeUpdated = false;
    }

    public function shouldSysTemplateBeUpdated(): bool
    {
        return $this->sysTemplateShouldBeUpdated;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
