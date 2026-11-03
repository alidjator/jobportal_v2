<?php

namespace Src\Assessment\Application\Institution;

use Src\Assessment\Application\Team\TeamAccess;
use Src\Assessment\Domain\Provider;
use Src\Assessment\Domain\ProviderRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A lead edits the identity of their institution (address, practice licence number, phone and email) and its review target in working days. Only the fields that are sent change; a blank value clears an identity field. */
final readonly class UpdateInstitution
{
    public function __construct(
        private TeamAccess $access,
        private ProviderRepository $providers,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  array<string, string|null>  $changes  keys `address`, `practice_licence_number`, `phone`, `email`
     *
     * @throws AccessDenied `not-a-lead` and the refusals of `TeamAccess`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, array $changes, ?int $reviewTargetDays = null): Provider
    {
        $lead = $this->access->lead($actor);

        return $this->transactions->run(function () use ($lead, $changes, $reviewTargetDays): Provider {
            $provider = $this->providers->findById($lead->providerId, forUpdate: true) ?? throw EntityNotFound::of('Provider', $lead->providerId);
            $provider->changeIdentity($provider->identity()->with($changes));

            if ($reviewTargetDays !== null) {
                $provider->changeReviewTarget($reviewTargetDays);
            }

            return $this->providers->save($provider);
        });
    }
}
