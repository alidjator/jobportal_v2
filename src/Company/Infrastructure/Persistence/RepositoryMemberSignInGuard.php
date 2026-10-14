<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\MemberStatus;
use Src\Shared\Application\Company\MemberSignInGuard;
use Src\Shared\Domain\Exceptions\AccessDenied;

final readonly class RepositoryMemberSignInGuard implements MemberSignInGuard
{
    public function __construct(private ConnectionInterface $connection) {}

    public function assertMayPass(int $userId): void
    {
        $suspended = $this->connection->table('company_members')->where('user_id', $userId)->where('status', MemberStatus::Suspended->value)->exists();

        if ($suspended) {
            throw new AccessDenied('Your access to the company was suspended by a company admin.', 'membership-suspended');
        }
    }
}
