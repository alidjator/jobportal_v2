<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\Provider;
use Src\Assessment\Domain\ProviderRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

final readonly class UpdateProvider
{
    public function __construct(private ProviderRepository $providers) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     * @throws StateConflict `provider-name-taken` or `provider-code-taken`
     */
    public function execute(Actor $actor, int $providerId, string $code, string $name, string $legalName): Provider
    {
        AdminOnly::assert($actor, 'change a provider');

        $provider = $this->providers->findById($providerId) ?? throw EntityNotFound::of('Provider', $providerId);
        $name = trim($name);

        if ($this->providers->nameIsTaken($name, $providerId)) {
            throw new StateConflict('A provider with this name already exists.', 'provider-name-taken');
        }

        if ($this->providers->codeIsTaken($code, $providerId)) {
            throw new StateConflict('A provider with this code already exists.', 'provider-code-taken');
        }

        $provider->rename($code, $name, trim($legalName));

        return $this->providers->save($provider);
    }
}
