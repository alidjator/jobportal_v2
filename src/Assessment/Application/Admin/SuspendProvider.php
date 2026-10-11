<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\ProviderRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Suspends a provider: its psychologists keep their accounts, but their reports cannot be signed while the provider is suspended. */
final readonly class SuspendProvider
{
    public function __construct(private ProviderRepository $providers) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $providerId): void
    {
        AdminOnly::assert($actor, 'suspend a provider');

        $provider = $this->providers->findById($providerId) ?? throw EntityNotFound::of('Provider', $providerId);

        $provider->suspend();
        $this->providers->save($provider);
    }
}
