<?php

namespace Src\Company\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Src\Company\Application\Invitations\InvitationLinkBuilder;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/** Builds the link from the client URL template in `app.invitation_url` (env `COMPANY_INVITATION_URL`), where `{token}` stands for the token. */
final readonly class ConfiguredInvitationLinkBuilder implements InvitationLinkBuilder
{
    private const string PLACEHOLDER = '{token}';

    public function __construct(private Repository $config) {}

    public function build(string $plainToken): string
    {
        return str_replace(self::PLACEHOLDER, rawurlencode($plainToken), $this->template());
    }

    public function assertAvailable(): void
    {
        $this->template();
    }

    private function template(): string
    {
        $template = $this->config->get('app.invitation_url');

        if (! is_string($template) || ! str_contains($template, self::PLACEHOLDER)) {
            throw new ExternalServiceUnavailable('Invitations cannot be sent because the invitation link is not configured.', 'invitation-link-unavailable');
        }

        return $template;
    }
}
