<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
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
        );
    }
}
