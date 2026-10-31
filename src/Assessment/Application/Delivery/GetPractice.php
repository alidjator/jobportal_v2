<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The practice questions of a test the job seeker was asked to take (`docs/design/phase-8-candidate-experience.md` section 4): the sample questions of the test, by section, with their correct
 * answers, so the candidate learns the kinds of question before the timer starts. It is only for a skill test whose invitation can still be taken (`invited` or `started`); anything else, and another
 * candidate's invitation, is a 404. Nothing is stored and no attempt or timer is involved; the samples are not drawn into attempts, so showing their answers gives nothing away.
 */
final readonly class GetPractice
{
    public function __construct(
        private InvitationRepository $invitations,
        private TestRepository $tests,
        private SectionRepository $sections,
        private QuestionRepository $questions,
    ) {}

    /**
     * @return array{invitation: Invitation, test: Test, sections: list<PracticeSection>}
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $invitationId): array
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can take a test.', 'job-seeker-only');
        }

        $invitation = $this->invitations->find($invitationId);

        if (! $invitation instanceof Invitation || $invitation->applicantUserId !== $actor->id || ! $invitation->status->isActive()) {
            throw EntityNotFound::of('Invitation', $invitationId);
        }

        $test = $this->tests->find($invitation->testId);

        if (! $test instanceof Test || $test->kind() !== TestKind::Skill) {
            throw EntityNotFound::of('Invitation', $invitationId);
        }

        $samples = [];

        foreach ($this->questions->samplesOfTest($invitation->testId) as $question) {
            $samples[$question->sectionId][] = $question;
        }

        $sections = [];

        foreach ($this->sections->ofTest($invitation->testId) as $section) {
            if (isset($samples[(int) $section->id])) {
                $sections[] = new PracticeSection($section, array_values($samples[(int) $section->id]));
            }
        }

        return ['invitation' => $invitation, 'test' => $test, 'sections' => $sections];
    }
}
