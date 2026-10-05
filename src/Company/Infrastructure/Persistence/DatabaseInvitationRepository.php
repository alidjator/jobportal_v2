<?php

namespace Src\Company\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\Invitation;
use Src\Company\Domain\InvitationRepository;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseInvitationRepository implements InvitationRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function save(Invitation $invitation): Invitation
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        if ($invitation->id !== null) {
            $this->connection->table('company_invitations')->where('id', $invitation->id)->update([
                'accepted_at' => $invitation->acceptedAt()?->format(self::TIME_FORMAT),
                'revoked_at' => $invitation->revokedAt()?->format(self::TIME_FORMAT),
                'updated_at' => $now,
            ]);

            return $invitation;
        }

        $id = $this->connection->table('company_invitations')->insertGetId([
            'company_id' => $invitation->companyId,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'token_hash' => $invitation->tokenHash,
            'invited_by_user_id' => $invitation->invitedByUserId,
            'invited_by_admin_id' => $invitation->invitedByAdminId,
            'sent_at' => $invitation->sentAt->format(self::TIME_FORMAT),
            'expires_at' => $invitation->expiresAt->format(self::TIME_FORMAT),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new Invitation(
            (int) $id, $invitation->companyId, $invitation->email, $invitation->role, $invitation->tokenHash, $invitation->invitedByUserId,
            $invitation->invitedByAdminId, $invitation->sentAt, $invitation->expiresAt, $invitation->acceptedAt(), $invitation->revokedAt(),
        );
    }

    public function findByTokenHash(string $tokenHash, bool $forUpdate = false): ?Invitation
    {
        $query = $this->connection->table('company_invitations')->where('token_hash', $tokenHash);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toInvitation($row);
    }

    private function toInvitation(stdClass $row): Invitation
    {
        return new Invitation(
            (int) $row->id,
            (int) $row->company_id,
            (string) $row->email,
            CompanyRole::from($row->role),
            (string) $row->token_hash,
            $row->invited_by_user_id === null ? null : (int) $row->invited_by_user_id,
            $row->invited_by_admin_id === null ? null : (int) $row->invited_by_admin_id,
            $this->time($row->sent_at),
            $this->time($row->expires_at),
            $row->accepted_at === null ? null : $this->time($row->accepted_at),
            $row->revoked_at === null ? null : $this->time($row->revoked_at),
        );
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
