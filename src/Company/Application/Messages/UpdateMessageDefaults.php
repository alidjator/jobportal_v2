<?php

namespace Src\Company\Application\Messages;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\Events\CompanyMessageDefaultsChanged;
use Src\Company\Domain\MessageDefaults;
use Src\Company\Domain\MessageDefaultsRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A member who may change the company profile sets the default texts. Only the scenarios in the request change (a blank text clears one) and each text follows the
 * link rule. Saving what is already set changes and audits nothing; the audit names the scenarios, never the texts.
 */
final readonly class UpdateMessageDefaults
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private MessageDefaultsRepository $defaults,
        private CandidateMessageRules $rules,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, ?string>  $changes  keyed by scenario
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws BusinessRuleViolation `message-link-not-allowed`
     */
    public function execute(Actor $actor, array $changes, ?string $ipAddress, ?string $userAgent): MessageDefaults
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);
        $companyId = (int) $company->id;

        foreach ($changes as $scenario => $text) {
            $this->rules->assertAllowed($companyId, $text, $scenario);
        }

        [$after, $changed] = $this->transactions->run(function () use ($companyId, $changes, $actor): array {
            $before = $this->defaults->find($companyId);
            $after = $before->with($changes);
            $changed = array_keys(array_diff_assoc($after->toArray(), $before->toArray()));

            if ($changed !== []) {
                $this->defaults->save($companyId, $after, (int) $actor->id);
            }

            return [$after, $changed];
        });

        if ($changed !== []) {
            $this->events->dispatch([new CompanyMessageDefaultsChanged($companyId, $changed, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
        }

        return $after;
    }
}
