<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\InvitationStatus;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The tests a job seeker was asked to take: a list, and one invitation with the test's instructions and the title, kind and length of its sections (never a
 * question). Another candidate's invitation is a 404.
 */
final readonly class MyInvitations
{
    public function __construct(
        private InvitationRepository $invitations,
        private TestRepository $tests,
        private SectionRepository $sections,
        private QuestionRepository $questions,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
    ) {}

    /**
     * @return Page<MyInvitationView>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function list(Actor $actor, ?InvitationStatus $status, PageRequest $request): Page
    {
        $page = $this->invitations->pageOfApplicant($this->userId($actor), $status, $request);

        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (Invitation $invitation): int => $invitation->jobId, $page->items))));
        $companies = $this->companies->summaries(array_values(array_unique(array_map(static fn (Invitation $invitation): int => $invitation->companyId, $page->items))));

        $views = array_map(fn (Invitation $invitation): MyInvitationView => new MyInvitationView(
            $invitation,
            $this->test($invitation),
            $titles[$invitation->jobId] ?? '',
            isset($companies[$invitation->companyId]) ? $companies[$invitation->companyId]->name : '',
            null,
        ), $page->items);

        return new Page($views, $page->total, $page->request);
    }

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function get(Actor $actor, int $invitationId): MyInvitationView
    {
        $invitation = $this->invitations->find($invitationId);

        if (! $invitation instanceof Invitation || $invitation->applicantUserId !== $this->userId($actor)) {
            throw EntityNotFound::of('Invitation', $invitationId);
        }

        return new MyInvitationView(
            $invitation,
            $this->test($invitation),
            $this->jobs->titles([$invitation->jobId])[$invitation->jobId] ?? '',
            $this->companyName($invitation->companyId),
            $this->sections->ofTest($invitation->testId),
            $this->questions->testHasType($invitation->testId, QuestionType::VideoAnswer),
        );
    }

    private function test(Invitation $invitation): Test
    {
        return $this->tests->find($invitation->testId) ?? throw EntityNotFound::of('Test', $invitation->testId);
    }

    private function companyName(int $companyId): string
    {
        $companies = $this->companies->summaries([$companyId]);

        return isset($companies[$companyId]) ? $companies[$companyId]->name : '';
    }

    private function userId(Actor $actor): int
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can take a test.', 'job-seeker-only');
        }

        return $actor->id;
    }
}
