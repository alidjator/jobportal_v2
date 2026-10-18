<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** One job seeker's application to one job. The id is null until the application is written. */
final readonly class Application
{
    /** @param  array<string, mixed>  $profileSnapshot */
    public function __construct(
        public int $userId,
        public int $jobId,
        public int $companyId,
        public int $stageId,
        public StageType $stageType,
        public ?string $coverLetter,
        public ?ApplicationResume $resume,
        public array $profileSnapshot,
        public ?bool $meetsMustHave,
        public ?string $withdrawnNote,
        public DateTimeImmutable $appliedAt,
        public ?int $id = null,
        public ?ApplicationSource $source = null,
        public ?int $matchScore = null,
        public ?DateTimeImmutable $talentPoolConsentAt = null,
    ) {}

    /**
     * A new application, placed in the company's default `applied` stage.
     *
     * @param  array<string, mixed>  $profileSnapshot
     */
    public static function submit(
        int $userId,
        int $jobId,
        int $companyId,
        int $appliedStageId,
        ?string $coverLetter,
        ApplicationResume $resume,
        array $profileSnapshot,
        ?bool $meetsMustHave,
        DateTimeImmutable $now,
        ?ApplicationSource $source = null,
        ?int $matchScore = null,
        ?DateTimeImmutable $talentPoolConsentAt = null,
    ): self {
        return new self(
            $userId,
            $jobId,
            $companyId,
            $appliedStageId,
            StageType::Applied,
            $coverLetter,
            $resume,
            $profileSnapshot,
            $meetsMustHave,
            null,
            $now,
            null,
            $source,
            $matchScore,
            $talentPoolConsentAt,
        );
    }

    /**
     * The key behind the "one live application per applicant and job" rule: it is set until the application is withdrawn
     * (a rejected or hired application still blocks applying again) and null afterwards.
     */
    public function activeKey(): ?string
    {
        return $this->stageType === StageType::Withdrawn ? null : $this->userId.'-'.$this->jobId;
    }

    /** Whether the applicant allowed the company to keep their profile in its talent pool for other jobs. */
    public function hasTalentPoolConsent(): bool
    {
        return $this->talentPoolConsentAt !== null;
    }

    public function canBeWithdrawn(): bool
    {
        return $this->stageType->allowsWithdrawal();
    }

    /** The application after the applicant withdrew it into the company's default `withdrawn` stage. */
    public function withdraw(int $withdrawnStageId, ?string $note): self
    {
        if (! $this->canBeWithdrawn()) {
            throw new StateConflict('This application can no longer be withdrawn.', 'application-not-withdrawable');
        }

        return new self(
            $this->userId,
            $this->jobId,
            $this->companyId,
            $withdrawnStageId,
            StageType::Withdrawn,
            $this->coverLetter,
            $this->resume,
            $this->profileSnapshot,
            $this->meetsMustHave,
            $note,
            $this->appliedAt,
            $this->id,
            $this->source,
            $this->matchScore,
            $this->talentPoolConsentAt,
        );
    }

    /**
     * The application after a recruiter moved it to another active stage of the company. Any active stage will do (forward, backward or skipping), `hired`
     * included; a final application never moves again.
     *
     * @throws StateConflict `application-final` or `already-in-stage`
     * @throws BusinessRuleViolation `invalid-stage` when the target is a final stage
     */
    public function moveTo(int $stageId, StageType $type): self
    {
        $this->assertNotFinal();

        if (! $type->acceptsRecruiterMoves()) {
            throw new BusinessRuleViolation('An application cannot be moved to this stage.', 'invalid-stage', extensions: ['errors' => ['stage_id' => ['An application cannot be moved to this stage.']]]);
        }

        if ($stageId === $this->stageId) {
            throw new StateConflict('The application is already in this stage.', 'already-in-stage');
        }

        return $this->inStage($stageId, $type);
    }

    /**
     * The application after a recruiter rejected it, into the company's default `rejected` stage. It keeps its key, so the applicant cannot apply to the job again.
     *
     * @throws StateConflict `application-final`
     */
    public function reject(int $rejectedStageId): self
    {
        $this->assertNotFinal();

        return $this->inStage($rejectedStageId, StageType::Rejected);
    }

    /** @throws StateConflict `application-final` */
    private function assertNotFinal(): void
    {
        if ($this->stageType->isTerminal()) {
            throw new StateConflict('This application is final and cannot be moved.', 'application-final');
        }
    }

    private function inStage(int $stageId, StageType $type): self
    {
        return new self(
            $this->userId,
            $this->jobId,
            $this->companyId,
            $stageId,
            $type,
            $this->coverLetter,
            $this->resume,
            $this->profileSnapshot,
            $this->meetsMustHave,
            $this->withdrawnNote,
            $this->appliedAt,
            $this->id,
            $this->source,
            $this->matchScore,
            $this->talentPoolConsentAt,
        );
    }
}
