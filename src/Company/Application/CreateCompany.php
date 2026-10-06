<?php

namespace Src\Company\Application;

use Src\Company\Application\Invitations\InvitationLinkBuilder;
use Src\Company\Application\Invitations\InvitationSender;
use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\Events\CompanyCreated;
use Src\Company\Domain\Events\CompanyInvitationSent;
use Src\Company\Domain\Invitation;
use Src\Company\Domain\InvitationRepository;
use Src\Company\Domain\InvitationToken;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * An admin creates a company and invites its first company admin. The admin gives only the required minimum; the company completes its
 * own profile. The admin never sees a password: the invitee sets it when accepting.
 */
final readonly class CreateCompany
{
    private const int SLUG_MAX_LENGTH = 60;

    public function __construct(
        private CompanyRepository $companies,
        private InvitationRepository $invitations,
        private CompanyMemberAccounts $accounts,
        private ReferenceValues $references,
        private CompanySlugGenerator $slugs,
        private InvitationLinkBuilder $links,
        private InvitationSender $sender,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
        private DefaultStageProvisioner $stages,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws BusinessRuleViolation `unknown-industry` or `unknown-organization-type` for a classification that is missing or inactive
     * @throws StateConflict `email-already-registered` when the email already has an account
     */
    public function execute(Actor $actor, CreateCompanyCommand $command): CreatedCompany
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can create a company.');
        }

        if (! $this->references->isActive('industries', $command->industryId)) {
            throw new BusinessRuleViolation('The industry does not exist or is not active.', 'unknown-industry');
        }

        if (! $this->references->isActive('organization-types', $command->organizationTypeId)) {
            throw new BusinessRuleViolation('The organization type does not exist or is not active.', 'unknown-organization-type');
        }

        $email = mb_strtolower(trim($command->adminEmail));

        if ($this->accounts->emailIsRegistered($email)) {
            throw new StateConflict('An account with this email already exists.', 'email-already-registered');
        }

        $this->links->assertAvailable();

        $now = $this->clock->now();
        $token = InvitationToken::generate();

        [$company, $invitation] = $this->transactions->run(function () use ($actor, $command, $email, $token, $now): array {
            $company = $this->companies->save(Company::create(
                $command->name, $this->uniqueSlug($command->name), $command->industryId, $command->organizationTypeId, (int) $actor->id,
            ));
            $invitation = $this->invitations->save(Invitation::issue(
                (int) $company->id, $email, CompanyRole::CompanyAdmin, $token->hash(), null, (int) $actor->id, $now,
            ));
            $this->stages->provision((int) $company->id);

            return [$company, $invitation];
        });

        $this->sender->send($email, $command->language, $company->name(), $invitation->role, $this->links->build($token->plain), $invitation->expiresAt);
        $this->events->dispatch([
            new CompanyCreated((int) $company->id, (int) $actor->id, $command->ipAddress, $command->userAgent, $now),
            new CompanyInvitationSent(
                (int) $company->id, (int) $invitation->id, $invitation->role->value, null, (int) $actor->id, $command->ipAddress, $command->userAgent, $now,
            ),
        ]);

        return new CreatedCompany($company, $invitation);
    }

    /** The slug of the name, or with `-2`, `-3`, ... when it is taken. The base is cut so the suffix still fits. */
    private function uniqueSlug(string $name): string
    {
        $base = $this->slugs->make($name);

        if (! $this->companies->slugExists($base)) {
            return $base;
        }

        for ($number = 2; ; $number++) {
            $suffix = '-'.$number;
            $candidate = rtrim(mb_substr($base, 0, self::SLUG_MAX_LENGTH - strlen($suffix)), '-').$suffix;

            if (! $this->companies->slugExists($candidate)) {
                return $candidate;
            }
        }
    }
}
