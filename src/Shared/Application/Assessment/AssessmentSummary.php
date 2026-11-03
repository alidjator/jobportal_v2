<?php

namespace Src\Shared\Application\Assessment;

use DateTimeImmutable;

/**
 * The one line a review card or a comparison row shows of an application's latest submitted test (`docs/design/phase-8-hiring-managers.md` section 4). A skill test carries `percentage` and `band`
 * (both null while a written answer waits for its assessor). A psychological battery carries only its state and, once the report is signed, the hiring recommendation: never a score, a scale or text of the report.
 */
final readonly class AssessmentSummary
{
    public function __construct(
        /** @var 'skill'|'psychological' */
        public string $kind,
        public string $testTitle,
        public DateTimeImmutable $submittedAt,
        /** @var 'scored'|'pending_scoring'|'awaiting_psychologist_review'|'signed' */
        public string $status,
        public ?float $percentage = null,
        public ?string $band = null,
        public ?string $recommendation = null,
        public ?string $recommendationLabel = null,
    ) {}

    /** @return array<string, mixed> the line as the API shows it */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'test_title' => $this->testTitle,
            'submitted_at' => $this->submittedAt->format(DATE_ATOM),
            'status' => $this->status,
            'percentage' => $this->percentage,
            'band' => $this->band,
            'recommendation' => $this->recommendation,
            'recommendation_label' => $this->recommendationLabel,
        ];
    }
}
