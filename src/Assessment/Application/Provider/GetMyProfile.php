<?php

namespace Src\Assessment\Application\Provider;

use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The psychologist reads their own profile: provider, licences and whether signing is allowed. A provider that is suspended also blocks signing. */
final readonly class GetMyProfile
{
    public function __construct(
        private PsychologistRepository $psychologists,
        private ProviderRepository $providers,
        private PsychologistAccounts $accounts,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not a psychologist
     * @throws EntityNotFound when the account has no psychologist record
     */
    public function execute(Actor $actor): PsychologistProfile
    {
        if ($actor->kind !== ActorKind::Psychologist || $actor->id === null) {
            throw new AccessDenied('Only a psychologist can read this profile.');
        }

        $psychologist = $this->psychologists->findByUserId($actor->id) ?? throw EntityNotFound::of('Psychologist');
        $provider = $this->providers->findById($psychologist->providerId) ?? throw EntityNotFound::of('Provider', $psychologist->providerId);

        return new PsychologistProfile(
            $psychologist,
            $provider,
            $this->accounts->summaries([$actor->id])[$actor->id] ?? null,
            $provider->isActive() && $psychologist->canSignReports($this->clock->now()),
        );
    }
}
