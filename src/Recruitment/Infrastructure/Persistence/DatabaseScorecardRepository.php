<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\Recommendation;
use Src\Recruitment\Domain\Scorecard;
use Src\Recruitment\Domain\ScorecardAnswer;
use Src\Recruitment\Domain\ScorecardRepository;
use Src\Recruitment\Domain\ScorecardRevision;
use Src\Recruitment\Domain\ScorecardStatus;
use stdClass;

final readonly class DatabaseScorecardRepository implements ScorecardRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $interviewId, int $memberId): ?Scorecard
    {
        $row = $this->connection->table('recruitment_interview_scorecards')->where('interview_id', $interviewId)->where('member_id', $memberId)->first();

        return $row === null ? null : $this->toScorecards([$row])[0];
    }

    public function findById(int $scorecardId): ?Scorecard
    {
        $row = $this->connection->table('recruitment_interview_scorecards')->where('id', $scorecardId)->first();

        return $row === null ? null : $this->toScorecards([$row])[0];
    }

    public function sent(int $interviewId): array
    {
        $rows = $this->connection->table('recruitment_interview_scorecards')
            ->where('interview_id', $interviewId)
            ->where('status', ScorecardStatus::Submitted->value)
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get()
            ->all();

        return $this->toScorecards($rows);
    }

    public function sentBy(int $interviewId): array
    {
        return array_map('intval', $this->connection->table('recruitment_interview_scorecards')
            ->where('interview_id', $interviewId)
            ->where('status', ScorecardStatus::Submitted->value)
            ->orderBy('id')
            ->pluck('member_id')
            ->all());
    }

    public function save(Scorecard $scorecard): Scorecard
    {
        $now = $this->format(new DateTimeImmutable);
        $values = [
            'status' => $scorecard->status->value,
            'recommendation' => $scorecard->recommendation?->value,
            'note' => $scorecard->note,
            'score' => $scorecard->score,
            'submitted_at' => $scorecard->submittedAt === null ? null : $this->format($scorecard->submittedAt),
            'edited_at' => $scorecard->editedAt === null ? null : $this->format($scorecard->editedAt),
            'updated_at' => $now,
        ];

        if ($scorecard->id === null) {
            $id = (int) $this->connection->table('recruitment_interview_scorecards')->insertGetId([
                'interview_id' => $scorecard->interviewId,
                'member_id' => $scorecard->memberId,
                ...$values,
                'created_at' => $now,
            ]);
        } else {
            $id = $scorecard->id;
            $this->connection->table('recruitment_interview_scorecards')->where('id', $id)->update($values);
            $this->connection->table('recruitment_interview_scorecard_answers')->where('scorecard_id', $id)->delete();
        }

        if ($scorecard->answers !== []) {
            $this->connection->table('recruitment_interview_scorecard_answers')->insert(array_map(
                static fn (ScorecardAnswer $answer): array => ['scorecard_id' => $id, 'question_id' => $answer->questionId, 'score' => $answer->score, 'note' => $answer->note],
                $scorecard->answers,
            ));
        }

        return new Scorecard(
            $scorecard->interviewId,
            $scorecard->memberId,
            $scorecard->status,
            $scorecard->recommendation,
            $scorecard->note,
            $scorecard->answers,
            $scorecard->score,
            $scorecard->submittedAt,
            $scorecard->editedAt,
            $id,
        );
    }

    public function deleteDrafts(int $interviewId, array $memberIds): void
    {
        if ($memberIds === []) {
            return;
        }

        $this->connection->table('recruitment_interview_scorecards')
            ->where('interview_id', $interviewId)
            ->whereIn('member_id', $memberIds)
            ->where('status', ScorecardStatus::Draft->value)
            ->delete();
    }

    public function addRevision(ScorecardRevision $revision): void
    {
        $this->connection->table('recruitment_interview_scorecard_revisions')->insert([
            'scorecard_id' => $revision->scorecardId,
            'previous' => json_encode($revision->previous, JSON_THROW_ON_ERROR),
            'others_visible' => $revision->othersVisible,
            'created_at' => $this->format($revision->createdAt),
        ]);
    }

    public function revisions(int $scorecardId): array
    {
        return $this->connection->table('recruitment_interview_scorecard_revisions')
            ->where('scorecard_id', $scorecardId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (stdClass $row): ScorecardRevision => new ScorecardRevision(
                (int) $row->scorecard_id,
                json_decode((string) $row->previous, true, 512, JSON_THROW_ON_ERROR),
                (bool) $row->others_visible,
                $this->time((string) $row->created_at),
            ))
            ->all();
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<Scorecard>
     */
    private function toScorecards(array $rows): array
    {
        $answers = [];

        $answerRows = $this->connection->table('recruitment_interview_scorecard_answers')
            ->whereIn('scorecard_id', array_map(static fn (stdClass $row): int => (int) $row->id, $rows))
            ->orderBy('question_id')
            ->get();

        foreach ($answerRows as $answer) {
            $answers[(int) $answer->scorecard_id][] = new ScorecardAnswer((int) $answer->question_id, $answer->score === null ? null : (int) $answer->score, $answer->note === null ? null : (string) $answer->note);
        }

        return array_map(fn (stdClass $row): Scorecard => new Scorecard(
            (int) $row->interview_id,
            (int) $row->member_id,
            ScorecardStatus::from((string) $row->status),
            $row->recommendation === null ? null : Recommendation::from((string) $row->recommendation),
            $row->note === null ? null : (string) $row->note,
            $answers[(int) $row->id] ?? [],
            $row->score === null ? null : (float) $row->score,
            $row->submitted_at === null ? null : $this->time((string) $row->submitted_at),
            $row->edited_at === null ? null : $this->time((string) $row->edited_at),
            (int) $row->id,
        ), $rows);
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
