<?php

namespace Src\Platform\Application\EmailSuppression;

/**
 * Reads the webhook of one email provider. Registered when the SMTP provider is chosen (see `EmailEventParsers`); there is none until then.
 */
interface EmailEventParser
{
    /** The `{provider}` segment of the webhook URL this parser answers. */
    public function provider(): string;

    /**
     * Checks the provider's signature and returns the hard bounces and complaints of the payload (soft bounces are left out).
     *
     * @param  array<string, string>  $headers  the request headers, names in lower case
     * @return list<EmailEvent>
     *
     * @throws InvalidWebhookSignature when the signature does not match
     */
    public function parse(string $body, array $headers): array;
}
