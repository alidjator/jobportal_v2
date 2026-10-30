<?php

namespace Src\JobInvitation\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The checks every action on the invitations by email starts with: the feature is switched on, the caller is a member of an active company who holds the permission
 * (`applications.manage` to send, resend and cancel, `applications.view` to read) and sees the job. The person's side only needs the feature switched on.
 */
final readonly class EmailInvitationAccess
{
    public function __construct(private CompanyDirectory $companies, private JobDirectory $jobs, private Settings $settings) {}

    /** @throws EntityNotFound the feature is off (the person's side looks the same as a link that does not exist) */
    public function assertEnabled(): void
    {
        if (! $this->settings->boolean(InvitationSettings::EMAIL_ENABLED)) {
            throw EntityNotFound::of('Invitation', 'email');
        }
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required`, `company-suspended` or `feature-disabled`
     * @throws EntityNotFound the job (missing, foreign or not the member's)
     */
    public function member(Actor $actor, CompanyPermission $permission, int $jobId): InvitingMember
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        $actor->membership->require($permission);

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        if (! $this->settings->boolean(InvitationSettings::EMAIL_ENABLED)) {
            throw new AccessDenied('Invitations by email are not available yet.', 'feature-disabled');
        }

        $member = new InvitingMember($actor->id, $actor->membership->companyId, $actor->can(CompanyPermission::JobsViewAll));
        $this->jobs->visibleToMember($jobId, $member->companyId, $member->userId, $member->seesAllJobs) ?? throw EntityNotFound::of('Job', $jobId);

        return $member;
    }

    /**
     * @return int the id of the job seeker's account
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function jobSeeker(Actor $actor): int
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can claim an invitation.', 'job-seeker-only');
        }

        return $actor->id;
    }
}
