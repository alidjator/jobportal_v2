<?php

namespace Src\JobInvitation\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\JobInvitation\Domain\EmailInvitation;
use Src\JobInvitation\Domain\EmailInvitationChannel;
use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\JobInvitation\Domain\EmailInvitationStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;
use stdClass;

final readonly class DatabaseEmailInvitationRepository implements EmailInvitationRepository
{
    private const string TABLE = 'jobinvitation_email_invitations';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(EmailInvitation $invitation): EmailInvitation
    {
        try {
            $id = (int) $this->connection->table(self::TABLE)->insertGetId([
                'company_id' => $invitation->companyId,
                'job_id' => $invitation->jobId,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'channel' => $invitation->channel->value,
                'note' => $invitation->note,
                'token_hash' => $invitation->tokenHash,
                'cv_path' => $invitation->cvPath,
                'cv_name' => $invitation->cvName,
                'status' => $invitation->status->value,
                'invited_by_user_id' => $invitation->invitedByUserId,
                'expires_at' => $this->format($invitation->expiresAt),
                'resent_count' => $invitation->resentCount,
                'last_sent_at' => $this->format($invitation->lastSentAt),
                'created_at' => $this->format($invitation->createdAt),
                'updated_at' => $this->format($invitation->updatedAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('This person was already invited to the job.', 'already-invited');
        }

        return $this->find($id) ?? throw new \LogicException('The new invitation was not found.');
    }

    public function save(EmailInvitation $invitation): void
    {
        $this->connection->table(self::TABLE)->where('id', $invitation->id)->update([
            'name' => $invitation->name,
            'email' => $invitation->email,
            'token_hash' => $invitation->tokenHash,
            'cv_path' => $invitation->cvPath,
            'cv_name' => $invitation->cvName,
            'status' => $invitation->status->value,
            'claimed_by_user_id' => $invitation->claimedByUserId,
            'application_id' => $invitation->applicationId,
            'opened_at' => $this->formatOrNull($invitation->openedAt),
            'responded_at' => $this->formatOrNull($invitation->respondedAt),
            'resent_count' => $invitation->resentCount,
            'last_sent_at' => $this->format($invitation->lastSentAt),
            'updated_at' => $this->format($invitation->updatedAt),
        ]);
    }

    public function find(int $id, bool $forUpdate = false): ?EmailInvitation
    {
        $query = $this->connection->table(self::TABLE)->where('id', $id);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toInvitation($row);
    }

    public function findByTokenHash(string $tokenHash, bool $forUpdate = false): ?EmailInvitation
    {
        $query = $this->connection->table(self::TABLE)->where('token_hash', $tokenHash);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toInvitation($row);
    }

    public function openExists(int $jobId, string $email): bool
    {
        return $this->connection->table(self::TABLE)->where('job_id', $jobId)->whereRaw('lower(email) = ?', [mb_strtolower($email)])->whereIn('status', ['sent', 'opened'])->exists();
    }

    public function countOfCompanySince(int $companyId, DateTimeImmutable $since): int
    {
        return $this->connection->table(self::TABLE)->where('company_id', $companyId)->where('created_at', '>=', $this->format($since))->count();
    }

    public function ofJob(int $jobId, ?EmailInvitationStatus $status, PageRequest $request): Page
    {
        $query = $this->connection->table(self::TABLE)->where('job_id', $jobId);

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $total = (clone $query)->count();
        $rows = $total === 0 ? [] : $query->orderByDesc('created_at')->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get()->all();

        return new Page(array_map(fn (stdClass $row): EmailInvitation => $this->toInvitation($row), $rows), $total, $request);
    }

    public function claimedFor(int $jobId, int $userId): ?EmailInvitation
    {
        $row = $this->connection->table(self::TABLE)->where('job_id', $jobId)->where('claimed_by_user_id', $userId)->where('status', 'claimed')->orderByDesc('id')->first();

        return $row === null ? null : $this->toInvitation($row);
    }

    public function openSentBefore(DateTimeImmutable $before, int $limit): array
    {
        return $this->many($this->connection->table(self::TABLE)->whereIn('status', ['sent', 'opened'])->where('created_at', '<', $this->format($before))->orderBy('id')->limit($limit)->get()->all());
    }

    public function claimedWithCvBefore(DateTimeImmutable $before, int $limit): array
    {
        return $this->many($this->connection->table(self::TABLE)->where('status', 'claimed')->whereNotNull('cv_path')->where('responded_at', '<', $this->format($before))->orderBy('id')->limit($limit)->get()->all());
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<EmailInvitation>
     */
    private function many(array $rows): array
    {
        return array_map(fn (stdClass $row): EmailInvitation => $this->toInvitation($row), $rows);
    }

    private function toInvitation(stdClass $row): EmailInvitation
    {
        return new EmailInvitation(
            (int) $row->company_id,
            (int) $row->job_id,
            $row->name,
            $row->email,
            EmailInvitationChannel::from((string) $row->channel),
            $row->note,
            (string) $row->token_hash,
            $row->cv_path,
            $row->cv_name,
            EmailInvitationStatus::from((string) $row->status),
            (int) $row->invited_by_user_id,
            $row->claimed_by_user_id === null ? null : (int) $row->claimed_by_user_id,
            $row->application_id === null ? null : (int) $row->application_id,
            $this->timeOrNull($row->opened_at),
            $this->timeOrNull($row->responded_at),
            $this->time((string) $row->expires_at),
            (int) $row->resent_count,
            $this->time((string) $row->last_sent_at),
            $this->time((string) $row->created_at),
            $this->time((string) $row->updated_at),
            (int) $row->id,
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }

    private function formatOrNull(?DateTimeImmutable $time): ?string
    {
        return $time === null ? null : $this->format($time);
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function timeOrNull(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : $this->time($value);
    }
}
