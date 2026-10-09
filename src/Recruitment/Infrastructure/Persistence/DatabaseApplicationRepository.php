<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationAnswer;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\ApplicationResume;
use Src\Recruitment\Domain\ApplicationSource;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Domain\Exceptions\StateConflict;

final readonly class DatabaseApplicationRepository implements ApplicationRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function hasLiveApplication(int $userId, int $jobId): bool
    {
        return $this->connection->table('recruitment_applications')->where('active_key', $userId.'-'.$jobId)->exists();
    }

    public function countSubmittedSince(int $userId, DateTimeImmutable $since): int
    {
        return $this->connection->table('recruitment_applications')
            ->where('user_id', $userId)
            ->where('applied_at', '>=', $this->utc($since))
            ->count();
    }

    public function find(int $id, bool $forUpdate = false): ?Application
    {
        return $this->load($this->connection->table('recruitment_applications')->where('id', $id), $forUpdate);
    }

    public function findOfApplicant(int $userId, int $id, bool $forUpdate = false): ?Application
    {
        return $this->load($this->connection->table('recruitment_applications')->where('id', $id)->where('user_id', $userId), $forUpdate);
    }

    private function load(Builder $query, bool $forUpdate): ?Application
    {
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        if ($row === null) {
            return null;
        }

        $snapshot = $row->profile_snapshot === null ? [] : json_decode((string) $row->profile_snapshot, true, 512, JSON_THROW_ON_ERROR);

        return new Application(
            (int) $row->user_id,
            (int) $row->job_id,
            (int) $row->company_id,
            (int) $row->stage_id,
            StageType::from($row->stage_type),
            $row->cover_letter,
            $row->resume_path === null ? null : new ApplicationResume((string) $row->resume_name, (string) $row->resume_path, (int) $row->resume_size_bytes),
            is_array($snapshot) ? $snapshot : [],
            $row->meets_must_have === null ? null : (bool) $row->meets_must_have,
            $row->withdrawn_note,
            new DateTimeImmutable((string) $row->applied_at, new DateTimeZone('UTC')),
            (int) $row->id,
            $row->source === null ? null : ApplicationSource::from($row->source),
            $row->match_score === null ? null : (int) $row->match_score,
        );
    }

    public function recordWithdrawal(Application $before, Application $after, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_applications')->where('id', $before->id)->update(['withdrawn_note' => $after->withdrawnNote]);

        $this->recordTransition($before, $after, StageMoveActor::Applicant, $before->userId, null, null, $at);
    }

    public function recordMove(Application $before, Application $after, ?int $actorId, ?RejectionReason $reason, ?string $note, DateTimeImmutable $at): void
    {
        $this->recordTransition($before, $after, StageMoveActor::Recruiter, $actorId, $reason, $note, $at);
    }

    public function recordHire(Application $before, Application $after, DateTimeImmutable $at): void
    {
        $this->recordTransition($before, $after, StageMoveActor::Applicant, $before->userId, null, null, $at);
    }

    /** The new stage, the key (freed by a withdrawal) and one history entry. */
    private function recordTransition(Application $before, Application $after, StageMoveActor $by, ?int $actorId, ?RejectionReason $reason, ?string $note, DateTimeImmutable $at): void
    {
        $time = $this->utc($at);

        $this->connection->table('recruitment_applications')->where('id', $before->id)->update([
            'stage_id' => $after->stageId,
            'stage_type' => $after->stageType->value,
            'active_key' => $after->activeKey(),
            'updated_at' => $time,
        ]);

        $this->connection->table('recruitment_application_stage_history')->insert([
            'application_id' => $before->id,
            'from_stage_id' => $before->stageId,
            'to_stage_id' => $after->stageId,
            'from_type' => $before->stageType->value,
            'to_type' => $after->stageType->value,
            'actor_kind' => $by->value,
            'actor_id' => $actorId,
            'rejection_reason' => $reason?->value,
            'note' => $note,
            'created_at' => $time,
        ]);
    }

    public function moveStage(int $fromStageId, int $toStageId, StageType $type, DateTimeImmutable $at): int
    {
        $time = $this->utc($at);

        $this->connection->table('recruitment_application_stage_history')->insertUsing(
            ['application_id', 'from_stage_id', 'to_stage_id', 'from_type', 'to_type', 'actor_kind', 'actor_id', 'rejection_reason', 'note', 'created_at'],
            $this->connection->table('recruitment_applications')
                ->where('stage_id', $fromStageId)
                ->selectRaw('id, ?::bigint, ?::bigint, ?::varchar, ?::varchar, ?::varchar, NULL::bigint, NULL::varchar, NULL::varchar, ?::timestamp', [$fromStageId, $toStageId, $type->value, $type->value, StageMoveActor::System->value, $time]),
        );

        return $this->connection->table('recruitment_applications')->where('stage_id', $fromStageId)->update(['stage_id' => $toStageId, 'updated_at' => $time]);
    }

    public function add(Application $application, array $answers): Application
    {
        $appliedAt = $this->utc($application->appliedAt);

        try {
            $id = (int) $this->connection->table('recruitment_applications')->insertGetId([
                'user_id' => $application->userId,
                'job_id' => $application->jobId,
                'company_id' => $application->companyId,
                'stage_id' => $application->stageId,
                'stage_type' => $application->stageType->value,
                'active_key' => $application->activeKey(),
                'cover_letter' => $application->coverLetter,
                'resume_name' => $application->resume?->name,
                'resume_path' => $application->resume?->path,
                'resume_size_bytes' => $application->resume?->sizeBytes,
                'profile_snapshot' => json_encode($application->profileSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'meets_must_have' => $application->meetsMustHave,
                'source' => $application->source?->value,
                'match_score' => $application->matchScore,
                'withdrawn_note' => $application->withdrawnNote,
                'applied_at' => $appliedAt,
                'created_at' => $appliedAt,
                'updated_at' => $appliedAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('You have already applied to this job.', 'already-applied');
        }

        $this->connection->table('recruitment_application_stage_history')->insert([
            'application_id' => $id,
            'from_stage_id' => null,
            'to_stage_id' => $application->stageId,
            'from_type' => null,
            'to_type' => $application->stageType->value,
            'actor_kind' => StageMoveActor::Applicant->value,
            'actor_id' => $application->userId,
            'rejection_reason' => null,
            'note' => null,
            'created_at' => $appliedAt,
        ]);

        foreach ($answers as $answer) {
            $this->addAnswer($id, $answer);
        }

        return new Application(
            $application->userId,
            $application->jobId,
            $application->companyId,
            $application->stageId,
            $application->stageType,
            $application->coverLetter,
            $application->resume,
            $application->profileSnapshot,
            $application->meetsMustHave,
            $application->withdrawnNote,
            $application->appliedAt,
            $id,
            $application->source,
            $application->matchScore,
        );
    }

    private function addAnswer(int $applicationId, ApplicationAnswer $answer): void
    {
        $answerId = (int) $this->connection->table('recruitment_application_answers')->insertGetId([
            'application_id' => $applicationId,
            'question_id' => $answer->questionId,
            'answer_text' => $answer->text,
            'answer_number' => $answer->number,
            'answer_bool' => $answer->bool,
        ]);

        if ($answer->optionIds !== []) {
            $this->connection->table('recruitment_application_answer_options')->insert(
                array_map(static fn (int $optionId): array => ['answer_id' => $answerId, 'option_id' => $optionId], $answer->optionIds),
            );
        }
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
