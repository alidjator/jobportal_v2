<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\Events\ProviderRegistered;
use Src\Assessment\Domain\Provider;
use Src\Assessment\Domain\ProviderRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A platform admin registers a partner provider. The name is unique, compared case-insensitively; so is the code, which opens the numbers of its reports. */
final readonly class RegisterProvider
{
    public function __construct(
        private ProviderRepository $providers,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws StateConflict `provider-name-taken` or `provider-code-taken`
     */
    public function execute(Actor $actor, string $code, string $name, string $legalName, ?string $ipAddress, ?string $userAgent): Provider
    {
        AdminOnly::assert($actor, 'register a provider');

        $name = trim($name);

        if ($this->providers->nameIsTaken($name)) {
            throw new StateConflict('A provider with this name already exists.', 'provider-name-taken');
        }

        if ($this->providers->codeIsTaken($code)) {
            throw new StateConflict('A provider with this code already exists.', 'provider-code-taken');
        }

        $provider = $this->providers->save(Provider::register($code, $name, trim($legalName)));
        $this->events->dispatch([new ProviderRegistered((int) $provider->id, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);

        return $provider;
    }
}
