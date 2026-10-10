<?php

namespace Src\Platform\Application\Email;

use Src\Shared\Application\Email\AdHocEmails;
use Src\Shared\Application\Email\RenderedEmail;

final readonly class PlatformAdHocEmails implements AdHocEmails
{
    public function __construct(private EmailBodyRenderer $renderer) {}

    public function render(string $subject, string $markdown): RenderedEmail
    {
        return new RenderedEmail(
            trim((string) preg_replace('/\s*[\r\n]+\s*/', ' ', $subject)),
            $this->renderer->html($markdown),
            $this->renderer->text($markdown),
        );
    }
}
