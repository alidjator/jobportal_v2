<?php

namespace Src\Platform\Application\Email;

use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class GetEmailTemplate
{
    public function __construct(private EmailTemplateViews $views) {}

    /** @throws EntityNotFound */
    public function execute(string $key): EmailTemplateView
    {
        return $this->views->one($key);
    }
}
