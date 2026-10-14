<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use LogicException;
use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\AttemptQuestion;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\InvitationStatus;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseAttemptRepository implements AttemptRepository
{
    private const string TABLE = 'assessment_attempts';

    private const string DRAWN = 'assessment_attempt_questions';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function find(int $id, bool $forUpdate = false): ?Attempt
    {
        return $this->first($this->connection->table(self::TABLE)->where('id', $id), $forUpdate);
    }

    public function findByInvitation(int $invitationId, bool $forUpdate = false): ?Attempt
    {
        return $this->first($this->connection->table(self::TABLE)->where('invitation_id', $invitationId), $forUpdate);
    }

    public function add(Attempt $attempt, array $drawn): Attempt
    {
        $now = $this->clock->now();

        $id = (int) $this->connection->table(self::TABLE)->insertGetId([
            'invitation_id' => $attempt->invitationId,
            'consented_at' => $this->format($attempt->consentedAt),
            'started_at' => $this->format($attempt->startedAt),
            'section_position' => $attempt->sectionPosition,
            'section_opened_at' => $this->format($attempt->sectionOpenedAt),
            'question_cursor' => $attempt->questionCursor,
            'ends_at' => $this->format($attempt->endsAt),
            'seed' => $attempt->seed,
            'last_ip' => $attempt->lastIp,
            'video_consented_at' => $attempt->videoConsentedAt === null ? null : $this->format($attempt->videoConsentedAt),
            'session_hash' => $attempt->sessionHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($drawn !== []) {
            $this->connection->table(self::DRAWN)->insert(array_map(static fn (AttemptQuestion $question): array => [
                'attempt_id' => $id,
                'section_id' => $question->sectionId,
                'question_id' => $question->questionId,
                'position' => $question->position,
            ], $drawn));
        }

        return $this->find($id) ?? throw new LogicException('The new attempt was not found.');
    }

    public function update(Attempt $attempt): void
    {
        $this->connection->table(self::TABLE)->where('id', $attempt->id)->update([
            'section_position' => $attempt->sectionPosition,
            'section_opened_at' => $this->format($attempt->sectionOpenedAt),
            'question_cursor' => $attempt->questionCursor,
            'ends_at' => $this->format($attempt->endsAt),
            'submitted_at' => $attempt->submittedAt === null ? null : $this->format($attempt->submittedAt),
            'auto_submitted' => $attempt->autoSubmitted,
            'last_ip' => $attempt->lastIp,
            'session_hash' => $attempt->sessionHash,
            'updated_at' => $this->clock->now(),
        ]);
    }

    public function drawnOf(int $attemptId): array
    {
        return array_values(array_map(static fn (stdClass $row): AttemptQuestion => new AttemptQuestion((int) $row->section_id, (int) $row->question_id, (int) $row->position), $this->connection->table(self::DRAWN)
            ->where('attempt_id', $attemptId)->orderBy('section_id')->orderBy('position')->get()->all()));
    }

    public function idsOutOfTime(DateTimeImmutable $now): array
    {
        // A cancelled invitation leaves its attempt unsubmitted for good, so only the invitations that are still started count.
        return array_values(array_map('intval', $this->connection->table(self::TABLE)
            ->join('assessment_invitations as invitations', 'invitations.id', '=', self::TABLE.'.invitation_id')
            ->where('invitations.status', InvitationStatus::Started->value)
            ->whereNull(self::TABLE.'.submitted_at')->where(self::TABLE.'.ends_at', '<=', $this->format($now))
            ->orderBy(self::TABLE.'.ends_at')->orderBy(self::TABLE.'.id')->pluck(self::TABLE.'.id')->all()));
    }

    private function first(Builder $query, bool $forUpdate): ?Attempt
    {
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    private function hydrate(stdClass $row): Attempt
    {
        return new Attempt(
            (int) $row->id,
            (int) $row->invitation_id,
            $this->time((string) $row->consented_at),
            $this->time((string) $row->started_at),
            (int) $row->section_position,
            $this->time((string) $row->section_opened_at),
            (int) $row->question_cursor,
            $this->time((string) $row->ends_at),
            $row->submitted_at === null ? null : $this->time((string) $row->submitted_at),
            (bool) $row->auto_submitted,
            (int) $row->seed,
            $row->last_ip,
            $row->video_consented_at === null ? null : $this->time((string) $row->video_consented_at),
            $row->session_hash,
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
