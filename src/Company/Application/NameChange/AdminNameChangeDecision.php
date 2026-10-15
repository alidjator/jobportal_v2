<?php

namespace Src\Company\Application\NameChange;

use DateTimeImmutable;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyNameChangeDecided;
use Src\Company\Domain\NameChangeRequest;
use Src\Company\Domain\NameChangeRequestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * What an admin's decision on a name change request has in common: only an admin may decide, the company row is locked, the request must still be pending
 * (409 `name-change-not-pending` otherwise), and the decision is announced after the transaction committed. A subclass says which decision it is and what
 * it changes.
 */
abstract class AdminNameChangeDecision
{
    public function __construct(
        protected readonly CompanyRepository $companies,
        private readonly NameChangeRequestRepository $requests,
        private readonly TransactionManager $transactions,
        private readonly EventDispatcher $events,
        private readonly Clock $clock,
    ) {}

    /** One of the `CompanyNameChangeDecided` decision names. */
    abstract protected function decision(): string;

    /** The request after the decision, with whatever else the decision changes saved (the company's name, for an approval). */
    abstract protected function apply(NameChangeRequest $request, int $adminId, ?string $note, DateTimeImmutable $at): NameChangeRequest;

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the request does not exist
     * @throws StateConflict `name-change-not-pending`
     */
    protected function run(Actor $actor, int $requestId, ?string $note, ?string $ipAddress, ?string $userAgent): void
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can decide about name change requests.');
        }

        $adminId = (int) $actor->id;
        $now = $this->clock->now();

        $event = $this->transactions->run(function () use ($requestId, $adminId, $note, $ipAddress, $userAgent, $now): CompanyNameChangeDecided {
            $request = $this->requests->find($requestId) ?? throw EntityNotFound::of('Name change request', $requestId);
            $this->companies->lock($request->companyId);
            // Read again under the lock: another admin may have decided it meanwhile.
            $request = $this->requests->find($requestId) ?? throw EntityNotFound::of('Name change request', $requestId);

            $decided = $this->apply($request, $adminId, $note, $now);
            $this->requests->save($decided);

            return new CompanyNameChangeDecided($request->companyId, $requestId, $this->decision(), $request->currentName, $request->newName, $note, $adminId, $ipAddress, $userAgent, $now);
        });

        $this->events->dispatch([$event]);
    }
}
