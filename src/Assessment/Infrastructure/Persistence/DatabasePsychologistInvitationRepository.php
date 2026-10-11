<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\PsychologistInvitation;
use Src\Assessment\Domain\PsychologistInvitationRepository;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabasePsychologistInvitationRepository implements PsychologistInvitationRepository
{
    private const string TABLE = 'assessment_psychologist_invitations';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function save(PsychologistInvitation $invitation): PsychologistInvitation
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        if ($invitation->id !== null) {
            $this->connection->table(self::TABLE)->where('id', $invitation->id)->update([
                'accepted_at' => $invitation->acceptedAt()?->format(self::TIME_FORMAT),
                'revoked_at' => $invitation->revokedAt()?->format(self::TIME_FORMAT),
                'updated_at' => $now,
            ]);

            return $invitation;
        }

        $id = $this->connection->table(self::TABLE)->insertGetId([
            'provider_id' => $invitation->providerId,
            'email' => $invitation->email,
            'name' => $invitation->name,
            'str_number' => $invitation->strNumber,
            'str_valid_until' => $invitation->strValidUntil->format(self::DATE_FORMAT),
            'silp_number' => $invitation->silpNumber,
            'silp_valid_until' => $invitation->silpValidUntil->format(self::DATE_FORMAT),
            'token_hash' => $invitation->tokenHash,
            'invited_by_admin_id' => $invitation->invitedByAdminId,
            'sent_at' => $invitation->sentAt->format(self::TIME_FORMAT),
            'expires_at' => $invitation->expiresAt->format(self::TIME_FORMAT),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new PsychologistInvitation(
            (int) $id, $invitation->providerId, $invitation->email, $invitation->name, $invitation->strNumber, $invitation->strValidUntil,
            $invitation->silpNumber, $invitation->silpValidUntil, $invitation->tokenHash, $invitation->invitedByAdminId,
            $invitation->sentAt, $invitation->expiresAt, $invitation->acceptedAt(), $invitation->revokedAt(),
        );
    }

    public function findByTokenHash(string $tokenHash, bool $forUpdate = false): ?PsychologistInvitation
    {
        $query = $this->connection->table(self::TABLE)->where('token_hash', $tokenHash);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toInvitation($row);
    }

    public function findOfProvider(int $providerId, int $id, bool $forUpdate = false): ?PsychologistInvitation
    {
        $query = $this->connection->table(self::TABLE)->where('provider_id', $providerId)->where('id', $id);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toInvitation($row);
    }

    public function pendingOfProvider(int $providerId, DateTimeImmutable $now, bool $forUpdate = false): array
    {
        $query = $this->connection->table(self::TABLE)
            ->where('provider_id', $providerId)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $now->format(self::TIME_FORMAT))
            ->orderByDesc('sent_at')
            ->orderByDesc('id');

        return array_map(
            fn (stdClass $row): PsychologistInvitation => $this->toInvitation($row),
            ($forUpdate ? $query->lockForUpdate() : $query)->get()->all(),
        );
    }

    private function toInvitation(stdClass $row): PsychologistInvitation
    {
        return new PsychologistInvitation(
            (int) $row->id,
            (int) $row->provider_id,
            (string) $row->email,
            (string) $row->name,
            (string) $row->str_number,
            $this->time($row->str_valid_until),
            (string) $row->silp_number,
            $this->time($row->silp_valid_until),
            (string) $row->token_hash,
            (int) $row->invited_by_admin_id,
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
