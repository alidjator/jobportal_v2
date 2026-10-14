<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyEmailDomainsChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A company admin sets the email domains invitations are limited to; an empty list lifts the limit. Pending invitations stay as they are. Saving what is already set changes and audits nothing. */
final readonly class UpdateEmailDomains
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  list<string>  $domains
     * @return list<string> the domains now in force, empty when there is no limit
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws BusinessRuleViolation `invalid-email-domain`
     */
    public function execute(Actor $actor, array $domains, ?string $ipAddress, ?string $userAgent): array
    {
        $company = $this->guard->company($actor);
        $companyId = (int) $company->id;
        $after = InvitationEmailDomains::normalized($domains);

        $changed = $this->transactions->run(function () use ($companyId, $after): ?array {
            $this->companies->lock($companyId);
            $before = $this->companies->invitationEmailDomains($companyId);

            if ($before === $after) {
                return null;
            }

            $this->companies->setInvitationEmailDomains($companyId, $after);

            return ['before' => $before];
        });

        if ($changed !== null) {
            $this->events->dispatch([new CompanyEmailDomainsChanged($companyId, $changed['before'], $after, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
        }

        return $after ?? [];
    }
}
