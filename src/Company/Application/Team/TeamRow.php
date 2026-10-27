<?php

namespace Src\Company\Application\Team;

use DateTimeImmutable;
use Src\Company\Domain\CompanyRole;

/**
 * One row of the team list: a member or an open invitation. A member row has `status` `active` or `suspended`, an invitation row `pending` or `expired`;
 * `twoFactor` (members only) is `on`, `off` or `pending` (required, not enrolled yet); `assignedJobs` (members only) is the number of jobs they are assigned to. `actions` are the row actions the caller may take now (`edit`, `remove`, `suspend`, `reactivate`, `make_owner`, `resend`, `revoke`).
 */
final readonly class TeamRow
{
    public const string TYPE_MEMBER = 'member';

    public const string TYPE_INVITATION = 'invitation';

    /**
     * @param  list<string>  $permissions  every permission for a company admin
     * @param  list<string>  $actions
     */
    public function __construct(
        public string $type,
        public int $id,
        public string $name,
        public string $email,
        public CompanyRole $role,
        public bool $isOwner,
        public string $status,
        public array $permissions,
        public array $actions,
        public ?DateTimeImmutable $joinedAt,
        public ?DateTimeImmutable $lastLoginAt,
        public ?DateTimeImmutable $sentAt,
        public ?DateTimeImmutable $expiresAt,
        public ?string $invitedBy,
        public ?string $twoFactor = null,
        public ?int $assignedJobs = null,
        public bool $hasAvatar = false,
    ) {}

    /** The position in the list: the owner, then the other company admins, then the members. */
    public function group(): int
    {
        return $this->isOwner ? 0 : ($this->role === CompanyRole::CompanyAdmin ? 1 : 2);
    }
}
