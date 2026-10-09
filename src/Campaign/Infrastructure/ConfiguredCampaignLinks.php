<?php

namespace Src\Campaign\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Src\Campaign\Application\CampaignLinks;

/** Builds the link from the client URL template in `app.campaign_registration_url` (env `CAMPAIGN_REGISTRATION_URL`), where `{code}` stands for the campaign code. */
final readonly class ConfiguredCampaignLinks implements CampaignLinks
{
    private const string PLACEHOLDER = '{code}';

    public function __construct(private Repository $config) {}

    public function forCode(string $code): ?string
    {
        $template = $this->config->get('app.campaign_registration_url');

        return is_string($template) && str_contains($template, self::PLACEHOLDER) ? str_replace(self::PLACEHOLDER, rawurlencode($code), $template) : null;
    }
}
