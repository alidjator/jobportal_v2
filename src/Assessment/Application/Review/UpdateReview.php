<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\ReportTemplateRules;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The psychologist edits the draft: the text of the report, the explanation for the candidate and their own notes. All three are sent every time. */
final readonly class UpdateReview
{
    public const int TEXT_MAX = ReportTemplateRules::BODY_MAX;

    public const int EXPLANATION_MAX = 5000;

    public const int NOTES_MAX = 5000;

    public function __construct(
        private ReviewAccess $access,
        private ReportRepository $reports,
        private ReviewViews $views,
        private ReviewClaims $claims,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `report-signed` or `claim-required`
     * @throws BusinessRuleViolation `invalid-report`
     */
    public function execute(Actor $actor, int $reportId, string $body, ?string $explanation, ?string $reviewNotes): ReviewView
    {
        return $this->transactions->run(function () use ($actor, $reportId, $body, $explanation, $reviewNotes): ReviewView {
            $psychologist = $this->access->psychologist($actor);
            $report = $this->access->report($psychologist, $reportId, forUpdate: true);

            $body = trim($body);

            if ($body === '') {
                throw new BusinessRuleViolation('The report text cannot be empty.', 'invalid-report', extensions: ['errors' => ['body' => ['The report text cannot be empty.']]]);
            }

            $updated = $report->edit($body, $this->clean($explanation), $this->clean($reviewNotes), $this->clock->now());
            $this->claims->assertHeld($psychologist, $report);
            $this->reports->update($updated->claimTouched($this->clock->now()));

            return $this->views->build($psychologist, $updated->claimTouched($this->clock->now()));
        });
    }

    private function clean(?string $text): ?string
    {
        return $text === null || trim($text) === '' ? null : trim($text);
    }
}
