<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Recruitment\Application\HiringManagerVisibility;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationAnswer;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\ApplicationResume;
use Src\Recruitment\Domain\ApplicationSource;
use Src\Recruitment\Domain\HireDetails;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;
use Src\Recruitment\Domain\WithdrawalReason;
use Src\Shared\Domain\Exceptions\StateConflict;

final readonly class DatabaseApplicationRepository implements ApplicationRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private HiringManagerVisibility $visibility) {}

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
            $row->talent_pool_consent_at === null ? null : new DateTimeImmutable((string) $row->talent_pool_consent_at, new DateTimeZone('UTC')),
        );
    }

    public function recordWithdrawal(Application $before, Application $after, DateTimeImmutable $at, ?WithdrawalReason $reason = null): void
    {
        $this->connection->table('recruitment_applications')->where('id', $before->id)->update(['withdrawn_note' => $after->withdrawnNote, 'withdrawal_reason' => $reason?->value]);

        $this->recordTransition($before, $after, StageMoveActor::Applicant, $before->userId, null, null, $at, $reason);
    }

    public function recordWithdrawalByTeam(Application $before, Application $after, int $actorId, WithdrawalReason $reason, ?string $note, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_applications')->where('id', $before->id)->update(['withdrawal_reason' => $reason->value]);

        $this->recordTransition($before, $after, StageMoveActor::Recruiter, $actorId, null, $note, $at, $reason);
    }

    public function recordMove(Application $before, Application $after, ?int $actorId, ?RejectionReason $reason, ?string $note, DateTimeImmutable $at, ?HireDetails $hire = null): void
    {
        $this->recordTransition($before, $after, StageMoveActor::Recruiter, $actorId, $reason, $note, $at, hire: $hire);
    }

    public function changeTalentPoolConsent(int $id, ?DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_applications')->where('id', $id)->update(['talent_pool_consent_at' => $at === null ? null : $this->utc($at)]);
    }

    public function markViewed(int $id, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_applications')->where('id', $id)->whereNull('viewed_at')->update(['viewed_at' => $this->utc($at)]);
    }

    public function recordHire(Application $before, Application $after, DateTimeImmutable $at, ?HireDetails $hire = null): void
    {
        $this->recordTransition($before, $after, StageMoveActor::Applicant, $before->userId, null, null, $at, hire: $hire);
    }

    public function hireOf(int $id): ?HireDetails
    {
        $row = $this->connection->table('recruitment_applications')->where('id', $id)->first(['join_date', 'hire_note']);

        return $row === null || $row->join_date === null ? null : new HireDetails(new DateTimeImmutable((string) $row->join_date, new DateTimeZone('UTC')), $row->hire_note);
    }

    public function updateHire(int $id, HireDetails $hire, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_applications')->where('id', $id)->update([
            'join_date' => $hire->joinDate->format('Y-m-d'),
            'hire_note' => $hire->note,
            'updated_at' => $this->utc($at),
        ]);
    }

    /** The new stage, the key (freed by a withdrawal), the hire details (set on entering `hired`, cleared on leaving it) and one history entry. */
    private function recordTransition(Application $before, Application $after, StageMoveActor $by, ?int $actorId, ?RejectionReason $reason, ?string $note, DateTimeImmutable $at, ?WithdrawalReason $withdrawalReason = null, ?HireDetails $hire = null): void
    {
        $time = $this->utc($at);

        $this->connection->table('recruitment_applications')->where('id', $before->id)->update([
            'stage_id' => $after->stageId,
            'stage_type' => $after->stageType->value,
            'active_key' => $after->activeKey(),
            'stage_entered_at' => $time,
            'updated_at' => $time,
            ...($after->stageType === StageType::Hired ? ['join_date' => $hire?->joinDate->format('Y-m-d'), 'hire_note' => $hire?->note] : ($before->stageType === StageType::Hired ? ['join_date' => null, 'hire_note' => null] : [])),
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
            'withdrawal_reason' => $withdrawalReason?->value,
            'note' => $note,
            'created_at' => $time,
        ]);

        $this->visibility->refreshApplication((int) $before->id, $at);
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

        $moved = $this->connection->table('recruitment_applications')->where('stage_id', $fromStageId)->update(['stage_id' => $toStageId, 'stage_entered_at' => $time, 'updated_at' => $time]);

        $companyId = $this->connection->table('recruitment_stages')->where('id', $toStageId)->value('company_id');

        if ($moved > 0 && $companyId !== null) {
            $this->visibility->refreshCompany((int) $companyId, $at);
        }

        return $moved;
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
                'talent_pool_consent_at' => $application->talentPoolConsentAt === null ? null : $this->utc($application->talentPoolConsentAt),
                'withdrawn_note' => $application->withdrawnNote,
                'applied_at' => $appliedAt,
                'stage_entered_at' => $appliedAt,
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

        $this->visibility->refreshApplication($id, $application->appliedAt);

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
            $application->talentPoolConsentAt,
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
