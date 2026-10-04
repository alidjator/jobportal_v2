<?php

namespace Src\Shared\Application\Email;

/** A template with its variables filled in, ready to send: the subject, the HTML body (inside the standard email layout) and a plain-text alternative. */
final readonly class RenderedEmail
{
    public function __construct(
        public string $subject,
        public string $html,
        public string $text,
    ) {}
}
