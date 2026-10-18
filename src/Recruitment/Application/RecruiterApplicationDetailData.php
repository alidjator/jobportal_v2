<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\HireDetails;
use Src\Recruitment\Domain\StageType;

/** Everything stored about one application for the company's team. The snapshot is null once the applicant's account was anonymised. */
final readonly class RecruiterApplicationDetailData
{
    /**
     * @param  array<string, mixed>|null  $snapshot
     * @param  list<ApplicationAnswerView>  $answers  in the order of the job's questions
     * @param  list<RecruiterHistoryEntry>  $history  oldest first
     */
    public function __construct(
        public int $id,
        public int $jobId,
        public int $companyId,
        public int $stageId,
        public StageType $stageType,
        public ?bool $meetsMustHave,
        public ?string $coverLetter,
        public ?string $resumeName,
        public ?string $withdrawnNote,
        public ?array $snapshot,
        public array $answers,
        public array $history,
        public DateTimeImmutable $appliedAt,
        public DateTimeImmutable $stageEnteredAt,
        public bool $contactHidden = false,
        public ?HireDetails $hire = null,
    ) {}

    /** The same application with the contact data of the applicant removed from the snapshot (see `ContactVisibility`). */
    public function withContactHidden(): self
    {
        $snapshot = $this->snapshot === null ? null : [...$this->snapshot, 'email' => null, 'phone' => null, 'whatsapp' => null];

        return new self(
            $this->id, $this->jobId, $this->companyId, $this->stageId, $this->stageType, $this->meetsMustHave, $this->coverLetter, $this->resumeName, $this->withdrawnNote,
            $snapshot, $this->answers, $this->history, $this->appliedAt, $this->stageEnteredAt, $this->snapshot !== null, $this->hire,
        );
    }
}
