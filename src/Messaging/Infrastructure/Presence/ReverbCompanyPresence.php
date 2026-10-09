<?php

namespace Src\Messaging\Infrastructure\Presence;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Config\Repository as Config;
use Src\Messaging\Application\CompanyPresence;
use Throwable;

/**
 * Asks the Reverb server (through its Pusher-compatible HTTP API) whether anyone is connected to `presence-company-presence.{companyId}`. Nothing is stored. When
 * Reverb is not the broadcast connection, or cannot be reached, the company reads as offline. The answer is remembered for the rest of the request.
 */
final class ReverbCompanyPresence implements CompanyPresence
{
    /** @var array<int, bool> */
    private array $known = [];

    public function __construct(private readonly BroadcastManager $broadcasting, private readonly Config $config) {}

    public function isOnline(int $companyId): bool
    {
        return $this->known[$companyId] ??= $this->ask($companyId);
    }

    private function ask(int $companyId): bool
    {
        if ($this->config->get('broadcasting.default') !== 'reverb') {
            return false;
        }

        try {
            $broadcaster = $this->broadcasting->connection('reverb');

            if (! $broadcaster instanceof PusherBroadcaster) {
                return false;
            }

            $info = $broadcaster->getPusher()->getChannelInfo('presence-company-presence.'.$companyId, ['info' => 'user_count']);

            return (int) ($info->user_count ?? 0) > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
