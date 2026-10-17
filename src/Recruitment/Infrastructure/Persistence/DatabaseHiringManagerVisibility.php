<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Recruitment\Application\HiringManagerVisibility;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Recruitment\ApplicationVisibility;
use Src\Shared\Application\Recruitment\HiringManagerBoundary;

/**
 * Keeps the marker `recruitment_applications.hiring_manager_visible_at`. The boundary of a job is a stage of the company: its position in the company's stage
 * order is the line, and every stage at or after it (the final stages `rejected` and `withdrawn` excepted) shows the application. A job without a boundary,
 * or whose boundary stage was deleted, uses the first interview stage of the company; a company without one shows nothing by position.
 */
final readonly class DatabaseHiringManagerVisibility implements ApplicationVisibility, HiringManagerBoundary, HiringManagerVisibility
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private JobDirectory $jobs, private Clock $clock) {}

    public function isBoundaryStage(int $companyId, int $stageId): bool
    {
        return $this->connection->table('recruitment_stages')->where('id', $stageId)->where('company_id', $companyId)->whereNotIn('type', $this->finalTypes())->exists();
    }

    public function reevaluateJob(int $jobId): void
    {
        $companyId = $this->connection->table('recruitment_applications')->where('job_id', $jobId)->value('company_id');

        if ($companyId === null) {
            return;
        }

        $position = $this->boundaryPosition((int) $companyId, $this->jobs->hiringManagerBoundary($jobId));

        if ($position !== null) {
            $this->mark((int) $companyId, $position, $this->clock->now(), static fn (Builder $applications): Builder => $applications->where('job_id', $jobId));
        }
    }

    public function refreshApplication(int $applicationId, DateTimeImmutable $at): void
    {
        $row = $this->connection->table('recruitment_applications')->where('id', $applicationId)->whereNull('hiring_manager_visible_at')->first(['job_id', 'company_id']);

        if ($row === null) {
            return;
        }

        $position = $this->boundaryPosition((int) $row->company_id, $this->jobs->hiringManagerBoundary((int) $row->job_id));

        if ($position !== null) {
            $this->mark((int) $row->company_id, $position, $at, static fn (Builder $applications): Builder => $applications->where('id', $applicationId));
        }
    }

    public function refreshCompany(int $companyId, DateTimeImmutable $at): void
    {
        $explicit = $this->jobs->hiringManagerBoundaries($companyId);
        $byPosition = [];

        foreach ($explicit as $jobId => $stageId) {
            $position = $this->boundaryPosition($companyId, $stageId);

            if ($position !== null) {
                $byPosition[$position][] = $jobId;
            }
        }

        foreach ($byPosition as $position => $jobIds) {
            $this->mark($companyId, $position, $at, static fn (Builder $applications): Builder => $applications->whereIn('job_id', $jobIds));
        }

        $default = $this->defaultPosition($companyId);

        if ($default !== null) {
            $this->mark($companyId, $default, $at, static fn (Builder $applications): Builder => $applications->whereNotIn('job_id', array_keys($explicit)));
        }
    }

    public function showToPanel(int $applicationId, int $jobId, array $userIds, DateTimeImmutable $at): void
    {
        $managers = $this->jobs->hiringManagerUserIds($jobId);

        if (array_intersect($userIds, $managers) === []) {
            return;
        }

        $this->connection->table('recruitment_applications')->where('id', $applicationId)->whereNull('hiring_manager_visible_at')
            ->update(['hiring_manager_visible_at' => $at->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT)]);
    }

    public function showForReview(int $applicationId, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_applications')->where('id', $applicationId)->whereNull('hiring_manager_visible_at')
            ->update(['hiring_manager_visible_at' => $at->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT)]);
    }

    public function visibleAmong(array $applicationIds, array $boundJobIds): array
    {
        if ($applicationIds === [] || $boundJobIds === []) {
            return $applicationIds;
        }

        $hidden = $this->connection->table('recruitment_applications')->whereIn('id', $applicationIds)->whereIn('job_id', $boundJobIds)->whereNull('hiring_manager_visible_at')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        return array_values(array_diff($applicationIds, $hidden));
    }

    public function isVisible(int $applicationId): bool
    {
        return $this->connection->table('recruitment_applications')->where('id', $applicationId)->whereNotNull('hiring_manager_visible_at')->exists();
    }

    /** The position of the boundary stage, or of the company's first interview stage when the job names none (or its stage is gone or final). */
    private function boundaryPosition(int $companyId, ?int $stageId): ?int
    {
        if ($stageId !== null) {
            $position = $this->connection->table('recruitment_stages')->where('id', $stageId)->where('company_id', $companyId)->whereNotIn('type', $this->finalTypes())->value('position');

            if ($position !== null) {
                return (int) $position;
            }
        }

        return $this->defaultPosition($companyId);
    }

    private function defaultPosition(int $companyId): ?int
    {
        $position = $this->connection->table('recruitment_stages')->where('company_id', $companyId)->where('type', StageType::Interview->value)->min('position');

        return $position === null ? null : (int) $position;
    }

    /** @param  Closure(Builder): Builder  $scope */
    private function mark(int $companyId, int $position, DateTimeImmutable $at, Closure $scope): void
    {
        $qualifying = $this->connection->table('recruitment_stages')->select('id')->where('company_id', $companyId)->where('position', '>=', $position)->whereNotIn('type', $this->finalTypes());

        $scope($this->connection->table('recruitment_applications')->where('company_id', $companyId)->whereNull('hiring_manager_visible_at')->whereIn('stage_id', $qualifying))
            ->update(['hiring_manager_visible_at' => $at->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT)]);
    }

    /** @return list<string> */
    private function finalTypes(): array
    {
        return [StageType::Rejected->value, StageType::Withdrawn->value];
    }
}
