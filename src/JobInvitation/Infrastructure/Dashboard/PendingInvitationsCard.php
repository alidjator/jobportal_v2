<?php

namespace Src\JobInvitation\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\JobInvitation\Domain\InvitationStatus;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The invitations to apply that the job seeker has not answered: not opened yet (`new`) and opened (`pending` counts both). */
final readonly class PendingInvitationsCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'invitations';
    }

    public function audiences(): array
    {
        return [DashboardAudience::JobSeeker];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function build(DashboardScope $scope): array
    {
        $new = $this->connection->table('jobinvitation_invitations')->where('user_id', $scope->userId)->where('status', InvitationStatus::Sent->value)->count();
        $viewed = $this->connection->table('jobinvitation_invitations')->where('user_id', $scope->userId)->where('status', InvitationStatus::Viewed->value)->count();

        return ['new' => $new, 'pending' => $new + $viewed];
    }
}
