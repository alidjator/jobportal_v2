<?php

namespace Src\Company\Application\NameChange;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyNameChangeCancelled;
use Src\Company\Domain\NameChangeRequest;
use Src\Company\Domain\NameChangeRequestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A member who may change the company profile withdraws a request that is still pending. The document is deleted with the others, 90 days from now. */
final readonly class CancelNameChangeRequest
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private NameChangeRequestRepository $requests,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the request is not one of the company
     * @throws StateConflict `name-change-not-pending`
     */
    public function execute(Actor $actor, int $requestId, ?string $ipAddress, ?string $userAgent): NameChangeRequest
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);
        $companyId = (int) $company->id;
        $now = $this->clock->now();

        $cancelled = $this->transactions->run(function () use ($companyId, $requestId, $now): NameChangeRequest {
            $this->companies->lock($companyId);
            $request = $this->requests->find($requestId);

            if ($request === null || $request->companyId !== $companyId) {
                throw EntityNotFound::of('Name change request', $requestId);
            }

            $cancelled = $request->cancel($now);
            $this->requests->save($cancelled);

            return $cancelled;
        });

        $this->events->dispatch([new CompanyNameChangeCancelled($companyId, $requestId, (int) $actor->id, $ipAddress, $userAgent, $now)]);

        return $cancelled;
    }
}
