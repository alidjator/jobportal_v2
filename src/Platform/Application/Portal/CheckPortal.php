<?php

namespace Src\Platform\Application\Portal;

use Src\Platform\Application\PlatformSettings;
use Src\Shared\Application\Settings\Settings;

/**
 * Emails `platform.operations_email` when the set of companies the career site skips changes: once for a new set, once when nothing is skipped any more. `alert_signature`
 * remembers the set the last email named, so the same problem is not mailed every day. With no address nothing is sent and nothing is remembered, so a warning goes out once an
 * address is set while the problem lasts. The marketplace names no company, so there is nothing to check.
 */
final readonly class CheckPortal
{
    public function __construct(
        private Portals $portals,
        private PortalHealth $health,
        private PortalAlerts $alerts,
        private Settings $settings,
    ) {}

    /** @return array{skipped: int, notified: bool} */
    public function execute(): array
    {
        $identity = $this->portals->identity();

        if ($identity === null || $identity->isMarketplace()) {
            return ['skipped' => 0, 'notified' => false];
        }

        $skipped = $this->health->skipped($identity);
        $signature = PortalHealth::signature($skipped);

        if ($signature === $this->portals->alertSignature()) {
            return ['skipped' => count($skipped), 'notified' => false];
        }

        $email = trim($this->settings->text(PlatformSettings::OPERATIONS_EMAIL));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['skipped' => count($skipped), 'notified' => false];
        }

        if ($skipped === []) {
            $this->alerts->recovered($email);
        } else {
            $this->alerts->skipped($email, $skipped);
        }

        $this->portals->rememberAlertSignature($signature);

        return ['skipped' => count($skipped), 'notified' => true];
    }
}
