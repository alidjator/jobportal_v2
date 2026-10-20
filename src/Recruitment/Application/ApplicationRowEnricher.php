<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewRsvps;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Pagination\Page;

/**
 * Adds to the rows of a page what other contexts and tables know about each application, in one read per kind for the whole page: the latest interview result the
 * member may see, the other active applications of the same person, the answers of the interview panel and the shortlist reviews.
 */
final readonly class ApplicationRowEnricher
{
    public function __construct(
        private InterviewResults $results,
        private InterviewRsvps $rsvps,
        private ApplicationReviews $reviews,
        private OtherApplications $others,
        private ApplicantContacts $contacts,
    ) {}

    /**
     * @param  Page<RecruiterApplicationRow>  $page
     * @return Page<RecruiterApplicationRow>
     */
    public function enrich(RecruitmentMember $member, Page $page): Page
    {
        $ids = array_map(static fn (RecruiterApplicationRow $row): int => $row->id, $page->items);
        $results = $this->results->latestForApplications($ids, $member->userId);
        $others = $this->others->counts($member, $ids);
        $answers = $this->rsvps->countsForApplications($ids);
        $finalIds = array_values(array_map(static fn (RecruiterApplicationRow $row): int => $row->id, array_filter($page->items, static fn (RecruiterApplicationRow $row): bool => $row->stageType->isTerminal() || $row->stageType === StageType::Hired)));
        $summaries = $this->reviews->summaries($ids, $member->userId, $finalIds);
        $undeliverable = array_flip($this->contacts->undeliverable(array_values(array_unique(array_map(static fn (RecruiterApplicationRow $row): int => $row->userId, $page->items)))));

        return new Page(
            array_map(static fn (RecruiterApplicationRow $row): RecruiterApplicationRow => $row->withInterviewResult($results[$row->id] ?? null)->withOtherApplications($others[$row->id] ?? 0)->withInterviewRsvp($answers[$row->id] ?? null)->withReviewSummary($summaries[$row->id] ?? null)->withEmailDeliverable(! isset($undeliverable[$row->userId])), $page->items),
            $page->total,
            $page->request,
        );
    }
}
