<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\HireDetails;

/** Everything stored about one application for its applicant, before the job and company names are added. */
final readonly class ApplicationDetailData
{
    /**
     * @param  list<ApplicationHistoryEntry>  $history  oldest first
     * @param  list<ApplicationAnswerView>  $answers  in the order of the job's questions
     */
    public function __construct(
        public ApplicationRow $row,
        public ?string $coverLetter,
        public ?string $resumeName,
        public ?string $withdrawnNote,
        public array $history,
        public array $answers,
        public ?HireDetails $hire = null,
        public bool $talentPoolConsent = false,
    ) {}
}
