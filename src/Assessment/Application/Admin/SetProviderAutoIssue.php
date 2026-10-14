<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\Events\ProviderAutoIssueChanged;
use Src\Assessment\Domain\Provider;
use Src\Assessment\Domain\ProviderRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A platform admin allows or stops the automatic issue of reports for a provider (off until allowed, after the legal review). Stopping it does not touch the
 * batteries' own setting: their reports simply fall back to the psychologists' queue. Every change is audited.
 */
final readonly class SetProviderAutoIssue
{
    public function __construct(
        private ProviderRepository $providers,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $providerId, bool $allowed, ?string $ipAddress, ?string $userAgent): Provider
    {
        AdminOnly::assert($actor, 'allow a provider to issue reports automatically');

        [$provider, $changed] = $this->transactions->run(function () use ($providerId, $allowed): array {
            $provider = $this->providers->findById($providerId, forUpdate: true) ?? throw EntityNotFound::of('Provider', $providerId);
            $changed = $provider->autoIssueAllowed() !== $allowed;

            if ($changed) {
                $provider->allowAutoIssue($allowed);
                $this->providers->save($provider);
            }

            return [$provider, $changed];
        });

        if ($changed) {
            $this->events->dispatch([new ProviderAutoIssueChanged($providerId, $allowed, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
        }

        return $provider;
    }
}
