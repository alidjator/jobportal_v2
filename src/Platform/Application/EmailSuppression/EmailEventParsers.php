<?php

namespace Src\Platform\Application\EmailSuppression;

/** The parsers of the email providers this deployment accepts webhooks from. Empty until the SMTP provider is chosen. */
final class EmailEventParsers
{
    /** @var array<string, EmailEventParser> */
    private array $parsers = [];

    public function register(EmailEventParser $parser): void
    {
        $this->parsers[$parser->provider()] = $parser;
    }

    public function find(string $provider): ?EmailEventParser
    {
        return $this->parsers[$provider] ?? null;
    }
}
