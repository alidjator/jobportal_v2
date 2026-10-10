<?php

namespace Src\Newsletter\Application;

use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/** The links in the emails: the confirmation and unsubscribe pages of the client (from URL templates), and the API address that unsubscribes in one click. */
interface NewsletterLinks
{
    /** @throws ExternalServiceUnavailable `newsletter-unavailable` when a client URL template is not configured */
    public function assertAvailable(): void;

    public function confirmation(string $token): string;

    public function unsubscription(string $token): string;

    /** The API address a mail program posts to (`List-Unsubscribe-Post`). */
    public function oneClickUnsubscription(string $token): string;
}
