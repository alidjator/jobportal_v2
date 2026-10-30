<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Src\Assessment\Domain\IntegrityTier;
use Src\Assessment\Domain\InvitationStatus;
use Src\Assessment\Domain\NormCandidate;
use Src\Assessment\Domain\ReportStatus;
use Src\Assessment\Domain\ResultBand;
use Src\Assessment\Domain\ResultStatus;
use Src\Assessment\Domain\SubmittedAssessment;
use Src\Assessment\Domain\SubmittedAssessmentReader;

final readonly class DatabaseSubmittedAssessmentReader implements SubmittedAssessmentReader
{
    public function __construct(private ConnectionInterface $connection) {}

    public function latest(array $applicationIds, ?int $stageId = null, ?int $testId = null): array
    {
        $latest = [];

        foreach (array_chunk($applicationIds, 1000) as $chunk) {
            $rows = $this->connection->table('assessment_invitations as invitation')
                ->join('assessment_attempts as attempt', 'attempt.invitation_id', '=', 'invitation.id')
                ->leftJoin('assessment_results as result', 'result.attempt_id', '=', 'attempt.id')
                ->leftJoin('assessment_reports as report', function (JoinClause $join): void {
                    $join->on('report.attempt_id', '=', 'attempt.id')->where('report.status', ReportStatus::Signed->value);
                })
                ->whereIn('invitation.application_id', $chunk)
                ->where('invitation.status', InvitationStatus::Submitted->value)
                ->whereNotNull('attempt.submitted_at')
                ->when($stageId !== null, fn ($query) => $query->where('invitation.stage_id', $stageId))
                ->when($testId !== null, fn ($query) => $query->where('invitation.test_id', $testId))
                ->orderByDesc('attempt.submitted_at')
                ->orderByDesc('invitation.id')
                ->get([
                    'invitation.id as invitation_id', 'invitation.application_id', 'invitation.test_id', 'invitation.stage_id',
                    'attempt.id as attempt_id', 'attempt.submitted_at', 'attempt.norm_age', 'attempt.norm_education_rank',
                    'result.status', 'result.percentage', 'result.band', 'result.integrity_tier', 'result.section_scores', 'result.scale_scores',
                    'report.report_number as report_number',
                ]);

            foreach ($rows as $row) {
                $latest[(int) $row->application_id] ??= $this->hydrate($row);
            }
        }

        return $latest;
    }

    private function hydrate(object $row): SubmittedAssessment
    {
        return new SubmittedAssessment(
            (int) $row->application_id,
            (int) $row->invitation_id,
            (int) $row->test_id,
            $row->stage_id === null ? null : (int) $row->stage_id,
            (int) $row->attempt_id,
            new DateTimeImmutable((string) $row->submitted_at, new DateTimeZone('UTC')),
            new NormCandidate($row->norm_age === null ? null : (int) $row->norm_age, $row->norm_education_rank === null ? null : (int) $row->norm_education_rank),
            $row->status === null ? null : ResultStatus::from((string) $row->status),
            $row->percentage === null ? null : (float) $row->percentage,
            $row->band === null ? null : ResultBand::from((string) $row->band),
            $row->integrity_tier === null ? null : IntegrityTier::from((string) $row->integrity_tier),
            $row->section_scores === null ? [] : json_decode((string) $row->section_scores, true, flags: JSON_THROW_ON_ERROR),
            $row->scale_scores === null ? null : array_map('floatval', json_decode((string) $row->scale_scores, true, flags: JSON_THROW_ON_ERROR)),
            $row->report_number === null ? null : (string) $row->report_number,
        );
    }
}
