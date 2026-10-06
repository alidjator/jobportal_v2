<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use JsonException;
use Src\Recruitment\Application\ApplicantApplicationReader;
use Src\Recruitment\Application\ApplicationAnswerView;
use Src\Recruitment\Application\ApplicationDetailData;
use Src\Recruitment\Application\ApplicationHistoryEntry;
use Src\Recruitment\Application\ApplicationRow;
use Src\Recruitment\Domain\QuestionType;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use stdClass;

/**
 * Keyset pagination over (applied_at, id), newest first: the cursor holds the time and id of the last application of the previous slice, so a slice never
 * repeats or skips rows while new applications arrive.
 */
final readonly class DatabaseApplicantApplicationReader implements ApplicantApplicationReader
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function list(int $userId, ?StageType $status, CursorRequest $request): CursorPage
    {
        $builder = $this->connection->table('recruitment_applications')
            ->where('user_id', $userId)
            ->when($status !== null, fn (Builder $b) => $b->where('stage_type', $status?->value));

        if ($request->cursor !== null) {
            [$time, $id] = $this->decode($request->cursor);
            $builder->where(fn (Builder $after) => $after
                ->where('applied_at', '<', $time)
                ->orWhere(fn (Builder $tie) => $tie->where('applied_at', '=', $time)->where('id', '<', $id)));
        }

        $rows = $builder->orderByDesc('applied_at')->orderByDesc('id')->limit($request->perPage + 1)->get(['id', 'job_id', 'company_id', 'stage_type', 'applied_at'])->all();

        $hasMore = count($rows) > $request->perPage;
        $rows = array_slice($rows, 0, $request->perPage);
        $last = $rows === [] ? null : $rows[array_key_last($rows)];

        return new CursorPage(
            array_map(fn (stdClass $row): ApplicationRow => $this->toRow($row), $rows),
            $hasMore && $last !== null ? $this->encode((string) $last->applied_at, (int) $last->id) : null,
            $request->perPage,
        );
    }

    public function detail(int $userId, int $id): ?ApplicationDetailData
    {
        $row = $this->connection->table('recruitment_applications')->where('id', $id)->where('user_id', $userId)->first();

        if ($row === null) {
            return null;
        }

        return new ApplicationDetailData(
            $this->toRow($row),
            $row->cover_letter,
            $row->resume_path === null ? null : (string) $row->resume_name,
            $row->withdrawn_note,
            $this->history($id),
            $this->answers($id, (int) $row->job_id),
        );
    }

    /**
     * The history by stage type, oldest first. A move between two stages of the same type (an extra stage was deleted) shows nothing to the applicant,
     * so an entry that repeats the type before it is left out.
     *
     * @return list<ApplicationHistoryEntry>
     */
    private function history(int $applicationId): array
    {
        $entries = $this->connection->table('recruitment_application_stage_history')
            ->where('application_id', $applicationId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['to_type', 'actor_kind', 'created_at'])
            ->map(static fn (stdClass $entry): ApplicationHistoryEntry => new ApplicationHistoryEntry(
                StageType::from($entry->to_type),
                new DateTimeImmutable((string) $entry->created_at, new DateTimeZone('UTC')),
                StageMoveActor::from($entry->actor_kind),
            ))
            ->all();

        $shown = [];

        foreach ($entries as $entry) {
            if ($shown === [] || $shown[array_key_last($shown)]->status !== $entry->status) {
                $shown[] = $entry;
            }
        }

        return $shown;
    }

    /** @return list<ApplicationAnswerView> */
    private function answers(int $applicationId, int $jobId): array
    {
        $answers = $this->connection->table('recruitment_application_answers as a')
            ->join('recruitment_screening_questions as q', 'q.id', '=', 'a.question_id')
            ->leftJoin('recruitment_job_questions as jq', fn ($join) => $join->on('jq.question_id', '=', 'a.question_id')->where('jq.job_id', '=', $jobId))
            ->where('a.application_id', $applicationId)
            ->orderBy('jq.position')
            ->orderBy('a.id')
            ->get(['a.id as answer_id', 'a.question_id', 'q.text as question_text', 'q.type as question_type', 'a.answer_text', 'a.answer_number', 'a.answer_bool'])
            ->all();

        $options = [];

        if ($answers !== []) {
            foreach ($this->connection->table('recruitment_application_answer_options as ao')
                ->join('recruitment_screening_question_options as o', 'o.id', '=', 'ao.option_id')
                ->whereIn('ao.answer_id', array_map(static fn (stdClass $answer): int => (int) $answer->answer_id, $answers))
                ->orderBy('o.position')
                ->get(['ao.answer_id', 'o.id', 'o.label']) as $option) {
                $options[(int) $option->answer_id][] = ['id' => (int) $option->id, 'label' => (string) $option->label];
            }
        }

        return array_map(static fn (stdClass $answer): ApplicationAnswerView => new ApplicationAnswerView(
            (int) $answer->question_id,
            (string) $answer->question_text,
            QuestionType::from($answer->question_type),
            $answer->answer_text,
            $answer->answer_number === null ? null : (int) $answer->answer_number,
            $answer->answer_bool === null ? null : (bool) $answer->answer_bool,
            $options[(int) $answer->answer_id] ?? [],
        ), $answers);
    }

    private function toRow(stdClass $row): ApplicationRow
    {
        return new ApplicationRow(
            (int) $row->id,
            (int) $row->job_id,
            (int) $row->company_id,
            StageType::from($row->stage_type),
            new DateTimeImmutable((string) $row->applied_at, new DateTimeZone('UTC')),
        );
    }

    private function encode(string $time, int $id): string
    {
        return rtrim(strtr(base64_encode(json_encode([$time, $id], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @return array{string, int} */
    private function decode(string $cursor): array
    {
        try {
            $decoded = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->invalid();
        }

        if (! is_array($decoded) || count($decoded) !== 2 || ! is_string($decoded[0]) || ! is_int($decoded[1]) || $decoded[1] < 1
            || DateTimeImmutable::createFromFormat(self::TIME_FORMAT, $decoded[0]) === false) {
            throw $this->invalid();
        }

        return [$decoded[0], $decoded[1]];
    }

    private function invalid(): BusinessRuleViolation
    {
        return new BusinessRuleViolation('The cursor is not valid. Start again without a cursor.', 'invalid-cursor');
    }
}
