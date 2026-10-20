<?php

namespace Src\JobAlert\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\JobAlert\Domain\AlertCriteria;
use Src\JobAlert\Domain\AlertFrequency;
use Src\JobAlert\Domain\JobAlert;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Job\LiveJobProfile;
use stdClass;

final readonly class DatabaseJobAlertRepository implements JobAlertRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(JobAlert $alert): JobAlert
    {
        $id = (int) $this->connection->table('jobalert_alerts')->insertGetId([
            'user_id' => $alert->userId,
            'name' => $alert->name,
            'frequency' => $alert->frequency->value,
            'criteria' => json_encode($alert->criteria->toArray(), JSON_THROW_ON_ERROR),
            'keywords' => json_encode($alert->criteria->keywords(), JSON_THROW_ON_ERROR),
            'email' => $alert->email,
            'in_app' => $alert->inApp,
            'paused' => $alert->paused,
            'last_run_at' => $this->format($alert->lastRunAt),
            'created_at' => $this->format($alert->createdAt),
            'updated_at' => $this->format($alert->updatedAt),
        ]);

        return new JobAlert(
            $alert->userId, $alert->name, $alert->frequency, $alert->criteria, $alert->email, $alert->inApp, $alert->paused, $alert->lastRunAt,
            $alert->createdAt, $alert->updatedAt, $id,
        );
    }

    public function update(JobAlert $alert): void
    {
        $this->connection->table('jobalert_alerts')->where('id', $alert->id)->update([
            'name' => $alert->name,
            'frequency' => $alert->frequency->value,
            'criteria' => json_encode($alert->criteria->toArray(), JSON_THROW_ON_ERROR),
            'keywords' => json_encode($alert->criteria->keywords(), JSON_THROW_ON_ERROR),
            'email' => $alert->email,
            'in_app' => $alert->inApp,
            'paused' => $alert->paused,
            'last_run_at' => $this->format($alert->lastRunAt),
            'updated_at' => $this->format($alert->updatedAt),
        ]);
    }

    public function findById(int $alertId): ?JobAlert
    {
        $row = $this->connection->table('jobalert_alerts')->where('id', $alertId)->first();

        return $row === null ? null : $this->toAlert($row);
    }

    public function findOfUser(int $userId, int $alertId, bool $forUpdate = false): ?JobAlert
    {
        $query = $this->connection->table('jobalert_alerts')->where('id', $alertId)->where('user_id', $userId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toAlert($row);
    }

    public function ofUser(int $userId): array
    {
        return array_map(
            fn (stdClass $row): JobAlert => $this->toAlert($row),
            $this->connection->table('jobalert_alerts')->where('user_id', $userId)->orderBy('id')->get()->all(),
        );
    }

    public function countOfUser(int $userId, bool $forUpdate = false): int
    {
        $query = $this->connection->table('jobalert_alerts')->where('user_id', $userId);

        // A count cannot take a lock, so the rows themselves are locked (the gap too), which makes a second creation wait for the first.
        return $forUpdate ? count($query->lockForUpdate()->pluck('id')) : $query->count();
    }

    public function delete(int $alertId): void
    {
        $this->connection->table('jobalert_alerts')->where('id', $alertId)->delete();
    }

    public function deleteOfUser(int $userId): void
    {
        $this->connection->table('jobalert_alerts')->where('user_id', $userId)->delete();
    }

    public function moveCursorOfUser(int $userId, DateTimeImmutable $at): void
    {
        $this->connection->table('jobalert_alerts')
            ->where('user_id', $userId)
            ->where('last_run_at', '<', $this->format($at))
            ->update(['last_run_at' => $this->format($at)]);
    }

    public function activeAfter(AlertFrequency $frequency, int $afterId, int $limit): array
    {
        return array_map(
            fn (stdClass $row): JobAlert => $this->toAlert($row),
            $this->connection->table('jobalert_alerts')
                ->where('frequency', $frequency->value)
                ->where('paused', false)
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->all(),
        );
    }

    public function immediateFitting(LiveJobProfile $job, int $afterId, int $limit): array
    {
        $query = $this->connection->table('jobalert_alerts')
            ->where('frequency', AlertFrequency::Immediate->value)
            ->where('paused', false)
            ->where('id', '>', $afterId)
            ->where('last_run_at', '<', $this->format($job->publishedAt));

        // An id filter holds when it is absent or empty, or when its list holds the job's id (a job without that id fits no alert that asks for it).
        // The criteria were validated as lists of ids, so the array functions never see a scalar.
        foreach ($job->ids as $filter => $id) {
            $column = "criteria->'{$filter}'";
            $absent = "COALESCE(jsonb_array_length({$column}), 0) = 0";

            if ($id === null) {
                $query->whereRaw($absent);
            } else {
                $query->whereRaw("({$absent} OR {$column} @> ?::jsonb)", [json_encode($id, JSON_THROW_ON_ERROR)]);
            }
        }

        $query->whereRaw(
            "(COALESCE(jsonb_array_length(criteria->'skill_id'), 0) = 0 OR EXISTS ("
            ."SELECT 1 FROM jsonb_array_elements_text(criteria->'skill_id') AS wanted(id) JOIN jsonb_array_elements_text(?::jsonb) AS offered(id) ON offered.id = wanted.id))",
            [json_encode($job->skillIds, JSON_THROW_ON_ERROR)],
        );

        $query->whereRaw(
            "(COALESCE(jsonb_array_length(criteria->'work_mode'), 0) = 0".($job->workMode === null ? ')' : " OR criteria->'work_mode' @> ?::jsonb)"),
            $job->workMode === null ? [] : [json_encode($job->workMode, JSON_THROW_ON_ERROR)],
        );

        // A minimum salary needs a salary the company shows, in the same period, whose upper bound reaches it.
        if ($job->salaryPeriodId === null || $job->salaryCeiling === null) {
            $query->whereRaw("criteria->>'salary_min' IS NULL");
        } else {
            $query->whereRaw(
                "(criteria->>'salary_min' IS NULL OR ((criteria->>'salary_period_id')::bigint = ? AND ?::bigint >= (criteria->>'salary_min')::bigint))",
                [$job->salaryPeriodId, $job->salaryCeiling],
            );
        }

        // The keyword must match the full text vector of the job exactly as the public search matches it: the words are split and unaccented by PostgreSQL, every word
        // must be there and the last one may be a prefix (the same expression as `DatabaseJobSearchReader`).
        $query->whereRaw(
            "(jsonb_array_length(keywords) = 0 OR ?::tsvector @@ to_tsquery('simple', regexp_replace(plainto_tsquery('simple', immutable_unaccent((SELECT string_agg(word.text, ' ' ORDER BY word.position) FROM jsonb_array_elements_text(keywords) WITH ORDINALITY AS word(text, position))))::text, '''$', ''':*')))",
            [$job->searchVector],
        );

        return array_map(
            fn (stdClass $row): JobAlert => $this->toAlert($row),
            $query->orderBy('id')->limit($limit)->get()->all(),
        );
    }

    public function reserveEmail(int $alertId, string $day): bool
    {
        $table = $this->connection->table('jobalert_alerts')->getGrammar()->wrapTable('jobalert_alerts');

        // One statement, so two jobs going live at once cannot both take the last slot. Every expression reads the row as it was before
        // the statement, so the count restarts at 1 on a new day.
        return $this->connection->update(
            "UPDATE {$table} SET emails_sent = CASE WHEN emails_day = ? THEN emails_sent + 1 ELSE 1 END, emails_day = ? "
            .'WHERE id = ? AND (emails_day IS NULL OR emails_day <> ? OR emails_sent < ?)',
            [$day, $day, $alertId, $day, JobAlert::MAX_EMAILS_PER_DAY],
        ) === 1;
    }

    private function toAlert(stdClass $row): JobAlert
    {
        /** @var array<string, mixed> $filters */
        $filters = json_decode((string) $row->criteria, true, flags: JSON_THROW_ON_ERROR);

        return new JobAlert(
            (int) $row->user_id,
            (string) $row->name,
            AlertFrequency::from((string) $row->frequency),
            AlertCriteria::restore($filters),
            (bool) $row->email,
            (bool) $row->in_app,
            (bool) $row->paused,
            $this->time((string) $row->last_run_at),
            $this->time((string) $row->created_at),
            $this->time((string) $row->updated_at),
            (int) $row->id,
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
